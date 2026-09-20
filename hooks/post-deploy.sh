#!/usr/bin/env bash
# Stable ReleasePanel entry (copy or symlink to production/hooks/post-deploy.sh on the VPS).
set -euo pipefail

release="${RELEASEPANEL_ACTIVATED_RELEASE:-${RELEASEPANEL_DEPLOY_RELEASE:-}}"
base="${RELEASEPANEL_BASE:-}"

if [[ -z "${base}" && -n "${release}" ]]; then
    base="$(cd "${release}/.." && pwd)"
fi

shared_script="${base}/shared/infra/releasepanel/post-deploy.sh"
release_script="${release}/infra/releasepanel/post-deploy.sh"

if [[ -z "${release}" || ! -d "${release}" ]]; then
    printf '%s\n' '[arksmsv2] post-deploy: release path missing.' >&2
    exit 1
fi

if [[ -f "${release_script}" ]]; then
    export RELEASEPANEL_BASE="${base}"
    export RELEASEPANEL_ACTIVATED_RELEASE="${release}"
    exec bash "${release_script}"
fi

if [[ -f "${shared_script}" ]]; then
    export RELEASEPANEL_BASE="${base}"
    export RELEASEPANEL_ACTIVATED_RELEASE="${release}"
    exec bash "${shared_script}"
fi

printf '%s\n' "[arksmsv2] post-deploy: missing ${release_script}" >&2
exit 1
