#!/usr/bin/env bash
# Install launchd agent so ark-build-01 survives logout and interactive shell exit.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/ark-builder-env.sh
source "$SCRIPT_DIR/lib/ark-builder-env.sh"

LABEL="com.github.ark.builder"
PLIST_DEST="$HOME/Library/LaunchAgents/${LABEL}.plist"
DAEMON="$SCRIPT_DIR/run-listener-daemon.sh"
LOG_OUT="$RUNNER_DIR/runner.log"
LOG_ERR="$RUNNER_DIR/launchd.err.log"

chmod +x "$DAEMON" "$SCRIPT_DIR/lib/stop-runner.sh"

mkdir -p "$HOME/Library/LaunchAgents" "$(dirname "$LOG_OUT")"

cat > "$PLIST_DEST" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>${LABEL}</string>
    <key>ProgramArguments</key>
    <array>
        <string>/bin/bash</string>
        <string>${DAEMON}</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>${LOG_OUT}</string>
    <key>StandardErrorPath</key>
    <string>${LOG_ERR}</string>
    <key>EnvironmentVariables</key>
    <dict>
        <key>PATH</key>
        <string>/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin</string>
    </dict>
</dict>
</plist>
EOF

"$SCRIPT_DIR/lib/stop-runner.sh" 2>/dev/null || true
launchctl bootout "gui/$(id -u)/${LABEL}" 2>/dev/null || launchctl unload "$PLIST_DEST" 2>/dev/null || true
echo "Waiting for GitHub runner session to clear..."
sleep 90
launchctl bootstrap "gui/$(id -u)" "$PLIST_DEST" 2>/dev/null || launchctl load "$PLIST_DEST"

echo "Installed ${PLIST_DEST}"
echo "Listener managed by launchd (KeepAlive). Disable with: ark-build down --launchd"
echo "Logs: ${LOG_OUT}"

for _ in $(seq 1 30); do
    pgrep -f "Runner.Listener" >/dev/null 2>&1 && break
    sleep 2
done

pgrep -f "Runner.Listener" >/dev/null 2>&1 || {
    echo "WARN: Listener not up yet — check ${LOG_ERR}" >&2
    exit 1
}

echo "ARK_BUILDER_ENABLED=true" > "$ARK_HOME/builder-enabled.env"
echo "Runner listener online."
