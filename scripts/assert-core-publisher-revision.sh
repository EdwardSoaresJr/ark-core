#!/usr/bin/env bash
# The scripts that publish an image must come from origin/main.
# The archived source commit is named separately and does not have to be that checkout.
set -euo pipefail

repo=""
publisher=""
source=""
remote="origin"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) repo="$2"; shift 2 ;;
    --publisher) publisher="$2"; shift 2 ;;
    --source) source="$2"; shift 2 ;;
    --remote) remote="$2"; shift 2 ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

if [[ -z "$repo" || -z "$publisher" || -z "$source" ]]; then
  echo "Usage: assert-core-publisher-revision.sh --repo ROOT --publisher SHA --source SHA [--remote origin]" >&2
  exit 2
fi

if [[ ! "$remote" =~ ^[A-Za-z0-9._/-]+$ ]]; then
  echo "REFUSING: remote name is not usable." >&2
  exit 1
fi

if ! publisher_sha="$(git -C "$repo" rev-parse --verify "${publisher}^{commit}" 2>/dev/null)"; then
  echo "REFUSING: publisher revision is not a commit: $publisher" >&2
  exit 1
fi

if ! source_sha="$(git -C "$repo" rev-parse --verify "${source}^{commit}" 2>/dev/null)"; then
  echo "REFUSING: SOURCE_COMMIT is not a commit in this repository: $source" >&2
  exit 1
fi

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

if ! git -C "$repo" merge-base --is-ancestor "$publisher_sha" "$main_ref"; then
  echo "REFUSING: publisher revision ${publisher_sha} is not on ${main_ref}." >&2
  echo "Run publication from the protected publisher line. Do not check out the source commit to publish it." >&2
  exit 1
fi

echo "Publisher revision ok"
echo "  publisher: ${publisher_sha}"
echo "  source: ${source_sha}"
