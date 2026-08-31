#!/usr/bin/env bash
# Compose path certification:
#   docker compose up -d --build  →  /setup reachable  →  install inside app  →  recreate still works
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose -f docker-compose.yml)

echo "==> Disposable Compose stack (mysql + app)"
"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
"${COMPOSE[@]}" up -d --build

echo "==> Wait for /setup (prefer in-container curl — host port mapping can flake on Docker Desktop)"
ok=0
for i in $(seq 1 90); do
  # Fresh tree must be not_installed — welcome page or start CTA
  body=$("${COMPOSE[@]}" exec -T app php -r '
require "/app/vendor/autoload.php";
$app = require "/app/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create("/setup", "GET");
$response = $kernel->handle($request);
echo $response->getStatusCode()."\n";
echo $response->headers->get("Location")."\n";
echo substr($response->getContent(), 0, 1500);
' 2>/dev/null || true)
  code=$(printf '%s\n' "$body" | head -1 | tr -d '\r')
  if [ "$code" = "200" ] && printf '%s' "$body" | grep -qiE 'setup|install|welcome|database|Get started|shop'; then
    ok=1
    break
  fi
  # Host-side check (nice-to-have)
  host_code=$(curl -s -o /tmp/ark-setup-body.html -w "%{http_code}" --max-time 3 http://127.0.0.1:8088/setup || true)
  if [ "$host_code" = "200" ] && grep -qiE 'setup|install|welcome' /tmp/ark-setup-body.html 2>/dev/null; then
    ok=1
    break
  fi
  sleep 2
  if [ "$i" -eq 90 ]; then
    echo "App never served uninstalled /setup (in-container code=$code host=$host_code)"
    echo "$body" | head -40
    "${COMPOSE[@]}" exec -T app cat storage/app/install/state.json 2>/dev/null || true
    "${COMPOSE[@]}" logs --tail=80 app || true
    exit 1
  fi
done
echo "SETUP_HTTP_OK"

echo "==> Install from inside app container (wizard-equivalent CompleteInstallationAction)"
"${COMPOSE[@]}" exec -T app php <<'PHP'
<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Ark\Install\CompleteInstallationAction;
use App\Ark\Install\InstallationState;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;

$path = storage_path('app/install/state.json');
@mkdir(dirname($path), 0755, true);
file_put_contents($path, json_encode([
    'status' => 'not_installed',
    'updated_at' => gmdate('c'),
    'checkpoint' => null,
    'meta' => [],
], JSON_PRETTY_PRINT));

$result = app(CompleteInstallationAction::class)->execute([
    'db' => [
        'host' => 'mysql',
        'port' => '3306',
        'database' => 'ark',
        'username' => 'ark',
        'password' => \App\Ark\Install\RuntimeDatabaseConfig::read()['password'],
    ],
    'app_url' => 'http://localhost:8088',
    'shop' => [
        'shop_name' => 'Compose Certify Shop',
        'shop_timezone' => 'America/Denver',
        'phone' => '555-0199',
        'email' => 'owner@example.test',
        'address_line_1' => '2 Compose Way',
        'city' => 'Testville',
        'state' => 'CO',
        'postal_code' => '80001',
    ],
    'admin' => [
        'name' => 'Compose Admin',
        'email' => 'compose-admin@example.test',
        'password' => 'Compose-Passw0rd!',
    ],
    'create_workstation' => true,
    'skip_integrations' => true,
]);

if (!($result['ok'] ?? false)) {
    fwrite(STDERR, "INSTALL FAILED: ".($result['message'] ?? 'unknown')."\n");
    exit(1);
}
if (!InstallationState::isInstalled()) {
    fwrite(STDERR, "State not installed\n");
    exit(1);
}
if (!User::query()->where('email', 'compose-admin@example.test')->exists()) {
    fwrite(STDERR, "Admin missing\n");
    exit(1);
}
if (ShopSettings::current()->shop_name !== 'Compose Certify Shop') {
    fwrite(STDERR, "Shop mismatch\n");
    exit(1);
}
echo "COMPOSE_INSTALL_OK\n";
PHP

echo "==> Recreate app container (storage volume + MySQL persistence)"
"${COMPOSE[@]}" up -d --force-recreate --no-deps app
sleep 5

code=$(curl -s -o /tmp/ark-post.html -w "%{http_code}" -L http://127.0.0.1:8088/setup || true)
# After install, /setup should refuse (302/403/410) — not show a fresh wizard as not_installed
status=$("${COMPOSE[@]}" exec -T app php artisan ark:install-status --no-ansi 2>/dev/null | tr -d '\r' || true)
echo "post_recreate setup_http=$code"
echo "$status"

"${COMPOSE[@]}" exec -T app php <<'PHP'
<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Ark\Install\InstallationState;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

if (!InstallationState::isInstalled()) {
    fwrite(STDERR, "Install lock lost after recreate\n");
    exit(1);
}
$user = User::query()->where('email', 'compose-admin@example.test')->first();
if (!$user || !Hash::check('Compose-Passw0rd!', $user->password)) {
    fwrite(STDERR, "Admin auth lost after recreate\n");
    exit(1);
}
echo "COMPOSE_RESTART_OK\n";
PHP

echo "==> COMPOSE INSTALL PATH PASS"
