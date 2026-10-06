#!/usr/bin/env bash
# Public Core development stays on the sanitized lineage.
# The old development history is archive/pre-public-sanitization-history.
set -euo pipefail

public_root=25d9214435f1d4d680d956b5507537abca91b876
archive_root=809b6cdaa6201c0a9a669effb76de53e475bf5eb
archive_branch=archive/pre-public-sanitization-history

root="$(git rev-parse --show-toplevel)"
branch="$(git -C "$root" branch --show-current 2>/dev/null || true)"

if [[ "$branch" == "$archive_branch" ]]; then
  echo "Stopped: ${archive_branch} is archived history. Develop on main." >&2
  exit 1
fi

if git -C "$root" cat-file -e "${archive_root}^{commit}" 2>/dev/null; then
  if git -C "$root" merge-base --is-ancestor "$archive_root" HEAD; then
    echo "Stopped: HEAD includes the pre-public-sanitization history." >&2
    echo "Develop on main. That history is ${archive_branch}." >&2
    exit 1
  fi
fi

if ! git -C "$root" merge-base --is-ancestor "$public_root" HEAD; then
  echo "Stopped: HEAD is not on the sanitized public lineage (${public_root})." >&2
  exit 1
fi

if [[ "$branch" == "main" ]]; then
  upstream="$(git -C "$root" rev-parse --abbrev-ref --symbolic-full-name main@{upstream} 2>/dev/null || true)"
  if [[ "$upstream" != "origin/main" ]]; then
    echo "Stopped: main must track origin/main (currently: ${upstream:-none})." >&2
    exit 1
  fi
fi

echo "Public lineage ok (${branch:-detached})"
