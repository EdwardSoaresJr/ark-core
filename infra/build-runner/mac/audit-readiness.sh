#!/usr/bin/env bash
# Phase 1 — Mac build runner readiness audit (read-only).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

pass() { echo -e "${GREEN}PASS${NC}  $*"; }
fail() { echo -e "${RED}FAIL${NC}  $*"; FAILURES=$((FAILURES + 1)); }
warn() { echo -e "${YELLOW}WARN${NC}  $*"; }

FAILURES=0

echo "=== Mac Build Runner Readiness ==="
echo "Date: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "ARK_HOME: $ARK_HOME"
echo "Runner:   $RUNNER_NAME @ $RUNNER_DIR"
echo ""

echo "--- Hardware ---"
system_profiler SPHardwareDataType 2>/dev/null | grep -E "Model Name|Model Identifier|Chip|Memory" | sed 's/^[[:space:]]*/  /'
pass "Hardware profile readable"

echo ""
echo "--- Software ---"
sw_vers 2>/dev/null | sed 's/^/  /'
xcode-select -p >/dev/null 2>&1 && pass "Xcode CLT installed" || fail "Xcode CLT missing"
git --version >/dev/null 2>&1 && pass "$(git --version)" || fail "git missing"

echo ""
echo "--- ARK directory layout ---"
mkdir -p "$ARK_HOME" "$ARK_BUILD_CACHE" "$(dirname "$RUNNER_DIR")"
pass "ARK_HOME exists: $ARK_HOME"
pass "Build cache path: $ARK_BUILD_CACHE"

echo ""
echo "--- Docker Desktop settings (manual) ---"
warn "After install, set Docker Desktop → Settings → Resources:"
echo "         CPUs: 6–8 · Memory: 12–16 GiB · Disk: 100+ GiB"
echo "         See: infra/build-runner/mac/docker-desktop-settings.md"

echo ""
echo "--- Docker ---"
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    pass "docker $(docker version --format '{{.Server.Version}}' 2>/dev/null)"
    docker buildx version >/dev/null 2>&1 && pass "$(docker buildx version | head -1)" || fail "buildx missing"
    if docker buildx inspect "$BUILDX_BUILDER" >/dev/null 2>&1; then
        pass "Explicit builder '$BUILDX_BUILDER' configured"
    else
        warn "Run ensure-buildx.sh after Docker install"
    fi
else
    fail "Docker not running — install-prerequisites.sh + open Docker Desktop"
fi

echo ""
echo "--- Cross-platform ---"
[[ "$(uname -m)" == "arm64" ]] && warn "Apple Silicon — workflows use platforms: linux/amd64 + DOCKER_DEFAULT_PLATFORM"
if docker info >/dev/null 2>&1 && docker pull --platform linux/amd64 alpine:3.20 >/dev/null 2>&1; then
    pass "linux/amd64 pull works"
fi

echo ""
echo "--- Network ---"
curl -sf --max-time 8 https://github.com >/dev/null && pass "github.com" || fail "github.com"
curl -sf --max-time 8 https://ghcr.io/v2/ >/dev/null || [[ "$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 https://ghcr.io/v2/)" == "401" ]] && pass "ghcr.io" || fail "ghcr.io"

echo ""
echo "--- Runner ---"
[[ -f "$RUNNER_DIR/.runner" ]] && pass "Registered at $RUNNER_DIR" || warn "Not registered yet"
pgrep -f "Runner.Listener" >/dev/null && pass "Listener running" || warn "Listener stopped (ephemeral OK)"

echo ""
echo "=== Summary ==="
[[ "$FAILURES" -eq 0 ]] && echo -e "${GREEN}Ready for registration / validation.${NC}" && exit 0
echo -e "${RED}$FAILURES blocking check(s).${NC}"
exit 1
