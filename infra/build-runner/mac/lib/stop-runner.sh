#!/usr/bin/env bash
# Stop GitHub Actions runner listener without hanging on run.sh stop.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ark-builder-env.sh
source "$SCRIPT_DIR/ark-builder-env.sh"

wait_for_listener_stop() {
    for _ in $(seq 1 20); do
        pgrep -f "Runner.Listener" >/dev/null 2>&1 || return 0
        sleep 1
    done
    return 1
}

if [[ ! -d "$RUNNER_DIR" ]]; then
    exit 0
fi

pkill -f "Runner.Worker" 2>/dev/null || true

if pgrep -f "Runner.Listener" >/dev/null 2>&1; then
    cd "$RUNNER_DIR"
    timeout 30 ./run.sh stop 2>/dev/null || true
    wait_for_listener_stop || true
    if pgrep -f "Runner.Listener" >/dev/null 2>&1; then
        pkill -f "Runner.Listener" 2>/dev/null || true
        sleep 5
        wait_for_listener_stop || pkill -9 -f "Runner.Listener" 2>/dev/null || true
    fi
    sleep 5
fi
