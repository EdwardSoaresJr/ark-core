#!/usr/bin/env bash
# Read-only checks for both public hosts after Core is routed.
# Does not change Traefik, Foundry, or the database.
# A lead is submitted only when WEBSITE_CUTOVER_SUBMIT_LEAD=yes.
set -euo pipefail

NATIVE="${1:-https://lugsnplugs.arksms.com}"
CUSTOM="${2:-https://lugsnplugs.com}"
DTC=(p0016 p0101 p0128 p0135 p0174 p0300 p0301 p0302 p0303 p0304 p0340 p0401 p0420 p0430 p0442 p0455)

fail() {
  echo "FAIL $*" >&2
  exit 1
}

check_host() {
  local base="$1"
  local body headers js css
  echo "== ${base}"

  headers="$(mktemp)"
  body="$(curl -fsS -D "$headers" "${base}/")" || fail "${base}/ did not return 200"
  grep -qi '^X-ARK-Website: core' "$headers" || fail "${base}/ missing X-ARK-Website: core"
  grep -q 'rel="canonical" href="https://lugsnplugs.com/"' "$body" || fail "${base}/ canonical is not lugsnplugs.com"
  grep -q 'LugsNPlugs' "$body" || fail "${base}/ missing shop name"
  grep -q 'ark_display_theme' "$body" || fail "${base}/ missing theme cookie contract"
  grep -q 'data-public-surface-theme-toggle' "$body" || fail "${base}/ missing theme control"
  rm -f "$headers"

  js="$(grep -oE "${base}/(build|assets)/[^\"']+\.js" <<<"$body" | head -n 1 || true)"
  css="$(grep -oE "${base}/(build|assets)/[^\"']+\.css" <<<"$body" | head -n 1 || true)"
  [[ -n "$js" ]] || fail "${base} homepage has no same-origin script"
  [[ -n "$css" ]] || fail "${base} homepage has no same-origin stylesheet"
  curl -fsS -o /dev/null -D - "$js" | head -n 1 | grep -q '200' || fail "script ${js}"
  curl -fsS -o /dev/null -D - "$css" | head -n 1 | grep -q '200' || fail "stylesheet ${css}"
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
  curl -fsS "${base}/common-problems/p0420" | grep -q 'P0420' || fail "p0420"
  local code
  for code in "${DTC[@]}"; do
    curl -fsS -o /dev/null "${base}/common-problems/${code}" || fail "DTC ${code}"
  done

  curl -fsS "${base}/repairpal-warranty" | grep -q '12 months / 12,000 miles' || fail "repairpal warranty"
  curl -fsS "${base}/warranty" | grep -q '24 months / 24,000 miles' || fail "shop warranty"

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
  grep -q '"@type":"AutoRepair"' <<<"$body" || fail "JSON-LD type"
  grep -q 'LugsNPlugs' <<<"$body" || fail "JSON-LD shop name"

  curl -fsS -o /dev/null "${base}/portal/access" || fail "portal access"
  curl -fsS -o /dev/null -D - "${base}/appointment?concern=Brakes" | grep -q '301' || fail "legacy appointment redirect"

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
echo "After cutover, one controlled request, then delete that lead:"
echo "  WEBSITE_CUTOVER_SUBMIT_LEAD=yes $0"
echo "Normal page renders above did not call Platform or Foundry; confirm in the host logs if needed."

if [[ "${WEBSITE_CUTOVER_SUBMIT_LEAD:-}" == "yes" ]]; then
  echo "Submitting one website lead to ${CUSTOM}/leads"
  curl -fsS -c /tmp/ark-cutover-cookies -b /tmp/ark-cutover-cookies "${CUSTOM}/book" >/dev/null
  token="$(curl -fsS -c /tmp/ark-cutover-cookies -b /tmp/ark-cutover-cookies "${CUSTOM}/book" | sed -n 's/.*name=\"_token\" value=\"\([^\"]*\)\".*/\1/p' | head -n 1)"
  [[ -n "$token" ]] || fail "could not read form token"
  curl -fsS -c /tmp/ark-cutover-cookies -b /tmp/ark-cutover-cookies \
    -o /dev/null -D - \
    -X POST "${CUSTOM}/leads" \
    -H 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "_token=${token}" \
    --data-urlencode "page=contact" \
    --data-urlencode "contact_name=Cutover Check" \
    --data-urlencode "contact_phone=7195550199" \
    --data-urlencode "concern=Cutover acceptance check. Delete this lead." \
    | head -n 1 | grep -Eq '302|303' || fail "lead was not accepted"
  echo "Lead submitted. Delete the Cutover Check lead in the shop before leaving it."
fi
