#!/usr/bin/env bash
# Phase 2 — Install Docker Desktop and ~/ARK layout.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

echo "ARK build runner — install prerequisites"
echo "  ARK_HOME=$ARK_HOME"
echo ""

if ! command -v brew >/dev/null 2>&1; then
    echo "Homebrew required: https://brew.sh" >&2
    exit 1
fi

mkdir -p "$ARK_HOME" "$ARK_BUILD_CACHE" "$(dirname "$RUNNER_DIR")"

if [[ ! -f "$ARK_HOME/ark-builder.env" ]]; then
    cp "$SCRIPT_DIR/ark-builder.env.example" "$ARK_HOME/ark-builder.env"
    echo "Created $ARK_HOME/ark-builder.env"
fi

if [[ -d /Applications/Docker.app ]]; then
    echo "Docker Desktop already installed."
else
    brew install --cask docker
    echo "Open Docker Desktop and configure resources — see docker-desktop-settings.md"
fi

if ! grep -q 'ark-build' "$HOME/.zprofile" 2>/dev/null; then
    cat >> "$HOME/.zprofile" <<EOF

# ARK build runner
export ARK_HOME="\$HOME/ARK"
export DOCKER_BUILDKIT=1
export DOCKER_DEFAULT_PLATFORM=linux/amd64
export PATH="$SCRIPT_DIR:\$PATH"
EOF
    echo "Added ark-build to PATH via ~/.zprofile"
fi

echo ""
echo "Next:"
echo "  1. Docker Desktop → Settings (see docker-desktop-settings.md)"
echo "  2. open -a Docker"
echo "  3. ./ensure-buildx.sh"
echo "  4. ./register-runner.sh"
echo "  5. ark-build up"
