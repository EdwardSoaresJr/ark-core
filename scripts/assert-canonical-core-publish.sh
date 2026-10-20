#!/usr/bin/env bash
# Identity checks for a public Core production image.
# SOURCE_COMMIT is the tree that will be archived. It does not have to be HEAD.
# The publisher checkout is checked separately and must be on origin/main.
# Does not build, push, or deploy.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

IMAGE="${IMAGE:-ghcr.io/edwardsoaresjr/ark-core}"
if [[ "$IMAGE" != "ghcr.io/edwardsoaresjr/ark-core" ]]; then
  echo "REFUSING: public Core publishes only to ghcr.io/edwardsoaresjr/ark-core" >&2
  echo "IMAGE=$IMAGE" >&2
  exit 1
fi

if [[ -z "${SOURCE_COMMIT:-}" ]]; then
  echo "REFUSING: set SOURCE_COMMIT to the exact commit being published." >&2
  echo "Example: SOURCE_COMMIT=<full-sha> ./infra/build-runner/mac/publish-ghcr-ark.sh" >&2
  exit 1
fi

if ! wanted="$(git rev-parse --verify "${SOURCE_COMMIT}^{commit}" 2>/dev/null)"; then
  echo "REFUSING: SOURCE_COMMIT is not a commit in this repository: $SOURCE_COMMIT" >&2
  exit 1
fi

for qz_client in public/vendor/qz/qz-tray.js public/js/ark/qz-tray.js; do
  if ! git cat-file -e "${wanted}:${qz_client}" 2>/dev/null; then
    echo "REFUSING: QZ Tray client missing from commit ${wanted}: $qz_client" >&2
    echo "The image build must fail closed. Do not publish a Core image without it." >&2
    exit 1
  fi
done

dockerfile="$(git show "${wanted}:Dockerfile")"
if ! printf '%s\n' "$dockerfile" | grep -q 'test -f /app/public/vendor/qz/qz-tray.js' \
  || ! printf '%s\n' "$dockerfile" | grep -q 'test -f /app/public/js/ark/qz-tray.js'; then
  echo "REFUSING: Dockerfile does not fail the build when the QZ client is missing." >&2
  exit 1
fi

dockerignore="$(git show "${wanted}:.dockerignore")"
if ! printf '%s\n' "$dockerignore" | grep -qx '/vendor' \
  || ! printf '%s\n' "$dockerignore" | grep -qx '!public/vendor'; then
  echo "REFUSING: .dockerignore would drop public/vendor from the image." >&2
  exit 1
fi

branch="$(git branch --show-current 2>/dev/null || true)"
echo "Public Core publish identity ok"
echo "  IMAGE=$IMAGE"
echo "  SOURCE_COMMIT=$wanted"
echo "  publisher_head=$(git rev-parse HEAD)"
echo "  branch=${branch:-<detached>}"
