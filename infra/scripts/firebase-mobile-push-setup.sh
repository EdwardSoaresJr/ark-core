#!/usr/bin/env bash
# Wire Firebase FCM as transport only - client config files + optional production enablement.
# Requires: Firebase Console exports (see docs/mobile/firebase-push-setup-checklist.md)
set -euo pipefail

# arksmsv2 and ark-mobile are siblings under Herd/ (or any parent checkout dir).
ARK_MOBILE_DIR="${ARK_MOBILE_DIR:-$(cd "$(dirname "$0")/../.." && pwd)/../ark-mobile}"
PRODUCTION_HOST="${PRODUCTION_HOST:-root@144.202.74.190}"
# Mounted in app container as /app/storage/app/private/
PRODUCTION_SECRET_HOST="/data/ark-shared/storage/app/private/firebase-mobile-service-account.json"
PRODUCTION_SECRET_CONTAINER="/app/storage/app/private/firebase-mobile-service-account.json"
PROJECT_ID=""
SERVICE_ACCOUNT=""
GOOGLE_SERVICES=""
GOOGLE_SERVICE_INFO=""
ENABLE_PRODUCTION=false

usage() {
    cat <<'EOF'
Usage: firebase-mobile-push-setup.sh --project-id ID --service-account PATH [options]

Required:
  --project-id ID              Firebase project ID (Console → Project settings)
  --service-account PATH       Service account JSON (FCM Admin private key)

Optional:
  --google-services PATH       Android google-services.json → ark-mobile
  --google-service-info PATH   iOS GoogleService-Info.plist → ark-mobile
  --ark-mobile-dir PATH        Default: ../ark-mobile relative to arksmsv2
  --enable-production          SCP secret + enable shop_settings mobile_push on production
  --help

Transport only - no Firestore/Auth/Analytics. FCM Spark plan is free.
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --project-id) PROJECT_ID="$2"; shift 2 ;;
        --service-account) SERVICE_ACCOUNT="$2"; shift 2 ;;
        --google-services) GOOGLE_SERVICES="$2"; shift 2 ;;
        --google-service-info) GOOGLE_SERVICE_INFO="$2"; shift 2 ;;
        --ark-mobile-dir) ARK_MOBILE_DIR="$2"; shift 2 ;;
        --enable-production) ENABLE_PRODUCTION=true; shift ;;
        --help|-h) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
    esac
done

if [[ -z "$PROJECT_ID" || -z "$SERVICE_ACCOUNT" ]]; then
    echo "Error: --project-id and --service-account are required." >&2
    usage
    exit 1
fi

if [[ ! -f "$SERVICE_ACCOUNT" ]]; then
    echo "Error: service account not found: $SERVICE_ACCOUNT" >&2
    exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
    echo "Error: jq is required (brew install jq)." >&2
    exit 1
fi

if ! jq -e '.client_email and .private_key and .project_id' "$SERVICE_ACCOUNT" >/dev/null 2>&1; then
    echo "Error: service account JSON missing client_email, private_key, or project_id." >&2
    exit 1
fi

JSON_PROJECT_ID="$(jq -r '.project_id' "$SERVICE_ACCOUNT")"
if [[ "$JSON_PROJECT_ID" != "$PROJECT_ID" ]]; then
    echo "Warning: --project-id ($PROJECT_ID) differs from JSON project_id ($JSON_PROJECT_ID). Using --project-id."
fi

echo "==> Validated service account for $JSON_PROJECT_ID"

if [[ -n "$GOOGLE_SERVICES" ]]; then
    if [[ ! -d "$ARK_MOBILE_DIR/android/app" ]]; then
        echo "Error: ark-mobile android/app not found at $ARK_MOBILE_DIR" >&2
        exit 1
    fi
    cp "$GOOGLE_SERVICES" "$ARK_MOBILE_DIR/android/app/google-services.json"
    echo "==> Installed android/app/google-services.json"
fi

if [[ -n "$GOOGLE_SERVICE_INFO" ]]; then
    if [[ ! -d "$ARK_MOBILE_DIR/ios/Runner" ]]; then
        echo "Error: ark-mobile ios/Runner not found at $ARK_MOBILE_DIR" >&2
        exit 1
    fi
    cp "$GOOGLE_SERVICE_INFO" "$ARK_MOBILE_DIR/ios/Runner/GoogleService-Info.plist"
    echo "==> Installed ios/Runner/GoogleService-Info.plist"
fi

LOCAL_SECRET="$(cd "$(dirname "$0")/../.." && pwd)/storage/app/private/firebase-mobile-service-account.json"
mkdir -p "$(dirname "$LOCAL_SECRET")"
cp "$SERVICE_ACCOUNT" "$LOCAL_SECRET"
chmod 600 "$LOCAL_SECRET"
echo "==> Copied service account to storage/app/private/ (local dev fallback)"

if [[ "$ENABLE_PRODUCTION" == true ]]; then
    echo "==> Uploading service account to production (mounted storage)..."
    ssh "$PRODUCTION_HOST" "mkdir -p /data/ark-shared/storage/app/private && chmod 700 /data/ark-shared/storage/app/private"
    scp "$SERVICE_ACCOUNT" "${PRODUCTION_HOST}:${PRODUCTION_SECRET_HOST}"
    ssh "$PRODUCTION_HOST" "chmod 600 ${PRODUCTION_SECRET_HOST}"

    echo "==> Locking production env + shop dispatch (platform file - not shop JSON)..."
    ssh "$PRODUCTION_HOST" bash <<REMOTE
set -euo pipefail
ENV="/data/coolify/applications/b38otdn2epypspy0jadbgfl0/.env"
touch "\$ENV"
if grep -q '^FIREBASE_CREDENTIALS=' "\$ENV"; then
    sed -i "s|^FIREBASE_CREDENTIALS=.*|FIREBASE_CREDENTIALS=${PRODUCTION_SECRET_CONTAINER}|" "\$ENV"
else
    echo "FIREBASE_CREDENTIALS=${PRODUCTION_SECRET_CONTAINER}" >> "\$ENV"
fi
if grep -q '^FCM_ENABLED=' "\$ENV"; then
    sed -i 's|^FCM_ENABLED=.*|FCM_ENABLED=true|' "\$ENV"
else
    printf '\nFCM_ENABLED=true\n' >> "\$ENV"
fi
cd "/data/coolify/applications/b38otdn2epypspy0jadbgfl0"
docker compose up -d --force-recreate
REMOTE

    ssh "$PRODUCTION_HOST" "docker exec \$(docker ps --format '{{.Names}}' | grep b38ot | head -1) php artisan tinker --execute=\"
App\\\\Ark\\\\Operations\\\\Settings\\\\ShopSettings::current()->persistTrusted([
    'mobile_push' => ['enabled' => true],
]);
\""

    echo "==> Verifying push transport..."
    ssh "$PRODUCTION_HOST" "docker exec \$(docker ps --format '{{.Names}}' | grep b38ot | head -1) php artisan ark:mobile-push:verify --migrate-legacy --no-interaction" \
        || echo "Warning: verify failed - run infra/coolify/ensure-lugsnplugs-firebase-push.sh"

    echo "==> Production push wired via platform file. Shop toggle: Settings → Communications → Mobile."
else
    echo "==> Skipped production (--enable-production not set)."
    echo "    Or enable manually: https://lugsnplugs.arksms.com/app/settings/shop?section=communications&communications-tab=mobile"
fi

echo ""
echo "Done. Next: rebuild ark-mobile on device, advisor login, test inbound SMS push."
