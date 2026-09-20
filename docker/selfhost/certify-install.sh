#!/usr/bin/env bash
# Disposable MySQL + installer E2E certification (host PHP against Compose MySQL).
# Does not publish anything. Destroys only the compose volumes for this project.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose -f docker-compose.yml)
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3307
MYSQL_DB=ark
MYSQL_USER=ark

read_db_password() {
  docker compose exec -T mysql sh -c 'grep ^DB_PASSWORD= /run/ark/secrets/install.env | cut -d= -f2- | tr -d "\""'
}

echo "==> Reset compose volumes (disposable)"
"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
"${COMPOSE[@]}" up -d mysql

echo "==> Wait for MySQL"
for i in $(seq 1 60); do
  if docker compose exec -T mysql /bin/sh /ark/mysql-healthcheck.sh >/dev/null 2>&1; then
    break
  fi
  sleep 2
  if [ "$i" -eq 60 ]; then
    echo "MySQL failed to become healthy"
    exit 1
  fi
done

MYSQL_PASS="$(read_db_password)"
export MYSQL_HOST MYSQL_PORT MYSQL_DB MYSQL_USER MYSQL_PASS

# Ensure empty
TABLES=$(docker compose exec -T mysql sh -c 'set -a; . /run/ark/secrets/install.env; set +a; mysql -N -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SHOW TABLES;"' 2>/dev/null | wc -l | tr -d ' ')
if [ "${TABLES}" != "0" ]; then
  echo "Database not empty after fresh volume — abort"
  exit 1
fi

echo "==> Reset install state on host tree"
rm -f storage/app/install/state.json storage/app/install/draft.json storage/app/install/install.lock storage/app/install/app_key
mkdir -p storage/app/install storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache

# Minimal .env for host-driven install (writable traditional mode)
if [ ! -f .env ]; then
  cp .env.example .env
fi
php artisan key:generate --force --no-interaction >/dev/null

# Force not installed (file write — do not call resetForTests outside testing)
php -r '
$file = "storage/app/install/state.json";
@mkdir("storage/app/install", 0755, true);
file_put_contents($file, json_encode(["status"=>"not_installed","updated_at"=>gmdate("c"),"checkpoint"=>null,"meta"=>[]], JSON_PRETTY_PRINT));
'

export APP_ENV=local
export MYSQL_HOST MYSQL_PORT MYSQL_DB MYSQL_USER MYSQL_PASS

php <<'PHP'
<?php
$_SERVER['APP_ENV'] = 'local';
putenv('APP_ENV=local');
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Ark\Install\CompleteInstallationAction;
use App\Ark\Install\InstallationState;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Ensure not installed
$path = storage_path('app/install/state.json');
file_put_contents($path, json_encode([
    'status' => 'not_installed',
    'updated_at' => gmdate('c'),
    'checkpoint' => null,
    'meta' => [],
], JSON_PRETTY_PRINT));

$result = app(CompleteInstallationAction::class)->execute([
    'db' => [
        'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
        'port' => getenv('MYSQL_PORT') ?: '3307',
        'database' => getenv('MYSQL_DB') ?: 'ark',
        'username' => getenv('MYSQL_USER') ?: 'ark',
        'password' => getenv('MYSQL_PASS') ?: 'ark',
    ],
    'app_url' => 'http://localhost:8080',
    'shop' => [
        'shop_name' => 'Certify Auto',
        'shop_timezone' => 'America/Denver',
        'phone' => '555-0100',
        'email' => 'owner@example.test',
        'address_line_1' => '1 Test Street',
        'city' => 'Testville',
        'state' => 'CO',
        'postal_code' => '80000',
    ],
    'admin' => [
        'name' => 'Cert Admin',
        'email' => 'admin@example.test',
        'password' => 'Certify-Passw0rd!',
    ],
    'create_workstation' => true,
    'skip_integrations' => true,
]);

if (!($result['ok'] ?? false)) {
    fwrite(STDERR, "INSTALL FAILED: ".($result['message'] ?? 'unknown')." checkpoint=".($result['checkpoint'] ?? '')."\n");
    exit(1);
}

if (!InstallationState::isInstalled()) {
    fwrite(STDERR, "State not installed after success\n");
    exit(1);
}

$user = User::query()->where('email', 'admin@example.test')->first();
if (!$user || !$user->is_master_admin) {
    fwrite(STDERR, "Admin missing\n");
    exit(1);
}

$shop = ShopSettings::current();
if ($shop->shop_name !== 'Certify Auto') {
    fwrite(STDERR, "Shop name mismatch\n");
    exit(1);
}

echo "INSTALL_OK\n";
PHP

echo "==> Restart MySQL container (persistence check)"
"${COMPOSE[@]}" restart mysql
sleep 8
for i in $(seq 1 30); do
  if docker compose exec -T mysql /bin/sh /ark/mysql-healthcheck.sh >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

php <<'PHP'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Ark\Install\InstallationState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
    'database.connections.mysql.port' => getenv('MYSQL_PORT') ?: '3307',
    'database.connections.mysql.database' => getenv('MYSQL_DB') ?: 'ark',
    'database.connections.mysql.username' => getenv('MYSQL_USER') ?: 'ark',
    'database.connections.mysql.password' => getenv('MYSQL_PASS') ?: 'ark',
]);
DB::purge('mysql');
DB::reconnect('mysql');

if (!InstallationState::isInstalled()) {
    fwrite(STDERR, "Install state lost after restart\n");
    exit(1);
}
$user = User::query()->where('email', 'admin@example.test')->first();
if (!$user || !Hash::check('Certify-Passw0rd!', $user->password)) {
    fwrite(STDERR, "Admin auth lost after restart\n");
    exit(1);
}
$count = DB::table('migrations')->count();
if ($count < 1) {
    fwrite(STDERR, "Migrations missing after restart\n");
    exit(1);
}
echo "RESTART_OK migrations={$count}\n";
PHP

echo "==> CERTIFICATION PASS (host installer → disposable MySQL → restart)"
