#!/bin/sh
set -eu

# Bind mounts replace image-baked storage dirs. Ensure layout + www-data ownership
# so php-fpm, Horizon, and Reverb can read/write PDFs, sessions, and logs.
for dir in \
    /app/storage/app/private \
    /app/storage/app/private/oidc/keys \
    /app/storage/app/public \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs \
    /app/storage/app/install
do
    mkdir -p "$dir"
done

chown -R www-data:www-data /app/storage/app /app/storage/framework /app/storage/logs
find /app/storage/app /app/storage/framework -type d -exec chmod 2775 {} +
find /app/storage/app /app/storage/framework -type f -exec chmod 664 {} + 2>/dev/null || true
chmod 2775 /app/storage/logs

# Compose/Vultr: load generated install secrets (DB password, Reverb, APP_KEY)
# before PHP-FPM starts. Coolify/platform installs skip this file and keep
# injected environment values.
if [ -s "${ARK_INSTALL_SECRETS_FILE:-/run/ark/secrets/install.env}" ]; then
    # shellcheck disable=SC1091
    set -a
    . "${ARK_INSTALL_SECRETS_FILE:-/run/ark/secrets/install.env}"
    set +a
    export DB_PASSWORD REVERB_APP_KEY REVERB_APP_SECRET
    export DB_DATABASE="${DB_DATABASE:-ark}"
    export DB_USERNAME="${DB_USERNAME:-ark}"
    export REVERB_APP_ID="${REVERB_APP_ID:-ark}"
    unset MYSQL_ROOT_PASSWORD

    # php-fpm clears process env (clear_env=yes). Persist durable secrets into
    # storage-backed dotenv so Laravel reads DB_PASSWORD after container recreate.
    ENV_PERSIST=/app/storage/app/install/dotenv
    mkdir -p /app/storage/app/install
    if [ -f "$ENV_PERSIST" ]; then
        cp "$ENV_PERSIST" /app/.env
    elif [ -f /app/.env.example ]; then
        cp /app/.env.example /app/.env
    else
        touch /app/.env
    fi
    upsert_dotenv() {
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
        ' /app/.env > "$tmp"
        mv "$tmp" /app/.env
    }
    [ -n "${APP_KEY:-}" ] && upsert_dotenv APP_KEY "$APP_KEY"
    upsert_dotenv DB_CONNECTION mysql
    upsert_dotenv DB_HOST mysql
    upsert_dotenv DB_PORT 3306
    upsert_dotenv DB_DATABASE "${DB_DATABASE}"
    upsert_dotenv DB_USERNAME "${DB_USERNAME}"
    upsert_dotenv DB_PASSWORD "${DB_PASSWORD}"
    [ -n "${REVERB_APP_ID:-}" ] && upsert_dotenv REVERB_APP_ID "$REVERB_APP_ID"
    [ -n "${REVERB_APP_KEY:-}" ] && upsert_dotenv REVERB_APP_KEY "$REVERB_APP_KEY"
    [ -n "${REVERB_APP_SECRET:-}" ] && upsert_dotenv REVERB_APP_SECRET "$REVERB_APP_SECRET"
    [ -n "${APP_URL:-}" ] && upsert_dotenv APP_URL "$APP_URL"
    [ -n "${SHOP_BASE_URL:-}" ] && upsert_dotenv SHOP_BASE_URL "$SHOP_BASE_URL"
    [ -n "${ARK_CLOUD_BASE_URL:-}" ] && upsert_dotenv ARK_CLOUD_BASE_URL "$ARK_CLOUD_BASE_URL"
    cp /app/.env "$ENV_PERSIST"
    chown www-data:www-data /app/.env "$ENV_PERSIST" 2>/dev/null || true
fi

# APP_KEY must exist in the process environment before supervisord starts php-fpm,
# Horizon, Reverb, and the scheduler. Persists on ark_storage across recreate.
# Presence of APP_KEY does NOT mean ARK is installed.
# shellcheck source=/dev/null
. /app/infra/coolify/bootstrap-app-key.sh

# OIDC signing keys: CLI creates as root; php-fpm runs as www-data.
if [ -d /app/storage/app/private/oidc/keys ]; then
    chmod 0750 /app/storage/app/private/oidc/keys
    chmod 0644 /app/storage/app/private/oidc/keys/*.public.pem 2>/dev/null || true
    chmod 0640 /app/storage/app/private/oidc/keys/*.private.pem 2>/dev/null || true
    chgrp www-data /app/storage/app/private/oidc/keys/*.private.pem 2>/dev/null || true
fi

# public/storage must symlink to bind-mounted uploads (not a stale image directory).
if [ -e /app/public/storage ] && [ ! -L /app/public/storage ]; then
    rm -rf /app/public/storage
fi
php /app/artisan storage:link --force >/dev/null 2>&1 || true

# Surface domain routing bakes hosts into route/config cache. Clear synchronously
# before nginx serves traffic — post-deploy runs in the background and is too late.
php /app/artisan config:clear --no-interaction >/dev/null 2>&1 || true
php /app/artisan route:clear --no-interaction >/dev/null 2>&1 || true
# Shared storage/framework volume keeps compiled Blade across image pulls.
# Wipe then clear so a redeploy cannot serve yesterday's Today markup.
find /app/storage/framework/views -mindepth 1 -delete 2>/dev/null || true
php /app/artisan view:clear --no-interaction >/dev/null 2>&1 || true

# Lifecycle boundary: installer owns birth; post-deploy owns upgrades.
# InstallationState is file-backed under storage/app/install — no DB required.
# Exit 0 = installed, 1 = not installed, other = check failed (skip mutations).
# Presence of APP_KEY does NOT mean ARK is installed.
set +e
php /app/artisan ark:install-status --check-installed --quiet
install_status=$?
set -e

if [ "$install_status" -eq 0 ]; then
    echo "[entrypoint] ARK installed; launching installed-instance post-deploy."

    # Voice SIP registrar is filesystem/config only, but belongs with installed runtime.
    php /app/artisan ark:voice:ensure-transport-config --no-interaction >/dev/null 2>&1 || true

    if [ -x /app/infra/coolify/ark-post-deploy.sh ]; then
        /app/infra/coolify/ark-post-deploy.sh >> /app/storage/logs/ark-post-deploy.log 2>&1 &
    fi
elif [ "$install_status" -eq 1 ]; then
    echo "[entrypoint] ARK first-run setup pending; installed-instance post-deploy skipped."
else
    echo "[entrypoint] ARK install-status check failed (exit ${install_status}); installed-instance post-deploy skipped."
fi

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
