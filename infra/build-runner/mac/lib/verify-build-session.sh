#!/usr/bin/env bash
# Refuse builds when build day has not been started (ark-build up).
set -euo pipefail

ARK_HOME="${ARK_HOME:-$HOME/ARK}"
ENABLED_FILE="${ARK_HOME}/builder-enabled.env"

if [[ -f "$ENABLED_FILE" ]]; then
    # shellcheck source=/dev/null
    source "$ENABLED_FILE"
fi

if [[ "${ARK_BUILDER_ENABLED:-}" != "true" ]]; then
    echo "::error::Build session not active. Run: ark-build up"
    exit 1
fi

echo "Build session enabled (ARK_BUILDER_ENABLED=true)."
