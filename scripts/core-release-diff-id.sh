#!/usr/bin/env bash
# Identity of the tree diff between a production baseline and a source commit.
# Both publication checks and the release workflow call this so the hash has one definition.
set -euo pipefail

repo=""
baseline=""
source=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) repo="$2"; shift 2 ;;
    --baseline) baseline="$2"; shift 2 ;;
    --source) source="$2"; shift 2 ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

if [[ -z "$repo" || -z "$baseline" || -z "$source" ]]; then
  echo "Usage: core-release-diff-id.sh --repo ROOT --baseline SHA --source SHA" >&2
  exit 2
fi

if ! git -C "$repo" cat-file -e "${baseline}^{commit}" 2>/dev/null \
  || ! git -C "$repo" cat-file -e "${source}^{commit}" 2>/dev/null; then
  echo "REFUSING: baseline or source is not a commit in the repository." >&2
  exit 1
fi

diff_text="$(git -C "$repo" diff-tree -r --format= "$baseline" "$source")"
if command -v sha256sum >/dev/null 2>&1; then
  printf '%s' "$diff_text" | sha256sum | awk '{print $1}'
else
  printf '%s' "$diff_text" | shasum -a 256 | awk '{print $1}'
fi
