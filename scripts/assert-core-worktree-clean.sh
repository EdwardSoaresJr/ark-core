#!/usr/bin/env bash
# Refuse to publish while tracked or untracked source differs from HEAD.
# Gitignored files are not listed here. The image build uses git archive, so they are excluded.
set -euo pipefail

repo=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) repo="$2"; shift 2 ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

if [[ -z "$repo" ]]; then
  repo="$(cd "$(dirname "$0")/.." && pwd)"
fi

if ! git -C "$repo" diff --quiet || ! git -C "$repo" diff --cached --quiet; then
  echo "REFUSING: working tree has staged/unstaged changes. Commit or stash first." >&2
  git -C "$repo" status -sb >&2
  exit 1
fi

untracked="$(git -C "$repo" ls-files --others --exclude-standard)"
if [[ -n "$untracked" ]]; then
  echo "REFUSING: untracked files are not part of HEAD. Commit them or remove them before publishing." >&2
  printf '%s\n' "$untracked" >&2
  exit 1
fi
