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
    /app/storage/logs
do
    mkdir -p "$dir"
done

chown -R www-data:www-data /app/storage/app /app/storage/framework /app/storage/logs
find /app/storage/app /app/storage/framework -type d -exec chmod 2775 {} +
find /app/storage/app /app/storage/framework -type f -exec chmod 664 {} + 2>/dev/null || true
chmod 2775 /app/storage/logs

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
php /app/artisan ark:voice:ensure-transport-config --no-interaction >/dev/null 2>&1 || true

# Post-deploy: migrations + ARKademy BookStack sync (background — do not block nginx).
if [ -x /app/infra/coolify/ark-post-deploy.sh ]; then
    /app/infra/coolify/ark-post-deploy.sh >> /app/storage/logs/ark-post-deploy.log 2>&1 &
fi

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
