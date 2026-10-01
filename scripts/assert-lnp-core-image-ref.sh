#!/usr/bin/env bash
# LugsNPlugs Core deploys by immutable digest. Floating tags are refused.
set -euo pipefail

ref="${1:-}"
if [[ -z "$ref" ]]; then
  echo "Usage: assert-lnp-core-image-ref.sh ghcr.io/edwardsoaresjr/ark-core@sha256:<digest>" >&2
  exit 2
fi

if [[ "$ref" =~ ^ghcr.io/edwardsoaresjr/ark-core@sha256:[0-9a-f]{64}$ ]]; then
  echo "Immutable Core image ref ok"
  echo "  ${ref}"
  exit 0
fi

echo "REFUSING: LugsNPlugs Core must be pinned to ghcr.io/edwardsoaresjr/ark-core@sha256:<digest>." >&2
echo "Refused: ${ref}" >&2
exit 1
