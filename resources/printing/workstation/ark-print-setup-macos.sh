#!/bin/bash
# ARK Print Setup for macOS, version @@ARK_SETUP_VERSION@@, for @@ARK_SHOP_HOST@@.
#
# Lets QZ Tray trust this shop's ARK printing certificate, so printing from ARK needs no Allow prompt.
# It adds this shop's public trust root to QZ Tray's "authcert.override" setting, which is what QZ's own
# "ca" provisioning step does. Nothing else changes: other entries in that setting, other applications'
# certificates, override.crt and every other QZ setting stay as they are. No private key is involved.
#
#   sudo bash ark-print-setup.sh               set up (running it again changes nothing)
#   bash ark-print-setup.sh --status           report only, change nothing
#   sudo bash ark-print-setup.sh --uninstall   remove ARK's entry for this shop
#
# Exit codes: 0 done, 2 usage, 3 QZ Tray not installed, 4 QZ Tray too old, 5 QZ Tray settings not found,
# 6 certificate check failed, 7 needs administrator rights, 8 settings layout ARK will not change, 1 other.
set -u

ARK_SETUP_VERSION='@@ARK_SETUP_VERSION@@'
ARK_SHOP_HOST='@@ARK_SHOP_HOST@@'
ARK_ROOT_SHA256='@@ARK_ROOT_SHA256@@'
ARK_ROOT_SHA1='@@ARK_ROOT_SHA1@@'
ARK_SETUP_URL='@@ARK_SETUP_URL@@'
QZ_MIN_VERSION='2.1.6'

ARK_ROOT_PEM="$(cat <<'ARK_ROOT_PEM_END'
@@ARK_ROOT_PEM@@
ARK_ROOT_PEM_END
)"

# Locations. The ARK_* environment overrides exist for testing on a copy of QZ Tray.
QZ_APP="${ARK_QZ_APP:-/Applications/QZ Tray.app}"
TRUST_DIR="${ARK_TRUST_DIR:-/Library/Application Support/ARK/Printing/trust}"
QZ_SHARED_DIR="${ARK_QZ_SHARED_DIR:-/Library/Application Support/qz}"
NO_RESTART="${ARK_NO_RESTART:-0}"
REQUIRE_ROOT="${ARK_REQUIRE_ROOT:-1}"
TRUST_FILE_NAME="ark-${ARK_SHOP_HOST}-${ARK_ROOT_SHA256:0:16}.crt"
TRUST_FILE="$TRUST_DIR/$TRUST_FILE_NAME"

say() { printf '%s\n' "$*"; }
ok() { printf '  OK      %s\n' "$*"; }
note() { printf '  NOTE    %s\n' "$*"; }
problem() { printf '  PROBLEM %s\n' "$*"; }
stop() { local code="$1"; shift; printf '\n%s\n' "$*"; exit "$code"; }

MODE=install
for arg in "$@"; do
  case "$arg" in
    --status) MODE=status ;;
    --uninstall) MODE=uninstall ;;
    --help|-h) sed -n '2,16p' "$0"; exit 0 ;;
    *) stop 2 "Unknown option: $arg (use --status or --uninstall)" ;;
  esac
done

say "ARK Print Setup $ARK_SETUP_VERSION for $ARK_SHOP_HOST"
say ""

[ "$(uname -s)" = "Darwin" ] || [ -n "${ARK_QZ_APP:-}" ] || stop 1 "This setup is for macOS. Download the Windows setup from ARK's Set Up Printing page."

# --- The certificate this setup installs -------------------------------------------------------------
WORK="$(mktemp -d "${TMPDIR:-/tmp}/ark-print-setup.XXXXXX")" || stop 1 "Could not create a temporary folder."
trap 'rm -rf "$WORK"' EXIT
printf '%s\n' "$ARK_ROOT_PEM" > "$WORK/root.crt"

fingerprint() { openssl x509 -in "$1" -noout -fingerprint "-$2" 2>/dev/null | sed 's/.*=//; s/://g' | tr 'A-F' 'a-f'; }
actual_sha256="$(fingerprint "$WORK/root.crt" sha256)"
if [ -z "$actual_sha256" ] || [ "$actual_sha256" != "$ARK_ROOT_SHA256" ]; then
  stop 6 "The certificate inside this setup does not match its expected fingerprint. Download the setup again from ARK."
fi
if ! openssl x509 -in "$WORK/root.crt" -noout -text 2>/dev/null | grep -A1 'Basic Constraints' | grep -q 'CA:TRUE'; then
  stop 6 "The certificate inside this setup is not a trust root. Download the setup again from ARK."
fi
if ! openssl x509 -in "$WORK/root.crt" -noout -checkend 0 >/dev/null 2>&1; then
  stop 6 "The certificate inside this setup has expired. ARK needs a new printing certificate; contact ARK support."
fi
root_subject="$(openssl x509 -in "$WORK/root.crt" -noout -subject 2>/dev/null | sed 's/^subject= *//')"
ok "Certificate checked: $root_subject"
ok "SHA-256 fingerprint: $ARK_ROOT_SHA256"

# --- QZ Tray -----------------------------------------------------------------------------------------
version_ge() { # version_ge 2.2.5 2.1.6
  local IFS=.; local -a a=($1) b=($2); local i
  for i in 0 1 2 3; do
    local x="${a[$i]:-0}" y="${b[$i]:-0}"; x="${x//[^0-9]/}"; y="${y//[^0-9]/}"
    if (( ${x:-0} > ${y:-0} )); then return 0; fi
    if (( ${x:-0} < ${y:-0} )); then return 1; fi
  done
  return 0
}

console_user() {
  if [ -n "${ARK_CONSOLE_USER:-}" ]; then printf '%s' "$ARK_CONSOLE_USER"; return; fi
  local u; u="$(stat -f%Su /dev/console 2>/dev/null || true)"
  if [ -z "$u" ] || [ "$u" = "root" ]; then u="${SUDO_USER:-$(id -un)}"; fi
  printf '%s' "$u"
}
user_home() {
  if [ -n "${ARK_USER_HOME:-}" ]; then printf '%s' "$ARK_USER_HOME"; return; fi
  dscl . -read "/Users/$1" NFSHomeDirectory 2>/dev/null | sed 's/^NFSHomeDirectory: //' || eval printf '%s' "~$1"
}

CONSOLE_USER="$(console_user)"
USER_HOME="$(user_home "$CONSOLE_USER")"
QZ_USER_DIR="$USER_HOME/Library/Application Support/qz"

if [ ! -d "$QZ_APP" ]; then
  say "QZ Tray is not installed on this Mac."
  say "Install it from QZ's official download page (https://qz.io/download/), start it once, then run this setup again."
  if [ "$MODE" = install ] && [ -z "${ARK_QZ_APP:-}" ]; then
    if [ "$(id -u)" = 0 ] && [ -n "$CONSOLE_USER" ]; then
      launchctl asuser "$(id -u "$CONSOLE_USER")" sudo -u "$CONSOLE_USER" open "https://qz.io/download/" >/dev/null 2>&1 || true
    else
      open "https://qz.io/download/" >/dev/null 2>&1 || true
    fi
  fi
  exit 3
fi
QZ_VERSION="$(tr -d '\n' < "$QZ_APP/Contents/Info.plist" 2>/dev/null | sed -n 's/.*<key>CFBundleShortVersionString<\/key>[[:space:]]*<string>\([^<]*\)<\/string>.*/\1/p')"
[ -n "$QZ_VERSION" ] || QZ_VERSION="$(defaults read "$QZ_APP/Contents/Info" CFBundleShortVersionString 2>/dev/null || true)"
[ -n "$QZ_VERSION" ] || stop 1 "Could not read QZ Tray's version from $QZ_APP."
if ! version_ge "$QZ_VERSION" "$QZ_MIN_VERSION"; then
  stop 4 "QZ Tray $QZ_VERSION is too old for this setup (needs $QZ_MIN_VERSION or newer). Update it from https://qz.io/download/ and run this setup again."
fi
ok "QZ Tray $QZ_VERSION found at $QZ_APP"

# QZ Tray reads the first qz-tray.properties it finds in this order (qz.installer.certificate.CertificateManager).
PROPS=""
for candidate in "$QZ_APP/Contents/Resources/qz-tray.properties" "$QZ_SHARED_DIR/qz-tray.properties" "$QZ_USER_DIR/qz-tray.properties"; do
  if [ -f "$candidate" ]; then PROPS="$candidate"; break; fi
done
[ -n "$PROPS" ] || stop 5 "QZ Tray's settings file (qz-tray.properties) was not found. Start QZ Tray once, quit it, then run this setup again."
ok "QZ Tray settings: $PROPS"

if [ -f "$QZ_APP/Contents/Resources/override.crt" ]; then
  if [ "$(fingerprint "$QZ_APP/Contents/Resources/override.crt" sha256)" = "$ARK_ROOT_SHA256" ]; then
    note "QZ Tray's override.crt is this shop's root (an earlier manual setup). It is left as it is."
  else
    note "QZ Tray's override.crt belongs to another application. It is left as it is."
  fi
fi

# --- authcert.override editor ------------------------------------------------------------------------
# Reads a Java properties file and rewrites only the authcert.override value. Values are unescaped the
# way java.util.Properties does, then split the way QZ does (";" separates paths, "^" escapes, "|"
# starts per-path options); kept entries are written back exactly as they were. Report lines go to
# fd 3: "kept|removed|added<TAB>path". Exit 10: continued line, 11: setting repeated, 12: only trustedRootCert.
edit_properties() { # file mode(add|remove) token arkdir hostprefix scope(host|all)
  awk -v mode="$2" -v token="$3" -v arkdir="$4" -v hostprefix="$5" -v scope="$6" '
    function report(kind, path) { printf "%s\t%s\n", kind, path > "/dev/fd/3" }
    function ends_continued(s,    n) { n = 0; while (n < length(s) && substr(s, length(s) - n, 1) == "\\") n++; return n % 2 == 1 }
    function is_ark(path) { return index(path, arkdir "/ark-") == 1 }
    function is_this_host(path,    base) { base = substr(path, length(arkdir) + 2); return index(base, hostprefix) == 1 }
    function rewrite(value,    n, i, c, raw, u, k, out, tok, norm, esc, inopts, kept, count, written) {
      # java.util.Properties unescape, remembering the raw text of each logical character
      n = 0
      for (i = 1; i <= length(value); i++) {
        c = substr(value, i, 1)
        if (c == "\\" && i < length(value)) {
          raw = c substr(value, i + 1, 1); i++
          c = substr(raw, 2, 1)
          if (c == "t") c = "\t"; else if (c == "n") c = "\n"; else if (c == "r") c = "\r"; else if (c == "f") c = "\f"
        } else { raw = c }
        u[++n] = c; r[n] = raw
      }
      out = ""; count = 0; written = 0; tok = ""; norm = ""; esc = 0; inopts = 0
      for (k = 1; k <= n + 1; k++) {
        if (k <= n && !(u[k] == ";" && !esc)) {
          tok = tok r[k]
          if (esc) { if (!inopts) norm = norm u[k]; esc = 0 }
          else if (u[k] == "^") esc = 1
          else if (u[k] == "|") inopts = 1
          else if (!inopts) norm = norm u[k]
          continue
        }
        if (tok != "") {
          gsub(/^[ \t]+|[ \t]+$/, "", norm)
          if (is_ark(norm) && (scope == "all" || is_this_host(norm)) && !(mode == "add" && norm == token)) {
            report("removed", norm)
          } else {
            if (norm == token) { written = 1; report("kept", norm) } else report("kept", norm)
            out = (count++ ? out ";" : "") tok
          }
        }
        tok = ""; norm = ""; esc = 0; inopts = 0
      }
      if (mode == "add" && !written) { out = (count++ ? out ";" : "") token; report("added", token) }
      return out
    }
    {
      line = $0; cr = ""
      if (substr(line, length(line), 1) == "\r") { cr = "\r"; line = substr(line, 1, length(line) - 1) }
      if (!continued && match(line, /^[ \t]*authcert\.override([ \t]*[=:][ \t]*|[ \t]+|$)/)) {
        if (ends_continued(line)) { abort = 10; exit }
        if (seen++) { abort = 11; exit }
        prefix = substr(line, 1, RLENGTH); value = substr(line, RLENGTH + 1)
        if (prefix !~ /[=:][ \t]*$/) { sub(/[ \t]*$/, "", prefix); prefix = prefix "=" }
        newvalue = rewrite(value)
        if (newvalue != "") lines[++total] = prefix newvalue cr
        continued = 0; next
      }
      if (!continued && match(line, /^[ \t]*trustedRootCert([ \t]*[=:]|[ \t]+|$)/)) trusted = 1
      lines[++total] = $0
      continued = (line !~ /^[ \t]*[#!]/) && ends_continued(line)
    }
    END {
      if (abort) exit abort
      if (!seen && trusted && mode == "add") exit 12
      for (i = 1; i <= total; i++) print lines[i]
      if (!seen && mode == "add") { print "authcert.override=" token; report("added", token) }
    }
  ' "$1"
}

TOKEN="$TRUST_FILE"
HOST_PREFIX="ark-${ARK_SHOP_HOST}-"

report_entries() { # file
  local tmp="$WORK/report"
  edit_properties "$1" remove "/nonexistent" "$TRUST_DIR" "\0never" host 3>"$tmp" >/dev/null || true
  local found_this=0 others=0 ark_other=0
  while IFS=$'\t' read -r _ path; do
    [ -n "$path" ] || continue
    if [ "$path" = "$TOKEN" ]; then found_this=1
    elif [[ "$path" == "$TRUST_DIR/$HOST_PREFIX"* ]]; then ark_other=$((ark_other + 1)); note "Older ARK certificate for this shop: $path"
    elif [[ "$path" == "$TRUST_DIR/ark-"* ]]; then note "ARK certificate for another shop: $path"
    else others=$((others + 1)); note "Trust root of another application (kept): $path"
    fi
  done < "$tmp"
  if [ "$found_this" = 1 ] && [ -f "$TOKEN" ] && [ "$(fingerprint "$TOKEN" sha256)" = "$ARK_ROOT_SHA256" ]; then
    ok "QZ Tray is set to trust this shop's ARK certificate."
    return 0
  fi
  problem "QZ Tray is not yet set to trust this shop's ARK certificate."
  return 1
}

qz_pids() { pgrep -u "$(id -u "$CONSOLE_USER" 2>/dev/null || echo 0)" -f 'qz-tray\.jar' 2>/dev/null || true; }

if [ "$MODE" = status ]; then
  report_entries "$PROPS"; status=$?
  if [ -n "$(qz_pids)" ]; then ok "QZ Tray is running."; else note "QZ Tray is not running."; fi
  exit "$status"
fi

if [ "$REQUIRE_ROOT" = 1 ] && [ "$(id -u)" != 0 ]; then
  stop 7 "This step needs administrator rights. Run it as: sudo bash \"$0\"$( [ "$MODE" = uninstall ] && printf ' --uninstall')"
fi

# --- Change the settings -----------------------------------------------------------------------------
if [ "$MODE" = install ]; then
  mkdir -p "$TRUST_DIR" || stop 1 "Could not create $TRUST_DIR."
  if [ "$REQUIRE_ROOT" = 1 ]; then chown root:wheel "$TRUST_DIR" "$(dirname "$TRUST_DIR")" 2>/dev/null; fi
  chmod 755 "$TRUST_DIR"
  if [ ! -f "$TRUST_FILE" ] || ! cmp -s "$WORK/root.crt" "$TRUST_FILE"; then
    cp "$WORK/root.crt" "$TRUST_FILE.tmp" && chmod 644 "$TRUST_FILE.tmp" && mv -f "$TRUST_FILE.tmp" "$TRUST_FILE" \
      || stop 1 "Could not write $TRUST_FILE."
    if [ "$REQUIRE_ROOT" = 1 ]; then chown root:wheel "$TRUST_FILE"; fi
  fi
  ok "Certificate stored: $TRUST_FILE"
  SCOPE_MODE=add
else
  SCOPE_MODE=remove
fi

edit_properties "$PROPS" "$SCOPE_MODE" "$TOKEN" "$TRUST_DIR" "$HOST_PREFIX" host 3>"$WORK/changes" >"$WORK/qz-tray.properties"
case $? in
  0) ;;
  10) stop 8 "QZ Tray's authcert.override setting continues over several lines. ARK will not change it automatically; contact ARK support." ;;
  11) stop 8 "QZ Tray's settings contain authcert.override more than once. ARK will not change them automatically; contact ARK support." ;;
  12) stop 8 "QZ Tray's settings use a trustedRootCert entry. ARK will not change them automatically; contact ARK support." ;;
  *) stop 1 "Could not read $PROPS." ;;
esac

changed=0
while IFS=$'\t' read -r kind path; do
  case "$kind" in
    added) changed=1; ok "Added this shop's ARK certificate to QZ Tray's trusted roots." ;;
    removed) changed=1
      if [ "$MODE" = install ]; then note "Replaced ARK's previous certificate for this shop: $path"; else ok "Removed: $path"; fi
      rm -f "$path" ;;
    kept) [ "$path" = "$TOKEN" ] || note "Kept: $path" ;;
  esac
done < "$WORK/changes"

if [ "$changed" = 1 ]; then
  backup="$PROPS.ark-backup-$(date -u +%Y%m%dT%H%M%SZ)-$$"
  cp -p "$PROPS" "$backup" || stop 1 "Could not back up $PROPS. Nothing was changed."
  ok "Backup of the previous settings: $backup"
  # Keep the five most recent ARK backups.
  ls -1t "$PROPS".ark-backup-* 2>/dev/null | tail -n +6 | while IFS= read -r old; do rm -f "$old"; done
  if stat -c '%a' / >/dev/null 2>&1; then
    owner="$(stat -c '%u:%g' "$PROPS")"; perms="$(stat -c '%a' "$PROPS")"
  else
    owner="$(stat -f '%u:%g' "$PROPS")"; perms="$(stat -f '%Lp' "$PROPS")"
  fi
  cp "$WORK/qz-tray.properties" "$PROPS.ark-new" && chmod "$perms" "$PROPS.ark-new" \
    && { [ "$REQUIRE_ROOT" != 1 ] || chown "$owner" "$PROPS.ark-new"; } && mv -f "$PROPS.ark-new" "$PROPS" \
    || { rm -f "$PROPS.ark-new"; stop 1 "Could not update $PROPS. The previous settings are unchanged."; }
  ok "QZ Tray settings updated."
else
  if [ "$MODE" = install ]; then ok "Already set up. Nothing to change."; else ok "ARK's entry for this shop was not present. Nothing to change."; fi
fi

if [ "$MODE" = uninstall ]; then
  rmdir "$TRUST_DIR" 2>/dev/null || true
fi

# --- Restart QZ Tray so it reads the change, then confirm it loaded the certificate --------------------
QZ_LOG="$QZ_USER_DIR/debug.log"
if [ "$NO_RESTART" = 1 ]; then
  note "QZ Tray was not restarted (ARK_NO_RESTART=1)."
elif [ "$changed" = 1 ] || [ -z "$(qz_pids)" ]; then
  log_lines=0; [ -f "$QZ_LOG" ] && log_lines="$(wc -l < "$QZ_LOG" | tr -d ' ')"
  if [ -n "$(qz_pids)" ]; then
    say ""; say "Restarting QZ Tray..."
    pkill -TERM -u "$(id -u "$CONSOLE_USER")" -f 'qz-tray\.jar' 2>/dev/null || true
    for _ in 1 2 3 4 5 6 7 8 9 10; do [ -z "$(qz_pids)" ] && break; sleep 1; done
  else
    say ""; say "Starting QZ Tray..."
  fi
  if [ "$(id -u)" = 0 ]; then
    launchctl asuser "$(id -u "$CONSOLE_USER")" sudo -u "$CONSOLE_USER" open -a "$QZ_APP" >/dev/null 2>&1 || true
  else
    open -a "$QZ_APP" >/dev/null 2>&1 || true
  fi
  if [ "$MODE" = install ]; then
    loaded=""
    for _ in $(seq 1 45); do
      sleep 1
      [ -f "$QZ_LOG" ] || continue
      fresh="$(tail -n +"$((log_lines + 1))" "$QZ_LOG" 2>/dev/null)"
      if printf '%s\n' "$fresh" | grep -iF "($ARK_ROOT_SHA1)" | grep -qF 'Adding CA certificate'; then loaded=yes; break; fi
      if printf '%s\n' "$fresh" | grep -qF "Error loading CA cert: $TRUST_FILE"; then loaded=error; break; fi
    done
    case "$loaded" in
      yes) ok "QZ Tray loaded this shop's ARK certificate." ;;
      error) stop 1 "QZ Tray could not load the certificate. Run this setup again; if it repeats, contact ARK support with QZ Tray's log ($QZ_LOG)." ;;
      *) note "QZ Tray has not confirmed the certificate yet. Continue in ARK; if printing asks to Allow, run this setup again." ;;
    esac
  fi
fi

say ""
if [ "$MODE" = install ]; then
  say "Done. Return to ARK's Set Up Printing page, click Check connection, then print a test label:"
  say "  $ARK_SETUP_URL"
else
  say "Done. ARK's certificate for $ARK_SHOP_HOST is no longer trusted by QZ Tray on this Mac."
fi
exit 0
