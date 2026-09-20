#!/usr/bin/env bash
# Append labor guide vars to an existing .env if they are not already set.
set -euo pipefail

env_file="${1:-}"

if [[ -z "${env_file}" ]]; then
    printf '%s\n' 'Usage: ensure-labor-guide-env.sh /path/to/.env' >&2
    exit 1
fi

if [[ ! -f "${env_file}" ]]; then
    printf '%s\n' "Missing env file: ${env_file}" >&2
    exit 1
fi

if grep -q '^LABOR_GUIDE_ALLDATA_URL=' "${env_file}"; then
    printf '%s\n' "Labor guide vars already present in ${env_file}"
    exit 0
fi

cat >> "${env_file}" <<'EOF'

# Labor guides (browser launch — set URLs to your shop login entry points)
LABOR_GUIDE_ALLDATA_URL=https://my.alldata.com/repair
LABOR_GUIDE_ALLDATA_LOGIN_PATH=
LABOR_GUIDE_PRODEMAND_URL=https://www.prodemand.com
LABOR_GUIDE_PRODEMAND_LOGIN_PATH=
EOF

printf '%s\n' "Labor guide vars appended to ${env_file}"
