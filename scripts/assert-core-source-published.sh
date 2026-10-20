#!/usr/bin/env bash
# Authorize one source commit for image publication.
# A tag names the commit. The release record on origin/release-authorization authorizes it.
# The tag alone is not authorization. This script does not deploy.
set -euo pipefail

for bypass in ARK_SKIP_RELEASE_CHECKS ARK_ALLOW_UNPUBLISHED_SOURCE ARK_RELEASE_BYPASS CORE_RELEASE_LOCAL_RECORD SKIP_RELEASE_APPROVAL; do
  if [[ -n "${!bypass:-}" ]]; then
    echo "REFUSING: ${bypass} cannot authorize publication." >&2
    exit 1
  fi
done

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

origin_url="$(git -C "$repo" config --get "remote.${remote}.url")"
if [[ "$origin_url" =~ ^https://github.com/([^/]+)/([^/.]+)(\.git)?$ ]] || [[ "$origin_url" =~ ^git@github.com:([^/]+)/([^/.]+)(\.git)?$ ]]; then
  origin_repository="${BASH_REMATCH[1]}/${BASH_REMATCH[2]}"
else
  echo "REFUSING: publication origin is not a GitHub repository URL." >&2
  exit 1
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if ! git -C "$repo" fetch "$remote" "refs/heads/release-authorization:refs/remotes/${remote}/release-authorization"; then
  echo "REFUSING: origin/release-authorization is not available." >&2
  exit 1
fi

tag_ref="refs/ark-verify/core-release/${sha}"
if ! git -C "$repo" fetch "$remote" "refs/tags/core-release/${sha}:${tag_ref}"; then
  echo "REFUSING: core-release/${sha} is not on ${remote}." >&2
  exit 1
fi

tagged="$(git -C "$repo" rev-parse --verify "${tag_ref}^{commit}")"
if [[ "$tagged" != "$sha" ]]; then
  echo "REFUSING: core-release/${sha} points at ${tagged}." >&2
  exit 1
fi

record_path="releases/${sha}.json"
if ! record_file="$(git -C "$repo" show "${remote}/release-authorization:${record_path}" 2>/dev/null)"; then
  echo "REFUSING: ${remote}/release-authorization has no ${record_path}." >&2
  exit 1
fi

record_tmp="$(mktemp "${TMPDIR:-/tmp}/ark-release-record-XXXXXX")"
trap 'rm -f "$record_tmp"' EXIT
printf '%s\n' "$record_file" > "$record_tmp"

fields="$(php "$script_dir/core-release-authorize.php" record "$record_tmp")"
source_sha=""
baseline_sha=""
diff_sha256=""
repository=""
pull_request=""
while IFS='=' read -r key value; do
  case "$key" in
    source_sha) source_sha="$value" ;;
    baseline_sha) baseline_sha="$value" ;;
    diff_sha256) diff_sha256="$value" ;;
    repository) repository="$value" ;;
    pull_request) pull_request="$value" ;;
    *)
      echo "REFUSING: unexpected release record field." >&2
      exit 1
      ;;
  esac
done <<< "$fields"

if [[ -z "$source_sha" || -z "$baseline_sha" || -z "$diff_sha256" || -z "$repository" || -z "$pull_request" ]]; then
  echo "REFUSING: the release record is incomplete." >&2
  exit 1
fi

if [[ "$source_sha" != "$sha" ]]; then
  echo "REFUSING: release record source ${source_sha} is not ${sha}." >&2
  exit 1
fi

if [[ "$repository" != "$origin_repository" ]]; then
  echo "REFUSING: release record repository ${repository} is not ${origin_repository}." >&2
  exit 1
fi

if ! git -C "$repo" cat-file -e "${baseline_sha}^{commit}" 2>/dev/null; then
  echo "REFUSING: baseline ${baseline_sha} is not in this repository." >&2
  exit 1
fi

if ! git -C "$repo" merge-base --is-ancestor "$baseline_sha" "$sha"; then
  echo "REFUSING: baseline ${baseline_sha} is not an ancestor of ${sha}." >&2
  exit 1
fi

actual_diff="$("$script_dir/core-release-diff-id.sh" --repo "$repo" --baseline "$baseline_sha" --source "$sha")"
if [[ "$actual_diff" != "$diff_sha256" ]]; then
  echo "REFUSING: source diff ${actual_diff} does not match the approved diff ${diff_sha256}." >&2
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "REFUSING: gh is required to verify checks and approval." >&2
  exit 1
fi

checks_json="$(gh api "repos/${repository}/commits/${sha}/check-runs?per_page=100")"
printf '%s' "$checks_json" | php "$script_dir/core-release-authorize.php" checks "$sha"

pull_json="$(gh api "repos/${repository}/pulls/${pull_request}")"
reviews_json="$(gh api "repos/${repository}/pulls/${pull_request}/reviews")"
tip_blob="$(git -C "$repo" rev-parse "${remote}/release-authorization:${record_path}")"
review_commits="$(printf '%s' "$reviews_json" | php -r '
$reviews = json_decode(stream_get_contents(STDIN), true);
if (! is_array($reviews)) { exit(0); }
$seen = [];
foreach ($reviews as $review) {
    if (! is_array($review) || ($review["state"] ?? "") !== "APPROVED") { continue; }
    $commit = (string) ($review["commit_id"] ?? "");
    if ($commit !== "" && preg_match("/\\A[a-f0-9]{40}\\z/", $commit) === 1) {
        $seen[$commit] = true;
    }
}
foreach (array_keys($seen) as $commit) {
    fwrite(STDOUT, $commit."\n");
}
')"

blob_pairs=""
while IFS= read -r review_commit; do
  [[ -z "$review_commit" ]] && continue
  if ! review_blob="$(git -C "$repo" rev-parse "${review_commit}:${record_path}" 2>/dev/null)"; then
    echo "REFUSING: approved review ${review_commit} is not in the release-authorization history." >&2
    exit 1
  fi
  blob_pairs="${blob_pairs}\"${review_commit}\":\"${review_blob}\","
done <<< "$review_commits"

approval_json="$(php -r '
$pull = json_decode($argv[1], true);
$reviews = json_decode($argv[2], true);
$blobs = json_decode("{".rtrim($argv[3], ",")."}", true);
echo json_encode([
    "pull" => $pull,
    "reviews" => $reviews,
    "tip_blob" => $argv[4],
    "review_blobs" => is_array($blobs) ? $blobs : new stdClass(),
]);
' "$pull_json" "$reviews_json" "$blob_pairs" "$tip_blob")"

printf '%s' "$approval_json" | php "$script_dir/core-release-authorize.php" approval

echo "Source publication authorized"
echo "  source: ${sha}"
echo "  baseline: ${baseline_sha}"
echo "  diff: ${diff_sha256}"
echo "  production deployment: not authorized"
