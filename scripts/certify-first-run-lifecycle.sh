#!/usr/bin/env bash
# Disposable Compose certification: first-run must NOT run installed-instance post-deploy.
#
# Proves: fresh volumes → boot → /setup usable → DB not polluted by migrate
#
# Usage (destructive to THIS Compose project name only):
#   ./scripts/certify-first-run-lifecycle.sh
#
# Never points at a live production shop / production volumes.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PROJECT="${ARK_LIFECYCLE_PROJECT:-ark-lifecycle-cert}"
COMPOSE=(docker compose -p "$PROJECT" -f docker-compose.yml)
export ARK_DOMAIN="${ARK_DOMAIN:-lifecycle.example.test}"
export ARK_ADMIN_EMAIL="${ARK_ADMIN_EMAIL:-lifecycle@example.test}"

cleanup() {
  "${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "=== ARK first-run lifecycle cert (project=${PROJECT}) ==="
cleanup

echo "--- building / starting fresh volumes ---"
"${COMPOSE[@]}" up -d --build --wait

APP_CID="$("${COMPOSE[@]}" ps -q app)"
MYSQL_CID="$("${COMPOSE[@]}" ps -q mysql)"
test -n "$APP_CID"
test -n "$MYSQL_CID"

echo "--- wait for install-status (file-backed) ---"
for _ in $(seq 1 90); do
  if docker exec "$APP_CID" php artisan ark:install-status -q >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

STATUS_OUT="$(docker exec "$APP_CID" php artisan ark:install-status 2>/dev/null || true)"
echo "$STATUS_OUT"
echo "$STATUS_OUT" | grep -q 'status=not_installed'
echo "$STATUS_OUT" | grep -q 'installed=no'

set +e
docker exec "$APP_CID" php artisan ark:install-status --check-installed -q
CHECK_EC=$?
set -e
test "$CHECK_EC" -eq 1

echo "--- entrypoint must have skipped post-deploy ---"
sleep 3
LOG="$(docker exec "$APP_CID" sh -c 'tail -n 80 /app/storage/logs/ark-post-deploy.log 2>/dev/null || true')"
if echo "$LOG" | grep -q 'running migrations'; then
  echo "FAIL: post-deploy migrated on first-run"
  echo "$LOG"
  exit 1
fi

DLOG="$(docker logs "$APP_CID" 2>&1 | tail -n 120 || true)"
echo "$DLOG" | grep -q 'first-run setup pending' || echo "$DLOG" | grep -q 'post-deploy skipped' || {
  echo "WARN: did not see first-run skip phrase in docker logs (continuing with DB proof)"
  echo "$DLOG" | tail -n 40
}

echo "--- DB must remain free of ARK application schema ---"
TABLES="$(docker exec "$MYSQL_CID" mysql -uark -park -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='ark';" 2>/dev/null || echo fail)"
echo "ark_table_count=${TABLES}"
test "$TABLES" = "0"

echo "--- /setup must be reachable ---"
CODE="$(curl -s -o /tmp/ark-lifecycle-setup.html -w '%{http_code}' http://127.0.0.1:8088/setup || echo 000)"
echo "setup_http=${CODE}"
test "$CODE" = "200"
grep -qi 'database\|welcome\|setup' /tmp/ark-lifecycle-setup.html

if grep -qi 'already looks like an ARK installation' /tmp/ark-lifecycle-setup.html; then
  echo "FAIL: /setup claims existing ARK schema on fresh volume"
  exit 1
fi

echo "--- APP_KEY persistence across recreate (still uninstalled) ---"
KEY1="$(docker exec "$APP_CID" sh -c 'test -s /app/storage/app/install/app_key && sha256sum /app/storage/app/install/app_key | cut -d" " -f1')"
test -n "$KEY1"
"${COMPOSE[@]}" up -d --force-recreate --no-deps app
sleep 10
APP_CID="$("${COMPOSE[@]}" ps -q app)"
KEY2="$(docker exec "$APP_CID" sh -c 'test -s /app/storage/app/install/app_key && sha256sum /app/storage/app/install/app_key | cut -d" " -f1')"
test "$KEY1" = "$KEY2"
set +e
docker exec "$APP_CID" php artisan ark:install-status --check-installed -q
CHECK_EC=$?
set -e
test "$CHECK_EC" -eq 1

echo "--- mark installed + recreate must launch post-deploy ---"
docker exec "$APP_CID" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Ark\Install\InstallationState::markInstalled();
echo App\Ark\Install\InstallationState::isInstalled() ? "marked\n" : "fail\n";
' | grep -q marked

"${COMPOSE[@]}" up -d --force-recreate --no-deps app
sleep 15
APP_CID="$("${COMPOSE[@]}" ps -q app)"

for _ in $(seq 1 90); do
  LOG="$(docker exec "$APP_CID" sh -c 'cat /app/storage/logs/ark-post-deploy.log 2>/dev/null || true')"
  if echo "$LOG" | grep -Eq 'running migrations|complete'; then
    echo "post-deploy log observed"
    break
  fi
  sleep 2
done
LOG="$(docker exec "$APP_CID" sh -c 'cat /app/storage/logs/ark-post-deploy.log 2>/dev/null || true')"
echo "$LOG" | tail -n 40
echo "$LOG" | grep -Eq 'running migrations|ARK installed|complete' || {
  echo "FAIL: installed recreate did not run post-deploy"
  docker logs "$APP_CID" 2>&1 | tail -n 80
  exit 1
}

KEY3="$(docker exec "$APP_CID" sh -c 'sha256sum /app/storage/app/install/app_key | cut -d" " -f1')"
test "$KEY1" = "$KEY3"

echo "PASS: first-run lifecycle boundary holds (installer birth / post-deploy upgrades)"
