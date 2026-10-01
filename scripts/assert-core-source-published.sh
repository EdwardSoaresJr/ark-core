#!/usr/bin/env bash
# A production Core image may only be built from a commit that is already on origin/main.
# Fetches origin. distribution.yaml is not consulted.
set -euo pipefail

repo=""
commit=""
remote="origin"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) repo="$2"; shift 2 ;;
    --commit) commit="$2"; shift 2 ;;
    --remote) remote="$2"; shift 2 ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

if [[ -z "$repo" || -z "$commit" ]]; then
  echo "Usage: assert-core-source-published.sh --repo ROOT --commit SHA [--remote origin]" >&2
  exit 2
fi

if [[ ! "$remote" =~ ^[A-Za-z0-9._/-]+$ ]]; then
  echo "REFUSING: remote name is not usable." >&2
  exit 1
fi

if ! git -C "$repo" rev-parse --verify "${commit}^{commit}" >/dev/null 2>&1; then
  echo "REFUSING: SOURCE_COMMIT is not a commit in this repository: $commit" >&2
  exit 1
fi
sha="$(git -C "$repo" rev-parse --verify "${commit}^{commit}")"

if ! git -C "$repo" remote get-url "$remote" >/dev/null 2>&1; then
  echo "REFUSING: remote ${remote} is not configured." >&2
  exit 1
fi

git -C "$repo" fetch "$remote"

main_ref="${remote}/main"
if ! git -C "$repo" rev-parse --verify "${main_ref}^{commit}" >/dev/null 2>&1; then
  echo "REFUSING: ${main_ref} is not available after fetch." >&2
  exit 1
fi

if ! git -C "$repo" merge-base --is-ancestor "$sha" "$main_ref"; then
  echo "REFUSING: ${sha} is not reachable from ${main_ref}." >&2
  echo "Push the commit to origin/main before publishing a LugsNPlugs Core image." >&2
  exit 1
fi

echo "Published source ok"
echo "  commit: ${sha}"
echo "  ref:    ${main_ref}"
