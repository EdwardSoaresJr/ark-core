#!/usr/bin/env bash
# Verify ARK ecosystem favicon wiring on operational surfaces (read-only HTTP checks).
set -euo pipefail

pass=0
fail=0

check() {
    local name="$1"
    local url="$2"
    local pattern="$3"
    local body
    body="$(curl -sL "$url" 2>/dev/null || true)"
    if echo "$body" | grep -qE "$pattern"; then
        echo "PASS  $name"
        pass=$((pass + 1))
    else
        echo "FAIL  $name ($url)"
        fail=$((fail + 1))
    fi
}

check_http() {
    local name="$1"
    local url="$2"
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' "$url" 2>/dev/null || echo 000)"
    if [[ "$code" == "200" ]]; then
        echo "PASS  $name (HTTP $code)"
        pass=$((pass + 1))
    else
        echo "FAIL  $name (HTTP $code) $url"
        fail=$((fail + 1))
    fi
}

echo "=== ARK V2 (lugsnplugs.arksms.com) ==="
check "ARK V2 favicon.ico in head" "https://lugsnplugs.arksms.com/app/login" 'ARK_SMS_FINAL_DROP_IN_PACK/favicon/favicon\.ico'
check_http "ARK V2 favicon asset" "https://lugsnplugs.arksms.com/assets/ARK_SMS_FINAL_DROP_IN_PACK/favicon/favicon.ico"
check_http "ARK V2 apple-touch" "https://lugsnplugs.arksms.com/assets/ARK_SMS_FINAL_DROP_IN_PACK/ios/ark-180x180.png"

echo ""
echo "=== Arkify (platform.autorepairkeeper.com) ==="
check "Arkify ARK favicon in head" "https://platform.autorepairkeeper.com/login" '/ark/favicon\.ico|ark/favicon'
check_http "Arkify favicon asset" "https://platform.autorepairkeeper.com/ark/favicon.ico"
check_http "Arkify apple-touch" "https://platform.autorepairkeeper.com/ark/ark-180x180.png"

echo ""
echo "=== Public Surface - lugsnplugs.com (shop branding - intentional) ==="
check "Public shop favicon" "https://lugsnplugs.com" 'lugsnplugs-favicon\.png'

echo ""
echo "Result: $pass passed, $fail failed"
[[ "$fail" -eq 0 ]]
