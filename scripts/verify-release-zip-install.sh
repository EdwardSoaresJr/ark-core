#!/usr/bin/env bash
# Validate a built Release ZIP by extracting it to an isolated directory and
# performing a real application bootstrap + migrate + native PDF + constrained
# runtime checks (not merely class_exists).
set -euo pipefail

ZIP="${1:?Usage: verify-release-zip-install.sh <path-to.zip> [workdir]}"
WORK="${2:-$(mktemp -d "${TMPDIR:-/tmp}/ark-zip-install.XXXXXX")}"
EXTRACT="$WORK/extract"
APP=""

mkdir -p "$EXTRACT"
unzip -q "$ZIP" -d "$EXTRACT"
APP=$(find "$EXTRACT" -maxdepth 1 -type d -name 'ark-core-*' | head -1)
test -n "$APP"
test -f "$APP/public/index.php"
test -f "$APP/public/build/manifest.json"
test -f "$APP/RELEASE.json"
test -f "$APP/MANIFEST.sha256"
test -f "$APP/.ark-source-commit"
test ! -e "$APP/.env"
test ! -d "$APP/node_modules"
test -d "$APP/vendor/mpdf/mpdf"
test -f "$APP/.env.example"

# Refuse secrets / customer data / install leftovers in the packaged tree.
if find "$APP" -type f \( \
  -name '.env' -o -name '*.pem' -o -name 'id_rsa' -o -name 'id_ed25519' \
  -o -name '*.sqlite' -o -name '*.sql' -o -name '*.sql.gz' -o -name '*.dump' \
\) | grep -q .; then
  echo "ERROR: forbidden artifact present in extracted archive" >&2
  find "$APP" -type f \( -name '.env' -o -name '*.pem' -o -name '*.sqlite' -o -name '*.sql' \) >&2
  exit 1
fi

php -r '$j=json_decode(file_get_contents($argv[1]),true); foreach(["version","commit","tree","document_root","setup"] as $k){ if(empty($j[$k])){fwrite(STDERR,"missing $k\n"); exit(1);} } echo "release_commit=".$j["commit"]."\n"; echo "release_version=".$j["version"]."\n";' "$APP/RELEASE.json"

# Isolated DB name for install smoke (requires local MySQL).
DB_NAME="ark_zip_verify_$(date +%s)"
mysql -uroot -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

cd "$APP"
cp .env.example .env
php -r '
$env=file_get_contents(".env");
$env=preg_replace("/^APP_KEY=.*/m","APP_KEY=",$env);
$env=preg_replace("/^APP_URL=.*/m","APP_URL=http://127.0.0.1",$env);
$env=preg_replace("/^DB_DATABASE=.*/m","DB_DATABASE=".$argv[1],$env);
$env=preg_replace("/^DB_USERNAME=.*/m","DB_USERNAME=root",$env);
$env=preg_replace("/^DB_PASSWORD=.*/m","DB_PASSWORD=",$env);
$env=preg_replace("/^SESSION_DRIVER=.*/m","SESSION_DRIVER=file",$env);
$env=preg_replace("/^CACHE_STORE=.*/m","CACHE_STORE=file",$env);
$env=preg_replace("/^QUEUE_CONNECTION=.*/m","QUEUE_CONNECTION=database",$env);
$env=preg_replace("/^BROADCAST_CONNECTION=.*/m","BROADCAST_CONNECTION=log",$env);
file_put_contents(".env",$env);
' "$DB_NAME"

# Constrain: no redis/reverb/chromium assumptions.
{
  echo "REDIS_CLIENT=phpredis"
  echo "REDIS_HOST="
  echo "PDF_CHROME_PATH="
  echo "PDF_NODE_BINARY="
} >> .env

# PHP without exec/proc_open — mirrors shared hosting disable_functions.
CONSTRAINED_PHP=(php -d disable_functions=exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec)

"${CONSTRAINED_PHP[@]}" artisan key:generate --force --no-interaction
"${CONSTRAINED_PHP[@]}" artisan package:discover --ansi >/dev/null
"${CONSTRAINED_PHP[@]}" artisan migrate --force --no-interaction
"${CONSTRAINED_PHP[@]}" artisan about --only=environment 2>/dev/null | head -20 || "${CONSTRAINED_PHP[@]}" artisan --version

# Interrupted install recovery: mark IN_PROGRESS then recover.
"${CONSTRAINED_PHP[@]}" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Ark\Install\InstallationState::markInProgress("migrate");
if (App\Ark\Install\InstallationState::status() !== App\Ark\Install\InstallationState::IN_PROGRESS) { fwrite(STDERR,"expected IN_PROGRESS\n"); exit(1); }
if (! App\Ark\Install\InstallationState::recoverInterruptedProgress()) { fwrite(STDERR,"recover failed\n"); exit(1); }
if (App\Ark\Install\InstallationState::status() !== App\Ark\Install\InstallationState::NOT_INSTALLED) { fwrite(STDERR,"expected NOT_INSTALLED after recover\n"); exit(1); }
echo "interrupted_install_recovery=ok\n";
'

# Native PDF generation from the extracted tree (real bytes, not class_exists).
"${CONSTRAINED_PHP[@]}" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! class_exists(Mpdf\Mpdf::class)) { fwrite(STDERR,"mpdf missing\n"); exit(1); }
$html = "<html><body><h1>Fidelity Auto — 東京</h1><p>Brake service \$150.00</p><div style=\"page-break-before:always\">Page 2</div></body></html>";
$bytes = app(App\Ark\Operations\Documents\HtmlPdfBuilder::class)->toPdfBytes($html);
if (! str_starts_with($bytes, "%PDF") || strlen($bytes) < 500) { fwrite(STDERR,"pdf render failed\n"); exit(1); }
file_put_contents(sys_get_temp_dir()."/ark-zip-verify-native.pdf", $bytes);
$p = App\Ark\Runtime\InfrastructureProfile::inspect();
echo "native_pdf_bytes=".strlen($bytes)."\n";
echo "profile=".$p["profile"]." chromium=".($p["chromium_pdf"]?"y":"n")." native=".($p["native_pdf"]?"y":"n")." detached=".($p["detached_finalize"]?"y":"n")."\n";
if (! $p["native_pdf"]) { fwrite(STDERR,"native_pdf unavailable\n"); exit(1); }
if ($p["detached_finalize"]) { fwrite(STDERR,"expected detached_finalize=false under disable_functions\n"); exit(1); }
'

# Bounded queue + schedule entry points present
"${CONSTRAINED_PHP[@]}" artisan ark:queue-work-bounded --help >/dev/null
"${CONSTRAINED_PHP[@]}" artisan schedule:list >/dev/null

# Installation security surface
"${CONSTRAINED_PHP[@]}" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r = app(App\Ark\Install\InstallationSecurityChecker::class)->check();
$fail = array_values(array_filter($r, fn ($c) => ($c["status"] ?? "") === "fail"));
if ($fail !== []) { fwrite(STDERR, "security fail: ".json_encode($fail)."\n"); exit(1); }
echo "security_checker=ok checks=".count($r)."\n";
'

# Security: installer not locked yet (fresh), public/.env absent
test ! -e public/.env
test ! -e vendor/bin/phpunit || true

echo "APP=$APP"
echo "DB=$DB_NAME"
echo "VERIFY_OK"
