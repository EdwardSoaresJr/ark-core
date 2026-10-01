#!/usr/bin/env bash
# Read-only checks for both public hosts after Core is routed.
# Does not change Traefik, Foundry, or the database.
#
# Production lead tests, if a later pass needs one:
# - use a dedicated test number that has no customer and no phone capability row;
# - do not overwrite an existing phone capability row to skip verification;
# - prefer a no-send session fixture over a real SMS or email;
# - delete only the lead, conversation, and capability row that the test created.
# This script does not submit a lead.
set -euo pipefail

fail() {
  echo "FAIL $*" >&2
  exit 1
}

body_has() {
  local pattern="$1"
  local content="$2"
  grep -q -F -- "$pattern" <<<"$content"
}

http_status() {
  local url="$1"
  local headers
  headers="$(mktemp)"
  curl -fsS -o /dev/null -D "$headers" "$url" || {
    rm -f "$headers"
    return 1
  }
  head -n 1 "$headers"
  rm -f "$headers"
}

asset_status() {
  local status
  status="$(http_status "$1")" || return 1
  grep -q '200' <<<"$status"
}

redirect_status() {
  local status
  status="$(http_status "$1")" || return 1
  grep -q "$2" <<<"$status"
}

same_origin_asset() {
  local base="$1"
  local kind="$2"
  local content="$3"
  local escaped
  escaped="$(printf '%s' "$base" | sed -E 's/[][(){}.+*?^$|\\]/\\&/g')"
  grep -oE "${escaped}/(build|assets)/[^\"']+\\.${kind}([^A-Za-z0-9]|$)" <<<"$content" \
    | head -n 1 \
    | sed -E 's/[^A-Za-z0-9]$//'
}

if [[ "${1:-}" == "--self-check" ]]; then
  sample="$(printf 'x%.0s' {1..400})"
  sample+=$'<link rel="canonical" href="https://lugsnplugs.com/">\n'
  sample+=' href="https://lugsnplugs.com/assets/ARK_SMS_FINAL_DROP_IN_PACK/manifest.json"'
  sample+=' src="https://lugsnplugs.com/build/assets/app-example.js"'
  body_has 'rel="canonical" href="https://lugsnplugs.com/"' "$sample" || fail "self-check canonical"
  js="$(same_origin_asset "https://lugsnplugs.com" js "$sample")"
  [[ "$js" == "https://lugsnplugs.com/build/assets/app-example.js" ]] || fail "self-check script matched ${js}"
  if grep -q -F -- 'rel="canonical" href="https://lugsnplugs.com/"' "$sample" 2>/dev/null; then
    fail "self-check still treats the body as a filename"
  fi
  echo "self-check ok"
  exit 0
fi

NATIVE="${1:-https://lugsnplugs.arksms.com}"
CUSTOM="${2:-https://lugsnplugs.com}"
DTC=(p0016 p0101 p0128 p0135 p0174 p0300 p0301 p0302 p0303 p0304 p0340 p0401 p0420 p0430 p0442 p0455)

check_host() {
  local base="$1"
  local body headers js css problem repairpal warranty
  echo "== ${base}"

  headers="$(mktemp)"
  body="$(curl -fsS -D "$headers" "${base}/")" || fail "${base}/ did not return 200"
  grep -qi '^X-ARK-Website: core' "$headers" || fail "${base}/ missing X-ARK-Website: core"
  body_has 'rel="canonical" href="https://lugsnplugs.com/"' "$body" || fail "${base}/ canonical is not lugsnplugs.com"
  body_has 'LugsNPlugs' "$body" || fail "${base}/ missing shop name"
  body_has 'ark_display_theme' "$body" || fail "${base}/ missing theme cookie contract"
  body_has 'data-public-surface-theme-toggle' "$body" || fail "${base}/ missing theme control"
  rm -f "$headers"

  js="$(same_origin_asset "$base" js "$body" || true)"
  css="$(same_origin_asset "$base" css "$body" || true)"
  [[ -n "$js" ]] || fail "${base} homepage has no same-origin script"
  [[ -n "$css" ]] || fail "${base} homepage has no same-origin stylesheet"
  asset_status "$js" || fail "script ${js}"
  asset_status "$css" || fail "stylesheet ${css}"
  [[ "$js" != https://lugsnplugs.com/* || "$base" == https://lugsnplugs.com ]] || true
  case "$js" in
    "${base}"/*) ;;
    *) fail "script is not same-origin: ${js}" ;;
  esac

  local book
  book="$(curl -fsS "${base}/book?concern=Brakes")" || fail "${base}/book"
  grep -q 'Request an appointment' <<<"$book" || fail "${base}/book is not an appointment request"
  grep -q 'does not reserve a bay' <<<"$book" || fail "${base}/book missing request language"
  grep -q 'value="Brakes"' <<<"$book" || fail "${base}/book did not prefill Brakes"

  curl -fsS -o /dev/null "${base}/common-problems/check-engine-light" || fail "symptom page"
  problem="$(curl -fsS "${base}/common-problems/p0420")" || fail "p0420"
  body_has 'P0420' "$problem" || fail "p0420"
  local code
  for code in "${DTC[@]}"; do
    curl -fsS -o /dev/null "${base}/common-problems/${code}" || fail "DTC ${code}"
  done

  repairpal="$(curl -fsS "${base}/repairpal-warranty")" || fail "repairpal warranty"
  body_has '12 months / 12,000 miles' "$repairpal" || fail "repairpal warranty"
  warranty="$(curl -fsS "${base}/warranty")" || fail "shop warranty"
  body_has '24 months / 24,000 miles' "$warranty" || fail "shop warranty"

  local sitemap robots llms
  sitemap="$(curl -fsS "${base}/sitemap.xml")" || fail "sitemap"
  grep -q 'https://lugsnplugs.com/' <<<"$sitemap" || fail "sitemap missing canonical host"
  grep -q 'lugsnplugs.arksms.com' <<<"$sitemap" && fail "sitemap lists the native host" || true
  grep -q 'https://lugsnplugs.com/llms.txt' <<<"$sitemap" || fail "sitemap missing llms.txt"
  robots="$(curl -fsS "${base}/robots.txt")" || fail "robots"
  grep -q 'Sitemap: https://lugsnplugs.com/sitemap.xml' <<<"$robots" || fail "robots sitemap line"
  llms="$(curl -fsS "${base}/llms.txt")" || fail "llms.txt"
  grep -q 'Canonical: https://lugsnplugs.com/' <<<"$llms" || fail "llms canonical"
  grep -q 'https://lugsnplugs.com/book' <<<"$llms" || fail "llms book url"
  body_has '"@type":"AutoRepair"' "$body" || fail "JSON-LD type"
  body_has 'LugsNPlugs' "$body" || fail "JSON-LD shop name"

  curl -fsS -o /dev/null "${base}/portal/access" || fail "portal access"
  redirect_status "${base}/appointment?concern=Brakes" "301" || fail "legacy appointment redirect"

  echo "ok ${base}"
}

check_host "$NATIVE"
check_host "$CUSTOM"

echo
echo "Theme persistence is client-side. In a browser on each host:"
echo "  1. Choose light. Reload. The page stays light."
echo "  2. Choose dark. Reload. The page stays dark."
echo "  Cookie name: ark_display_theme. Storage key: ark-customer-theme."
echo
echo "Lead check is off."
echo "A later production lead test needs its own contact, a no-send path, and cleanup of only the rows it created."
echo "Do not reuse a customer phone or edit an existing phone capability row."
