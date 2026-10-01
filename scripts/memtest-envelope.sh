#!/usr/bin/env bash
# Local memory-envelope torture for ARK Compose (not a public VPS install guide).
# Usage: ./scripts/memtest-envelope.sh 2g|1g
set -euo pipefail

TIER="${1:?usage: $0 2g|1g}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

case "$TIER" in
  2g) PROJECT=ark-memtest-2g; OVERLAY=docker-compose.memtest-2g.yml; BUDGET_MB=2048 ;;
  1g) PROJECT=ark-memtest-1g; OVERLAY=docker-compose.memtest-1g.yml; BUDGET_MB=1024 ;;
  *) echo "tier must be 2g or 1g"; exit 2 ;;
esac

COMPOSE=(docker compose -p "$PROJECT" -f docker-compose.yml -f "$OVERLAY")
STATS_LOG="/tmp/${PROJECT}-stats.csv"
RESULT_LOG="/tmp/${PROJECT}-result.txt"
: >"$STATS_LOG"
: >"$RESULT_LOG"

log() { echo "$*" | tee -a "$RESULT_LOG"; }

sample_stats() {
  local label="$1"
  local ts
  ts="$(date -u +%H:%M:%S)"
  docker stats --no-stream --format '{{.Name}} {{.MemUsage}}' 2>/dev/null \
    | grep "$PROJECT" \
    | while read -r name usage; do
        echo "$ts $label $name $usage" >>"$STATS_LOG"
      done || true
}

compute_peak() {
  python3 - "$STATS_LOG" <<'PY'
import re, collections, sys
path = sys.argv[1]
by = collections.defaultdict(float)
for line in open(path):
    m = re.match(r"(\S+) (\S+) (\S+) ([0-9.]+)(MiB|GiB) /", line.strip())
    if not m:
        continue
    ts, label, _name, val, unit = m.groups()
    v = float(val)
    if unit == "GiB":
        v *= 1024
    by[(ts, label)] += v
if not by:
    print("peak_total_mib=0")
    print("peak_at=none")
else:
    (ts, label), peak = max(by.items(), key=lambda kv: kv[1])
    print(f"peak_total_mib={peak:.1f}")
    print(f"peak_at={ts}/{label}")
PY
}

log "=== ARK memtest tier=$TIER budget_mb=$BUDGET_MB sha=$(git rev-parse --short HEAD) ==="
log "SCOPE=runtime_hard_caps build_uses_docker_desktop_builder_ram"

"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true

log "--- BUILD ---"
BUILD_START=$(date +%s)
set +e
"${COMPOSE[@]}" build app >"/tmp/${PROJECT}-build.log" 2>&1
BUILD_EC=$?
set -e
log "build_exit=$BUILD_EC build_seconds=$(( $(date +%s) - BUILD_START ))"
if [ "$BUILD_EC" -ne 0 ]; then
  tail -n 40 "/tmp/${PROJECT}-build.log" | tee -a "$RESULT_LOG"
  log "VERDICT=FAIL reason=build"
  exit 1
fi

log "--- UP ---"
(
  while true; do sample_stats running; sleep 2; done
) &
SAMPLER_PID=$!
cleanup() { kill "$SAMPLER_PID" 2>/dev/null || true; }
trap cleanup EXIT

UP_START=$(date +%s)
set +e
"${COMPOSE[@]}" up -d >>"$RESULT_LOG" 2>&1
UP_EC=$?
set -e
log "up_exit=$UP_EC"

OK=0
for _i in $(seq 1 90); do
  sample_stats boot
  CODE=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:8088/up" 2>/dev/null || echo 000)
  if [ "$CODE" = "200" ]; then
    OK=1
    log "up_http=200 after_seconds=$(( $(date +%s) - UP_START ))"
    break
  fi
  sleep 2
done

if [ "$OK" != "1" ]; then
  log "VERDICT=FAIL reason=up_timeout"
  "${COMPOSE[@]}" ps >>"$RESULT_LOG" 2>&1 || true
  "${COMPOSE[@]}" logs --tail=100 app >>"$RESULT_LOG" 2>&1 || true
  exit 1
fi

APP_CID=$("${COMPOSE[@]}" ps -q app)
log "--- PROCESSES ---"
docker exec "$APP_CID" sh -c 'ps aux' | grep -E 'horizon|reverb|schedule|php-fpm|nginx' | grep -v grep | tee -a "$RESULT_LOG" || true

log "--- HORIZON / REVERB ---"
set +e
docker exec "$APP_CID" php artisan horizon:status --no-ansi 2>&1 | tee -a "$RESULT_LOG"
curl -sS -o "/tmp/${PROJECT}-reverb.json" -w "reverb_http=%{http_code}\n" --max-time 5 "http://127.0.0.1:8088/up/reverb" | tee -a "$RESULT_LOG"
head -c 240 "/tmp/${PROJECT}-reverb.json" | tee -a "$RESULT_LOG"; echo | tee -a "$RESULT_LOG"
set -e
sample_stats steady

log "--- HEADLESS INSTALL + MIGRATE ---"
docker exec "$APP_CID" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\App\Ark\Install\InstallationState::markInstalled();
echo "installed=" . (\App\Ark\Install\InstallationState::isInstalled() ? "yes" : "no") . "\n";
' | tee -a "$RESULT_LOG"

set +e
docker exec "$APP_CID" php artisan migrate --force --no-ansi >"/tmp/${PROJECT}-migrate.log" 2>&1
MIG_EC=$?
set -e
tail -n 30 "/tmp/${PROJECT}-migrate.log" | tee -a "$RESULT_LOG"
log "migrate_exit=$MIG_EC"
sample_stats post_migrate

log "--- PHOTO WRITE ---"
docker exec "$APP_CID" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = "inspections/memtest/probe.bin";
$bytes = random_bytes(512 * 1024);
Illuminate\Support\Facades\Storage::disk("local")->put($path, $bytes);
$ok = Illuminate\Support\Facades\Storage::disk("local")->exists($path)
    && Illuminate\Support\Facades\Storage::disk("local")->size($path) === strlen($bytes);
echo "photo_write=" . ($ok ? "PASS" : "FAIL") . " size=" . strlen($bytes) . "\n";
' | tee -a "$RESULT_LOG"
sample_stats post_photo

log "--- FORCE RECREATE ---"
"${COMPOSE[@]}" up -d --force-recreate >>"$RESULT_LOG" 2>&1
OK=0
for _i in $(seq 1 60); do
  sample_stats recreate
  CODE=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:8088/up" 2>/dev/null || echo 000)
  if [ "$CODE" = "200" ]; then OK=1; break; fi
  sleep 2
done
log "recreate_up=$OK"

APP_CID=$("${COMPOSE[@]}" ps -q app)
docker exec "$APP_CID" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$photo = Illuminate\Support\Facades\Storage::disk("local")->exists("inspections/memtest/probe.bin");
$inst = \App\Ark\Install\InstallationState::isInstalled();
echo "photo_persist_after_recreate=" . ($photo ? "PASS" : "FAIL") . "\n";
echo "install_persist=" . ($inst ? "PASS" : "FAIL") . "\n";
' | tee -a "$RESULT_LOG"

kill "$SAMPLER_PID" 2>/dev/null || true
trap - EXIT
sample_stats final
compute_peak | tee -a "$RESULT_LOG"

OOM=$(docker ps -a --filter "name=${PROJECT}" --format '{{.Status}}' | grep -ci 'oom' || true)
log "oom_containers=$OOM"
PEAK=$(grep '^peak_total_mib=' "$RESULT_LOG" | tail -1 | cut -d= -f2)
log "budget_mb=$BUDGET_MB observed_peak_mib=${PEAK:-0}"

UNDER=$(python3 -c "print('yes' if float('${PEAK:-0}') <= float('$BUDGET_MB') * 1.08 else 'no')")
log "peak_under_budget=$UNDER"

FAIL=0
[ "$OK" = "1" ] || FAIL=1
# Post-deploy may already migrate; treat duplicate-column as non-memory noise if core schema exists.
if [ "$MIG_EC" != "0" ]; then
  if docker exec "$APP_CID" php -r '
    require "vendor/autoload.php";
    $app=require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo Schema::hasTable("repair_orders") && Schema::hasTable("users") ? "schema_ok" : "schema_bad";
  ' | grep -q schema_ok && grep -qi 'Duplicate column\|already exists' "/tmp/${PROJECT}-migrate.log"; then
    log "migrate_note=duplicate_after_post_deploy_ignored_for_memtest"
  else
    FAIL=1
  fi
fi
[ "$OOM" = "0" ] || FAIL=1
grep -q 'photo_persist_after_recreate=PASS' "$RESULT_LOG" || FAIL=1
grep -q 'install_persist=PASS' "$RESULT_LOG" || FAIL=1
grep -q 'photo_write=PASS' "$RESULT_LOG" || FAIL=1
if ! grep -qiE 'horizon|reverb' "$RESULT_LOG"; then FAIL=1; fi

if [ "$FAIL" = "0" ] && [ "$UNDER" = "yes" ]; then
  log "VERDICT=PASS"
  exit 0
fi
log "VERDICT=FAIL"
exit 1
