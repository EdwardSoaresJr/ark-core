#!/usr/bin/env bash
# Prove a deployed shop can still load the QZ client and sign a print request.
# Does not print a physical label. Exit 0 only when both checks pass.
set -euo pipefail

base="${1:-}"
if [[ -z "$base" ]]; then
  echo "usage: QZ_SMOKE_COOKIE='laravel_session=...' scripts/qz-label-smoke.sh https://lugsnplugs.arksms.com" >&2
  exit 2
fi

base="${base%/}"
fail=0

check_asset() {
  local path="$1"
  local needle="$2"
  local tmp code
  tmp="$(mktemp)"
  code="$(curl -sS -o "$tmp" -w "%{http_code}" --max-time 20 "$base$path" || true)"
  if [[ "$code" != "200" ]]; then
    echo "FAIL $path http=$code"
    fail=1
  elif ! grep -q "$needle" "$tmp"; then
    echo "FAIL $path missing $needle"
    fail=1
  else
    echo "OK $path"
  fi
  rm -f "$tmp"
}

check_asset "/vendor/qz/qz-tray.js" "_qz.security"
check_asset "/js/ark/qz-tray.js" "_qz.security"
check_asset "/js/ark/ark-qz-key-tag.js" "ArkQzKeyTag"

if [[ -z "${QZ_SMOKE_COOKIE:-}" ]]; then
  echo "FAIL signing endpoint not checked. Set QZ_SMOKE_COOKIE from an admin session." >&2
  exit 1
fi

tmp="$(mktemp)"
code="$(curl -sS -o "$tmp" -w "%{http_code}" --max-time 20 \
  -H "Accept: application/json" \
  -H "Cookie: ${QZ_SMOKE_COOKIE}" \
  "$base/app/api/qz/sign-health" || true)"

if [[ "$code" == "200" ]] && grep -Eq '"status"[[:space:]]*:[[:space:]]*"ok"' "$tmp"; then
  echo "OK /app/api/qz/sign-health"
else
  echo "FAIL /app/api/qz/sign-health http=$code"
  fail=1
fi

if grep -q "BEGIN PRIVATE" "$tmp" || grep -q "PRIVATE KEY" "$tmp"; then
  echo "FAIL sign-health response included key material" >&2
  fail=1
fi
rm -f "$tmp"

if [[ "$fail" -ne 0 ]]; then
  exit 1
fi

echo "QZ label smoke passed. A physical label on LNP is still required."
