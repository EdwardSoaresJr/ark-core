#!/usr/bin/env bash
# Identity + source-commit checks for a public Core production image.
# Does not build or push.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

"$root/scripts/assert-canonical-core-repo.sh" >/dev/null

IMAGE="${IMAGE:-ghcr.io/edwardsoaresjr/ark}"
if [[ "$IMAGE" != "ghcr.io/edwardsoaresjr/ark" ]]; then
  echo "REFUSING: public Core publishes only to ghcr.io/edwardsoaresjr/ark" >&2
  echo "IMAGE=$IMAGE" >&2
  exit 1
fi

if [[ -z "${SOURCE_COMMIT:-}" ]]; then
  echo "REFUSING: set SOURCE_COMMIT to the exact commit being published." >&2
  echo "Example: SOURCE_COMMIT=\$(git rev-parse HEAD) ./infra/build-runner/mac/publish-ghcr-ark.sh" >&2
  exit 1
fi

if ! wanted="$(git rev-parse --verify "${SOURCE_COMMIT}^{commit}" 2>/dev/null)"; then
  echo "REFUSING: SOURCE_COMMIT is not a commit in this repository: $SOURCE_COMMIT" >&2
  exit 1
fi

head="$(git rev-parse HEAD)"
if [[ "$wanted" != "$head" ]]; then
  echo "REFUSING: SOURCE_COMMIT $SOURCE_COMMIT ($wanted) is not HEAD ($head)." >&2
  echo "Checkout that commit before publishing." >&2
  exit 1
fi

branch="$(git branch --show-current 2>/dev/null || true)"
echo "Public Core publish identity ok"
echo "  IMAGE=$IMAGE"
echo "  SOURCE_COMMIT=$wanted"
echo "  branch=${branch:-<detached>}"
