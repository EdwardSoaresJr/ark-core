#!/usr/bin/env bash
# Phase 3 — Register replaceable runner at ~/ARK/github-runner/ark-build-01
#
#   export RUNNER_REGISTRATION_TOKEN='…'
#   ./infra/build-runner/mac/register-runner.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

RUNNER_VERSION="${RUNNER_VERSION:-2.335.1}"

if [[ -z "${RUNNER_REGISTRATION_TOKEN:-}" ]]; then
    echo "Set RUNNER_REGISTRATION_TOKEN from GitHub → Settings → Actions → Runners." >&2
    exit 1
fi

ARCH=$(uname -m)
case "$ARCH" in
    arm64) RUNNER_ARCH="arm64" ;;
    x86_64) RUNNER_ARCH="x64" ;;
    *) echo "Unsupported arch: $ARCH" >&2; exit 1 ;;
esac

TAR="actions-runner-osx-${RUNNER_ARCH}-${RUNNER_VERSION}.tar.gz"
URL="https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/${TAR}"

mkdir -p "$ARK_HOME" "$ARK_BUILD_CACHE" "$RUNNER_DIR"

echo "Registering replaceable ARK build runner"
echo "  Name:    $RUNNER_NAME"
echo "  Dir:     $RUNNER_DIR"
echo "  Labels:  $RUNNER_LABELS"
echo "  Repo:    $RUNNER_REPO"

cd "$RUNNER_DIR"

if [[ ! -f bin/Runner.Listener ]]; then
    curl -fsSL -o "$TAR" "$URL"
    tar xzf "$TAR"
    rm -f "$TAR"
fi

./config.sh remove --token "$RUNNER_REGISTRATION_TOKEN" 2>/dev/null || true

./config.sh \
    --url "https://github.com/${RUNNER_REPO}" \
    --token "$RUNNER_REGISTRATION_TOKEN" \
    --name "$RUNNER_NAME" \
    --labels "$RUNNER_LABELS" \
    --unattended \
    --replace

echo ""
echo "Runner disposable — do not back up this directory."
echo "If Mac is replaced: install Docker → register-runner.sh → done."
echo ""
echo "Next: ./ensure-buildx.sh && ./start-build-session.sh"
