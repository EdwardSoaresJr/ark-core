#!/usr/bin/env bash
# Copy ARK favicon assets from the platform pack into ecosystem mount points.
# Source of truth: public/assets/ARK_SMS_FINAL_DROP_IN_PACK/favicon/
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PACK="${REPO_ROOT}/public/assets/ARK_SMS_FINAL_DROP_IN_PACK"
BOOKSTACK_THEME="${REPO_ROOT}/infra/coolify/bookstack/themes/arkademy/public"
BOOKSTACK_THEME_FAVICON="${BOOKSTACK_THEME}/favicon"

if [[ ! -d "${PACK}/favicon" ]]; then
    echo "ARK favicon pack missing at ${PACK}/favicon" >&2
    exit 1
fi

mkdir -p "$BOOKSTACK_THEME_FAVICON"
cp "${PACK}/favicon/"* "$BOOKSTACK_THEME_FAVICON/"
cp "${PACK}/ios/ark-180x180.png" "$BOOKSTACK_THEME_FAVICON/ark-180x180.png"
cp "${PACK}/ios/ark-180x180.png" "$BOOKSTACK_THEME/ark-header-mark.png"
cp "${PACK}/ark_logo_full_white.png" "$BOOKSTACK_THEME/ark-logo-full-white.png"

echo "ARK ecosystem favicons synced to ${BOOKSTACK_THEME_FAVICON}"
