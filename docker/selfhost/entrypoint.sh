#!/bin/sh
set -eu

mkdir -p \
  storage/app/install \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

ENV_PERSIST=storage/app/install/dotenv
KEY_FILE=storage/app/install/app_key

# Restore .env across container recreate (install writes live here).
if [ -f "$ENV_PERSIST" ]; then
  cp "$ENV_PERSIST" .env
elif [ ! -f .env ]; then
  cp .env.example .env
fi

upsert_env() {
  key="$1"
  value="$2"
  tmp="$(mktemp)"
  awk -v key="$key" -v value="$value" '
    BEGIN { done=0; pat="^[[:space:]]*#?[[:space:]]*" key "=" }
    $0 ~ pat {
      if (!done) { print key "=" value; done=1 }
      next
    }
    { print }
    END { if (!done) print key "=" value }
  ' .env > "$tmp"
  mv "$tmp" .env
}

# Prefer an explicitly injected APP_KEY (immutable/platform mode).
if [ -z "${APP_KEY:-}" ]; then
  if [ -f "$KEY_FILE" ]; then
    APP_KEY="$(cat "$KEY_FILE")"
  else
    APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
    printf '%s' "$APP_KEY" > "$KEY_FILE"
  fi
  export APP_KEY
else
  printf '%s' "$APP_KEY" > "$KEY_FILE"
fi

upsert_env APP_KEY "$APP_KEY"

# Stranger Compose / self-host: keep bootstrap drivers Redis-free unless injected otherwise.
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export CACHE_STORE="${CACHE_STORE:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
upsert_env SESSION_DRIVER "$SESSION_DRIVER"
upsert_env CACHE_STORE "$CACHE_STORE"
upsert_env QUEUE_CONNECTION "$QUEUE_CONNECTION"

if [ -n "${DB_HOST:-}" ]; then
  export DB_CONNECTION="${DB_CONNECTION:-mysql}"
  upsert_env DB_CONNECTION "$DB_CONNECTION"
  upsert_env DB_HOST "$DB_HOST"
  [ -n "${DB_PORT:-}" ] && upsert_env DB_PORT "$DB_PORT"
  [ -n "${DB_DATABASE:-}" ] && upsert_env DB_DATABASE "$DB_DATABASE"
  [ -n "${DB_USERNAME:-}" ] && upsert_env DB_USERNAME "$DB_USERNAME"
  [ -n "${DB_PASSWORD:-}" ] && upsert_env DB_PASSWORD "$DB_PASSWORD"
fi

if [ -n "${APP_URL:-}" ]; then
  upsert_env APP_URL "$APP_URL"
fi
if [ -n "${SHOP_BASE_URL:-}" ]; then
  upsert_env SHOP_BASE_URL "$SHOP_BASE_URL"
fi

cp .env "$ENV_PERSIST"

# Ensure Apache can read runtime files written as root in this entrypoint.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec apache2-foreground
