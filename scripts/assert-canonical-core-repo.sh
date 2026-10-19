#!/usr/bin/env bash
# Confirm this checkout is the public ARK Core repository.
# Any branch in this repository is valid. Folder names are not identity.
set -euo pipefail

if ! git rev-parse --show-toplevel >/dev/null 2>&1; then
  echo "Stopped: this is not a Git checkout." >&2
  echo "Canonical Core: https://github.com/EdwardSoaresJr/ark-core.git" >&2
  exit 1
fi

root="$(git rev-parse --show-toplevel)"
remote="$(git remote get-url origin 2>/dev/null || true)"
branch="$(git branch --show-current 2>/dev/null || true)"

report() {
  echo "Git root: $root" >&2
  echo "origin:   ${remote:-<none>}" >&2
  echo "branch:   ${branch:-<detached>}" >&2
  echo "Expected origin: https://github.com/EdwardSoaresJr/ark-core.git" >&2
}

case "$remote" in
  *github.com/EdwardSoaresJr/ark-core.git|*github.com:EdwardSoaresJr/ark-core.git)
    ;;
  *)
    echo "This is not the canonical public ARK Core repository." >&2
    report
    echo "Open https://github.com/EdwardSoaresJr/ark-core and make Core changes there." >&2
    exit 1
    ;;
esac

echo "Canonical Core repository: $root"
echo "origin: $remote"
echo "branch: ${branch:-<detached>}"
