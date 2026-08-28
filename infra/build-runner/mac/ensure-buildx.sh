#!/usr/bin/env bash
# Create or select explicit Buildx builder for linux/amd64 (never Docker Desktop defaults).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

if ! docker info >/dev/null 2>&1; then
    echo "Docker daemon not running." >&2
    exit 1
fi

mkdir -p "$ARK_BUILD_CACHE"

if docker buildx inspect "$BUILDX_BUILDER" >/dev/null 2>&1; then
    docker buildx use "$BUILDX_BUILDER"
else
    docker buildx create \
        --name "$BUILDX_BUILDER" \
        --driver docker-container \
        --platform linux/amd64 \
        --use
fi

docker buildx inspect --bootstrap >/dev/null

echo "Buildx builder: $BUILDX_BUILDER (platform: linux/amd64)"
docker buildx ls | head -5
