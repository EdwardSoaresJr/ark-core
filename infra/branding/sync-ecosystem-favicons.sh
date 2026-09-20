#!/usr/bin/env bash
# Confirm the ARK favicon pack is present for Core and other ARK surfaces.
# Source of truth: public/assets/ARK_SMS_FINAL_DROP_IN_PACK/favicon/
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PACK="${REPO_ROOT}/public/assets/ARK_SMS_FINAL_DROP_IN_PACK"

if [[ ! -d "${PACK}/favicon" ]]; then
    echo "ARK favicon pack missing at ${PACK}/favicon" >&2
    exit 1
fi

echo "ARK ecosystem favicons present at ${PACK}/favicon"
