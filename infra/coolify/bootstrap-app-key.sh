#!/bin/sh
# Establish a durable APP_KEY before PHP-FPM / Horizon / Reverb / scheduler start.
#
# Contract (canonical Docker / Compose / Vultr):
#   - No injected APP_KEY  → restore from install storage, or generate once and persist
#   - Injected APP_KEY     → honor it and persist to the same durable file
#   - Recreate/restart     → restore the same key from ark_storage (never rotate silently)
#   - Never print the key
#
# Durable path matches InstallStorage::path('app_key') → storage/app/install/app_key
#
# Usage (must be sourced so export reaches the caller):
#   . /app/infra/coolify/bootstrap-app-key.sh

ark_bootstrap_app_key() {
    _key_file="${ARK_APP_KEY_FILE:-/app/storage/app/install/app_key}"
    mkdir -p "$(dirname "$_key_file")"

    if [ -n "${APP_KEY:-}" ]; then
        printf '%s' "$APP_KEY" > "$_key_file"
    elif [ -f "$_key_file" ] && [ -s "$_key_file" ]; then
        APP_KEY="$(cat "$_key_file")"
    else
        APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
        printf '%s' "$APP_KEY" > "$_key_file"
    fi

    export APP_KEY
    chown www-data:www-data "$_key_file" 2>/dev/null || true
    chmod 640 "$_key_file" 2>/dev/null || true
    unset _key_file
}

ark_bootstrap_app_key
