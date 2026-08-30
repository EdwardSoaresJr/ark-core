#!/usr/bin/env bash
# Certify infra/coolify/bootstrap-app-key.sh without printing key material.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
HELPER="$ROOT/infra/coolify/bootstrap-app-key.sh"
WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/ark-app-key-XXXXXX")"
trap 'rm -rf "$WORKDIR"' EXIT

export ARK_APP_KEY_FILE="$WORKDIR/storage/app/install/app_key"
mkdir -p "$(dirname "$ARK_APP_KEY_FILE")"

assert_key_shape() {
  local key="$1"
  [[ "$key" == base64:* ]] || { echo "FAIL: key missing base64: prefix"; exit 1; }
  local b64="${key#base64:}"
  local decoded
  decoded="$(printf '%s' "$b64" | base64 -d 2>/dev/null | wc -c | tr -d ' ')"
  [[ "$decoded" == "32" ]] || { echo "FAIL: expected 32 raw key bytes, got $decoded"; exit 1; }
}

# 1) Fresh generate
unset APP_KEY || true
# shellcheck source=/dev/null
. "$HELPER"
assert_key_shape "$APP_KEY"
FIRST="$APP_KEY"
[[ -s "$ARK_APP_KEY_FILE" ]] || { echo "FAIL: key file not persisted"; exit 1; }
FILE_FIRST="$(cat "$ARK_APP_KEY_FILE")"
[[ "$FILE_FIRST" == "$FIRST" ]] || { echo "FAIL: file does not match exported key"; exit 1; }

# 2) Restore same key (simulate recreate)
unset APP_KEY || true
# shellcheck source=/dev/null
. "$HELPER"
[[ "$APP_KEY" == "$FIRST" ]] || { echo "FAIL: recreate rotated APP_KEY"; exit 1; }

# 3) Explicit injected key wins
INJECTED="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
export APP_KEY="$INJECTED"
# shellcheck source=/dev/null
. "$HELPER"
[[ "$APP_KEY" == "$INJECTED" ]] || { echo "FAIL: injected APP_KEY was replaced"; exit 1; }
[[ "$(cat "$ARK_APP_KEY_FILE")" == "$INJECTED" ]] || { echo "FAIL: injected key not persisted"; exit 1; }

# 4) Helper output must not echo the key (script is source-only; grep own source for echo of APP_KEY value)
if grep -E 'echo .*APP_KEY|printf .*\$APP_KEY' "$HELPER" | grep -vq 'printf '\''%s'\'' "\$APP_KEY"'; then
  # Allow only the persist printf; ban echo of the key
  if grep -E '^\s*echo\s+"?\$APP_KEY' "$HELPER"; then
    echo "FAIL: helper echoes APP_KEY"
    exit 1
  fi
fi

echo "PASS: bootstrap-app-key generate/restore/inject"
