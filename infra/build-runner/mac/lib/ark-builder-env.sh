#!/usr/bin/env bash
# Shared paths for replaceable ARK build runner (source, do not execute).
ARK_HOME="${ARK_HOME:-$HOME/ARK}"
ARK_BUILD_CACHE="${ARK_BUILD_CACHE:-$ARK_HOME/build-cache}"
RUNNER_DIR="${RUNNER_DIR:-$ARK_HOME/github-runner/ark-build-01}"
RUNNER_NAME="${RUNNER_NAME:-ark-build-01}"
RUNNER_LABELS="${RUNNER_LABELS:-self-hosted,macos,docker,ark-builder,production-builder}"
BUILDX_BUILDER="${BUILDX_BUILDER:-ark-build-01}"

if [[ -f "$ARK_HOME/ark-builder.env" ]]; then
    # shellcheck source=/dev/null
    source "$ARK_HOME/ark-builder.env"
fi

export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1
export DOCKER_DEFAULT_PLATFORM=linux/amd64
