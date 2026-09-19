#!/usr/bin/env bash
set -euo pipefail

root="$(git rev-parse --show-toplevel)"
remote="$(git remote get-url origin 2>/dev/null || true)"

canonical_remote_ok=0
case "$remote" in
  *github.com/EdwardSoaresJr/ark.git|*github.com:EdwardSoaresJr/ark.git)
    canonical_remote_ok=1
    ;;
esac

if [[ "$canonical_remote_ok" -ne 1 ]]; then
  echo "This is not the canonical public ARK Core repository." >&2
  echo "Git root: $root" >&2
  echo "origin:   ${remote:-<none>}" >&2
  echo "Expected origin: https://github.com/EdwardSoaresJr/ark.git" >&2
  exit 1
fi

echo "Canonical Core repository: $root"
echo "origin: $remote"
