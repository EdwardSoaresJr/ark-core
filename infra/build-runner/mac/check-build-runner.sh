#!/usr/bin/env bash
# Morning sanity check (~5 seconds). Read-only.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

ok() { echo "  OK   $*"; }
bad() { echo "  FAIL $*"; FAIL=1; }
warn() { echo "  WARN $*"; }

FAIL=0

mkdir -p "$ARK_HOME" "$ARK_BUILD_CACHE" "$(dirname "$RUNNER_DIR")" 2>/dev/null || true

echo "ARK Build Runner — $(date '+%Y-%m-%d %H:%M')"
echo "  ARK_HOME=$ARK_HOME"
echo "  RUNNER=$RUNNER_NAME @ $RUNNER_DIR"
echo ""

echo "Runner"
if [[ -f "$RUNNER_DIR/.runner" ]]; then
    ok "Configured ($(grep -E '"agentName"|\"serverUrl\"' "$RUNNER_DIR/.runner" 2>/dev/null | tr -d ' ",' | paste -sd ' ' - || echo 'ark-build-01'))"
else
    bad "Not registered — run register-runner.sh"
fi
if pgrep -f "Runner.Listener" >/dev/null 2>&1; then
    ok "Listener running"
else
    warn "Listener not running — run start-build-session.sh"
fi

echo ""
echo "Docker"
if docker info >/dev/null 2>&1; then
    ok "Daemon running ($(docker version --format '{{.Server.Version}}' 2>/dev/null))"
else
    bad "Daemon not running — open Docker Desktop"
fi

echo ""
echo "Buildx (linux/amd64)"
if docker buildx version >/dev/null 2>&1; then
    if docker buildx inspect "$BUILDX_BUILDER" >/dev/null 2>&1; then
        PLAT=$(docker buildx inspect "$BUILDX_BUILDER" --format '{{range .NodeNames}}{{.}}{{end}}' 2>/dev/null || true)
        ok "Builder '$BUILDX_BUILDER' exists"
        docker buildx inspect "$BUILDX_BUILDER" 2>/dev/null | grep -i "Platforms:" | sed 's/^/       /' || true
    else
        warn "Builder '$BUILDX_BUILDER' missing — run ensure-buildx.sh"
    fi
else
    bad "buildx unavailable"
fi

echo ""
echo "GHCR"
if grep -q '"ghcr.io"' "$HOME/.docker/config.json" 2>/dev/null; then
    ok "docker login ghcr.io present"
else
    warn "Not logged in — run configure-ghcr-login.sh"
fi

echo ""
echo "Disk & cache"
df -h "$ARK_HOME" 2>/dev/null | tail -1 | awk '{printf "  Data volume: %s free of %s (%s used)\n", $4, $2, $5}'
if [[ -d "$ARK_BUILD_CACHE" ]]; then
    CACHE_SIZE=$(du -sh "$ARK_BUILD_CACHE" 2>/dev/null | cut -f1)
    ok "Build cache $ARK_BUILD_CACHE ($CACHE_SIZE)"
else
    warn "Build cache dir missing — will be created on first build"
fi

echo ""
echo "Last build log"
LOG="$RUNNER_DIR/runner.log"
if [[ -f "$LOG" ]]; then
    tail -3 "$LOG" | sed 's/^/  /'
else
    echo "  (no runner.log yet)"
fi

echo ""
if [[ "$FAIL" -eq 0 ]]; then
    echo "Ready to start-build-session.sh"
    exit 0
fi
echo "Fix FAIL items before pushing production."
exit 1
