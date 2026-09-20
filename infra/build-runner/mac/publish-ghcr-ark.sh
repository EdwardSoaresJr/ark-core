#!/usr/bin/env bash
# Publish canonical public ARK Core to GHCR.
#
# Authoritative tags are immutable commit SHAs (+ digest). Convenience tags are
# optional and non-authoritative.
#
#   ./infra/build-runner/mac/publish-ghcr-ark.sh
#   IMAGE=ghcr.io/edwardsoaresjr/ark ./infra/build-runner/mac/publish-ghcr-ark.sh
#
# Does not deploy Coolify. Does not touch LNP production or shadow hosts.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

IMAGE="${IMAGE:-ghcr.io/edwardsoaresjr/ark}"
PLATFORM="${PLATFORM:-linux/amd64}"

cd "$REPO_ROOT"

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "REFUSING: working tree has staged/unstaged changes. Commit or stash first." >&2
    git status -sb >&2
    exit 1
fi

if [[ -n "$(git ls-files --others --exclude-standard)" ]]; then
    echo "REFUSING: untracked files present (would not be in git archive, but refuse to avoid ambiguity)." >&2
    git ls-files --others --exclude-standard >&2
    exit 1
fi

SHA="$(git rev-parse HEAD)"
SHORT_SHA="$(git rev-parse --short=12 HEAD)"

echo "Publishing public Core ${SHA}"
echo "  image: ${IMAGE}"
echo "  platform: ${PLATFORM}"

docker info >/dev/null 2>&1 || { echo "Start Docker Desktop first." >&2; exit 1; }
"$SCRIPT_DIR/ensure-buildx.sh"

# Prefer keychain/credsStore login already configured for GHCR.
if ! docker buildx imagetools inspect "${IMAGE}:${SHA}" >/dev/null 2>&1; then
    true
fi

BUILD_ARGS=(
    --builder "${BUILDX_BUILDER}"
    --platform "${PLATFORM}"
    --file Dockerfile
    --build-arg "GIT_SHA=${SHA}"
    --tag "${IMAGE}:${SHA}"
    --tag "${IMAGE}:${SHORT_SHA}"
    --cache-from "type=local,src=${ARK_BUILD_CACHE}"
    --cache-to "type=local,dest=${ARK_BUILD_CACHE},mode=max"
    --push
)

# Optional non-authoritative convenience tag (never the cert pin).
if [[ "${PUBLISH_CONVENIENCE_TAG:-}" == "1" ]]; then
    BUILD_ARGS+=(--tag "${IMAGE}:build-cert")
fi

docker buildx build "${BUILD_ARGS[@]}" "$REPO_ROOT"

echo ""
echo "Published:"
echo "  ${IMAGE}:${SHA}"
echo "  ${IMAGE}:${SHORT_SHA}"
docker buildx imagetools inspect "${IMAGE}:${SHA}" | head -20
echo ""
echo "Pin certs by digest from imagetools output above — not by floating tags."
