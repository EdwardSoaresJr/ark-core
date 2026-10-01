#!/usr/bin/env bash
# Append one line to ~/ARK/build-history.log
set -euo pipefail

WORKFLOW=""
TAG=""
DIGEST=""
SECONDS_ELAPSED=""
IMAGE_REF=""
CACHE_NOTE="local"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --workflow) WORKFLOW="$2"; shift 2 ;;
        --tag) TAG="$2"; shift 2 ;;
        --digest) DIGEST="$2"; shift 2 ;;
        --seconds) SECONDS_ELAPSED="$2"; shift 2 ;;
        --image) IMAGE_REF="$2"; shift 2 ;;
        --cache) CACHE_NOTE="$2"; shift 2 ;;
        *) shift ;;
    esac
done

ARK_HOME="${ARK_HOME:-$HOME/ARK}"
LOG="${ARK_HOME}/build-history.log"
mkdir -p "$ARK_HOME"

SIZE_HUMAN="unknown"
PLATFORM="unknown"
if [[ -n "$IMAGE_REF" ]] && docker image inspect "$IMAGE_REF" >/dev/null 2>&1; then
    SIZE_BYTES=$(docker image inspect "$IMAGE_REF" --format '{{.Size}}')
    PLATFORM=$(docker image inspect "$IMAGE_REF" --format '{{.Architecture}}')
    SIZE_HUMAN=$(numfmt --to=iec-i --suffix=B "$SIZE_BYTES" 2>/dev/null || echo "${SIZE_BYTES}B")
fi

M=$((SECONDS_ELAPSED / 60))
S=$((SECONDS_ELAPSED % 60))
STAMP=$(date -u +%Y-%m-%dT%H:%M:%SZ)

LINE="${STAMP} | workflow=${WORKFLOW} | Build: ${M}m ${S}s | Image: ${SIZE_HUMAN} | Platform: ${PLATFORM} | Cache: ${CACHE_NOTE} | Digest: ${DIGEST} | Tag: ${TAG}"

echo "$LINE" >> "$LOG"
echo "$LINE"
