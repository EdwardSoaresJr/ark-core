#!/usr/bin/env bash
# Installed-instance post-deploy for ARK on Coolify / canonical Docker.
# Invoked from infra/coolify/entrypoint.sh when InstallationState is installed.
# Defense in depth: refuse DB mutations on first-run / uninstalled hosts.
set -euo pipefail

cd /app

set +e
php artisan ark:install-status --check-installed --quiet
install_status=$?
set -e

if [[ "$install_status" -ne 0 ]]; then
    echo "[ark-post-deploy] ARK is not installed; skipping installed-instance post-deploy."
    exit 0
fi

echo "[ark-post-deploy] ARK installed; running migrations..."
php artisan migrate --force --no-interaction

echo "[ark-post-deploy] syncing RBAC permissions..."
php artisan db:seed --class=Database\\Seeders\\ArkAuthorizationSeeder --force --no-interaction

echo "[ark-post-deploy] ensuring repair order status catalog..."
php artisan repair-orders:sync-status-catalog --if-empty --no-interaction

echo "[ark-post-deploy] ensuring voice SIP transport config..."
php artisan ark:voice:ensure-transport-config --no-interaction || {
    echo "[ark-post-deploy] Voice SIP registrar not configured yet (non-fatal)." >&2
}

echo "[ark-post-deploy] verifying mobile push transport..."
php artisan ark:mobile-push:verify --no-interaction || {
    echo "[ark-post-deploy] Mobile push transport not operational (check FIREBASE_CREDENTIALS + FCM_ENABLED)." >&2
}

echo "[ark-post-deploy] backfilling communication reviews from call intelligence..."
php artisan communications:sync-sms-intelligence --days=90 --queue-analysis --no-interaction
php artisan communications:sync-reviews --no-interaction

php artisan config:clear --no-interaction >/dev/null 2>&1 || true
php artisan route:clear --no-interaction >/dev/null 2>&1 || true
php artisan view:clear --no-interaction >/dev/null 2>&1 || true

if [[ "${BOOKSTACK_CUTOVER:-false}" == "true" ]] \
    && [[ -n "${BOOKSTACK_API_TOKEN_ID:-}" ]] \
    && [[ -n "${BOOKSTACK_API_TOKEN_SECRET:-}" ]]; then
    echo "[ark-post-deploy] syncing ARKademy catalog to BookStack..."
    php artisan ark:arkademy:import-bookstack --force --no-interaction || {
        echo "[ark-post-deploy] BookStack import failed (non-fatal)." >&2
    }
    php artisan ark:bookstack:lockdown --no-interaction || {
        echo "[ark-post-deploy] BookStack lockdown failed (non-fatal)." >&2
    }
else
    echo "[ark-post-deploy] BookStack sync skipped (cutover or API token not configured)."
fi

echo "[ark-post-deploy] complete."
