#!/usr/bin/env bash
# Publish canonical public ARK Core to GHCR.
#
# Tags are the commit SHA only. Floating tags are refused.
# The image is git archive of a commit already on origin/main.
# Docker does not see the worktree.
#
#   SOURCE_COMMIT=$(git rev-parse HEAD) ./infra/build-runner/mac/publish-ghcr-ark.sh
#
# Does not deploy Coolify. Does not touch LNP production or shadow hosts.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

IMAGE="${IMAGE:-ghcr.io/edwardsoaresjr/ark-core}"
PLATFORM="${PLATFORM:-linux/amd64}"

cd "$REPO_ROOT"
"$REPO_ROOT/scripts/assert-canonical-core-publish.sh"
"$REPO_ROOT/scripts/assert-core-worktree-clean.sh" --repo "$REPO_ROOT"

SHA="$(git rev-parse --verify "${SOURCE_COMMIT}^{commit}")"
"$REPO_ROOT/scripts/assert-core-source-published.sh" --repo "$REPO_ROOT" --commit "$SHA"
SHORT_SHA="$(git rev-parse --short=12 "$SHA")"

CONTEXT="$(mktemp -d "${TMPDIR:-/tmp}/ark-core-release-XXXXXX")"
cleanup() {
    rm -rf "$CONTEXT"
}
trap cleanup EXIT

"$REPO_ROOT/scripts/materialize-core-release-context.sh" --repo "$REPO_ROOT" --commit "$SHA" --dest "$CONTEXT"

echo "Publishing public Core ${SHA}"
echo "  image: ${IMAGE}"
echo "  platform: ${PLATFORM}"
echo "  source-commit: ${SHA}"
echo "  context: git archive"

docker info >/dev/null 2>&1 || { echo "Start Docker Desktop first." >&2; exit 1; }
"$SCRIPT_DIR/ensure-buildx.sh"

# Prefer keychain/credsStore login already configured for GHCR.
if ! docker buildx imagetools inspect "${IMAGE}:${SHA}" >/dev/null 2>&1; then
    true
fi

BUILD_ARGS=(
    --builder "${BUILDX_BUILDER}"
    --platform "${PLATFORM}"
    --file "${CONTEXT}/Dockerfile"
    --build-arg "GIT_SHA=${SHA}"
    --tag "${IMAGE}:${SHA}"
    --tag "${IMAGE}:${SHORT_SHA}"
    --cache-from "type=local,src=${ARK_BUILD_CACHE}"
    --cache-to "type=local,dest=${ARK_BUILD_CACHE},mode=max"
    --push
)

if [[ -n "${PUBLISH_CONVENIENCE_TAG:-}" ]]; then
    echo "REFUSING: Core publish does not push floating tags." >&2
    exit 1
fi

docker buildx build "${BUILD_ARGS[@]}" "$CONTEXT"

echo ""
echo "Published:"
echo "  ${IMAGE}:${SHA}"
echo "  ${IMAGE}:${SHORT_SHA}"
docker buildx imagetools inspect "${IMAGE}:${SHA}" | head -20
echo ""
echo "Pin certs by digest from imagetools output above - not by floating tags."
