#!/usr/bin/env bash
# Confirm this checkout is public ARK Core (origin + Git root).
# Any branch in this repository is valid. Folder names are not identity.
set -euo pipefail

if ! git rev-parse --show-toplevel >/dev/null 2>&1; then
  echo "Stopped: this is not a Git checkout." >&2
  echo "Canonical Core: https://github.com/EdwardSoaresJr/ark.git" >&2
  exit 1
fi

root="$(git rev-parse --show-toplevel)"
remote="$(git remote get-url origin 2>/dev/null || true)"
branch="$(git branch --show-current 2>/dev/null || true)"
root_base="$(basename "$root")"

report() {
  echo "Git root: $root" >&2
  echo "origin:   ${remote:-<none>}" >&2
  echo "branch:   ${branch:-<detached>}" >&2
  echo "Expected origin: https://github.com/EdwardSoaresJr/ark-core.git" >&2
}

case "$remote" in
  *github.com/EdwardSoaresJr/arksmsv2.git|*github.com:EdwardSoaresJr/arksmsv2.git)
    echo "Stopped: this workspace is the legacy private repository (arksmsv2)." >&2
    report
    echo "Shared Core changes belong in public ARK. Do not edit Core here." >&2
    exit 1
    ;;
  *github.com/EdwardSoaresJr/ark-platform.git|*github.com:EdwardSoaresJr/ark-platform.git|*github.com/EdwardSoaresJr/ark-cloud.git|*github.com:EdwardSoaresJr/ark-cloud.git)
    echo "Stopped: this workspace is ARK Platform, not Core." >&2
    report
    echo "Platform work stays here. Shared Core changes belong in public ARK." >&2
    exit 1
    ;;
esac

canonical_remote_ok=0
case "$remote" in
  *github.com/EdwardSoaresJr/ark.git|*github.com:EdwardSoaresJr/ark.git|\
  *github.com/EdwardSoaresJr/ark-core.git|*github.com:EdwardSoaresJr/ark-core.git)
    canonical_remote_ok=1
    ;;
esac

if [[ "$canonical_remote_ok" -ne 1 ]]; then
  echo "This is not the canonical public ARK Core repository." >&2
  report
  exit 1
fi

case "$root_base" in
  arksmsv2|ark-cloud)
    echo "Stopped: Git root directory is ${root_base}, not public Core." >&2
    report
    echo "Open the public ARK checkout (origin EdwardSoaresJr/ark.git)." >&2
    exit 1
    ;;
esac

echo "Canonical Core repository: $root"
echo "origin: $remote"
echo "branch: ${branch:-<detached>}"
