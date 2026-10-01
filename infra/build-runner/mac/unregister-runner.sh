#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

[[ -n "${RUNNER_REMOVE_TOKEN:-}" ]] || { echo "Set RUNNER_REMOVE_TOKEN from GitHub UI." >&2; exit 1; }
[[ -f "$RUNNER_DIR/config.sh" ]] || { echo "No runner at $RUNNER_DIR" >&2; exit 1; }

cd "$RUNNER_DIR"
./run.sh stop 2>/dev/null || true
./config.sh remove --token "$RUNNER_REMOVE_TOKEN"
echo "Runner removed. Directory can be deleted: rm -rf $RUNNER_DIR"
