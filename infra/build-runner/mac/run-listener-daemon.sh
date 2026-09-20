#!/usr/bin/env bash
# launchd entrypoint — keeps GitHub Actions listener alive (exec run.sh).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

mkdir -p "$ARK_HOME" "$ARK_BUILD_CACHE"
echo "ARK_BUILDER_ENABLED=true" > "$ARK_HOME/builder-enabled.env"

if ! docker info >/dev/null 2>&1; then
    open -a Docker 2>/dev/null || true
    for _ in $(seq 1 90); do
        docker info >/dev/null 2>&1 && break
        sleep 2
    done
fi

docker info >/dev/null 2>&1 || {
    echo "Docker not running — listener waiting for Docker Desktop." >&2
    exit 1
}

"$SCRIPT_DIR/ensure-buildx.sh" >/dev/null 2>&1 || true

[[ -f "$RUNNER_DIR/run.sh" ]] || {
    echo "Runner not registered at $RUNNER_DIR" >&2
    exit 1
}

echo "ARK_BUILDER_ENABLED=true" > "$RUNNER_DIR/.env"
cd "$RUNNER_DIR"
exec ./run.sh
