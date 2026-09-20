#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PROCESSES="${TEST_PROCESSES:-8}"

php artisan config:clear --ansi --no-interaction >/dev/null

exec php -d memory_limit=768M vendor/bin/pest \
  --parallel \
  "--processes=${PROCESSES}" \
  --no-coverage \
  "$@"
