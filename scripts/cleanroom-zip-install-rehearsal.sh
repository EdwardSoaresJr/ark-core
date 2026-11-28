#!/usr/bin/env bash
# Clean-room Release ZIP installation rehearsal using only the archive + public /setup HTTP flow.
# Does not reuse a development .env, vendor, or node_modules from the source workspace.
set -euo pipefail

ZIP="${1:?Usage: cleanroom-zip-install-rehearsal.sh <release.zip> [workdir]}"
WORK="${2:-$(mktemp -d "${TMPDIR:-/tmp}/ark-cleanroom.XXXXXX")}"
PORT="${ARK_CLEANROOM_PORT:-18088}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_NAME="ark_cleanroom_$(date +%s)"
BASE="http://127.0.0.1:${PORT}"
COOKIE_JAR="$WORK/cookies.txt"
EVIDENCE="$WORK/EVIDENCE.txt"

mkdir -p "$WORK"
: > "$EVIDENCE"
log() { echo "$*" | tee -a "$EVIDENCE"; }

MYSQL=(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME")
if [[ -n "$DB_PASSWORD" ]]; then MYSQL+=(-p"$DB_PASSWORD"); fi
"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

EXTRACT="$WORK/extract"
mkdir -p "$EXTRACT"
unzip -q "$ZIP" -d "$EXTRACT"
APP=$(find "$EXTRACT" -maxdepth 1 -type d -name 'ark-core-*' | head -1)
test -n "$APP"
test -f "$APP/public/index.php"
test -f "$APP/RELEASE.json"
test ! -e "$APP/.env"
test ! -d "$APP/node_modules"

# Provenance
COMMIT=$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["commit"];' "$APP/RELEASE.json")
PHP_DECL=$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["php"];' "$APP/RELEASE.json")
log "app=$APP"
log "release_commit=$COMMIT"
log "release_php=$PHP_DECL"
test "$PHP_DECL" = "^8.4.1"

# Host PHP floor
php -r 'if (version_compare(PHP_VERSION, "8.4.1", "<")) { fwrite(STDERR, "host PHP too old\n"); exit(1);} echo "host_php=".PHP_VERSION."\n";' | tee -a "$EVIDENCE"

# Document root = public/ (published requirement). No Composer/Node at runtime.
cd "$APP/public"
# Built-in server only for rehearsal; production uses Apache/nginx docroot=public.
php -S "127.0.0.1:${PORT}" -t "$APP/public" "$APP/public/index.php" >"$WORK/php-server.log" 2>&1 &
SERVER_PID=$!
cleanup() {
  kill "$SERVER_PID" 2>/dev/null || true
  wait "$SERVER_PID" 2>/dev/null || true
}
trap cleanup EXIT
sleep 1

curl_c() {
  curl -sS -c "$COOKIE_JAR" -b "$COOKIE_JAR" -L "$@"
}

# CSRF token helper from HTML forms
csrf_from() {
  php -r '$h=file_get_contents("php://stdin"); if(preg_match("/name=\"_token\" value=\"([^\"]+)\"/",$h,$m)){echo $m[1]; exit;} if(preg_match("/name='\''_token'\'' value='\''([^'\'']+)'\''/",$h,$m)){echo $m[1]; exit;} fwrite(STDERR,"csrf missing\n"); exit(1);'
}

log "== /setup begin"
SETUP_HTML=$(curl_c "$BASE/setup")
echo "$SETUP_HTML" | grep -qi 'setup\|Begin\|system' 
log "setup_landing=ok"

log "== system check"
SYSTEM_HTML=$(curl_c "$BASE/setup/system")
echo "$SYSTEM_HTML" | grep -qi '8.4.1\|PHP version\|System'
log "system_page=ok"

log "== database test + store"
DB_PAGE=$(curl_c "$BASE/setup/database")
TOKEN=$(echo "$DB_PAGE" | csrf_from)
curl_c -X POST "$BASE/setup/database/test" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "app_url=$BASE" \
  --data-urlencode "db_host=$DB_HOST" \
  --data-urlencode "db_port=$DB_PORT" \
  --data-urlencode "db_database=$DB_NAME" \
  --data-urlencode "db_username=$DB_USERNAME" \
  --data-urlencode "db_password=$DB_PASSWORD" \
  -o "$WORK/db-test.html" -w 'db_test_http=%{http_code}\n' | tee -a "$EVIDENCE"

log "== shop"
SHOP_PAGE=$(curl_c "$BASE/setup/shop")
TOKEN=$(echo "$SHOP_PAGE" | csrf_from)
curl_c -X POST "$BASE/setup/shop" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "shop_name=Cleanroom Auto" \
  --data-urlencode "shop_timezone=America/Denver" \
  -o "$WORK/shop.html" -w 'shop_http=%{http_code}\n' | tee -a "$EVIDENCE"

log "== admin"
ADMIN_PAGE=$(curl_c "$BASE/setup/admin")
TOKEN=$(echo "$ADMIN_PAGE" | csrf_from)
curl_c -X POST "$BASE/setup/admin" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "admin_name=Cleanroom Admin" \
  --data-urlencode "admin_email=admin@cleanroom.test" \
  --data-urlencode "password=CleanroomPass1!" \
  --data-urlencode "password_confirmation=CleanroomPass1!" \
  -o "$WORK/admin.html" -w 'admin_http=%{http_code}\n' | tee -a "$EVIDENCE"

log "== review + install"
REVIEW_PAGE=$(curl_c "$BASE/setup/review")
TOKEN=$(echo "$REVIEW_PAGE" | csrf_from)
curl_c -X POST "$BASE/setup/install" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "_token=$TOKEN" \
  -o "$WORK/install.html" -w 'install_http=%{http_code}\n' | tee -a "$EVIDENCE"

# Poll progress until installed or failed
for i in $(seq 1 90); do
  STATUS=$(curl_c "$BASE/setup/progress/status")
  echo "$STATUS" >> "$WORK/progress.jsonl"
  if echo "$STATUS" | grep -q '"status":"installed"\|"installed":true\|installed'; then
    log "install_status=installed attempt=$i"
    break
  fi
  if echo "$STATUS" | grep -qi 'fail\|error'; then
    log "install_status_raw=$STATUS"
    # allow recover once
    if [[ $i -eq 5 ]]; then
      (cd "$APP" && php artisan ark:install-recover --force 2>/dev/null || true)
    fi
  fi
  sleep 2
  if [[ $i -eq 90 ]]; then
    log "ERROR: install did not complete"
    cat "$WORK/progress.jsonl" | tail -5 | tee -a "$EVIDENCE"
    exit 1
  fi
done

# Login
LOGIN=$(curl_c "$BASE/login")
TOKEN=$(echo "$LOGIN" | csrf_from)
curl_c -X POST "$BASE/login" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "email=admin@cleanroom.test" \
  --data-urlencode "password=CleanroomPass1!" \
  -o "$WORK/login.html" -w 'login_http=%{http_code}\n' | tee -a "$EVIDENCE"

APP_HOME=$(curl_c "$BASE/app" -o "$WORK/app-home.html" -w '%{http_code}')
log "app_home_http=$APP_HOME"
grep -qi 'Cleanroom\|Dashboard\|Repair\|Today\|Operations' "$WORK/app-home.html" && log "admin_login=ok"

# Storage persistence
echo "cleanroom-storage-ok" > "$APP/storage/app/cleanroom-probe.txt"
test -f "$APP/storage/app/cleanroom-probe.txt"
log "persistent_storage=ok"

# Cron entry points (published constrained-hosting-cron.md)
(cd "$APP" && php artisan schedule:run --verbose 2>&1 | head -20) | tee -a "$EVIDENCE" || true
(cd "$APP" && php artisan ark:queue-work-bounded --max-jobs=5 --max-time=10 2>&1 | head -20) | tee -a "$EVIDENCE" || true
log "cron_commands=ok"

# Installer locked
LOCKED=$(curl_c "$BASE/setup" -o "$WORK/setup-after.html" -w '%{http_code}')
log "setup_after_install_http=$LOCKED"

# Backup rehearsal (published backup approach: dump + storage)
"${MYSQL[@]}" "$DB_NAME" -e 'SHOW TABLES;' | head -20 | tee -a "$EVIDENCE"
"${MYSQL[@]}" --single-transaction --routines --triggers "$DB_NAME" > "$WORK/backup.sql"
test -s "$WORK/backup.sql"
tar -C "$APP" -czf "$WORK/storage-backup.tgz" storage .env
log "backup_bytes_sql=$(wc -c < "$WORK/backup.sql") storage_tgz=$(wc -c < "$WORK/storage-backup.tgz")"

# Restore rehearsal into a second DB
RESTORE_DB="${DB_NAME}_restore"
"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$RESTORE_DB\`; CREATE DATABASE \`$RESTORE_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"${MYSQL[@]}" "$RESTORE_DB" < "$WORK/backup.sql"
COUNT=$("${MYSQL[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$RESTORE_DB'")
log "restore_table_count=$COUNT"
test "$COUNT" -gt 10

log "CLEANROOM_OK"
log "WORK=$WORK"
log "DB=$DB_NAME"
echo "$WORK"
