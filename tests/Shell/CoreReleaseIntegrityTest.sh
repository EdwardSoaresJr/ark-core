#!/usr/bin/env bash
# Release context is the commit.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MATERIALIZE="$ROOT/scripts/materialize-core-release-context.sh"
PUBLISH="$ROOT/infra/build-runner/mac/publish-ghcr-ark.sh"
CLEAN="$ROOT/scripts/assert-core-worktree-clean.sh"
PUBLISHED="$ROOT/scripts/assert-core-source-published.sh"
PUBLISHER="$ROOT/scripts/assert-core-publisher-revision.sh"
IMAGE_REF="$ROOT/scripts/assert-lnp-core-image-ref.sh"
WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/ark-release-integrity-XXXXXX")"
trap 'rm -rf "$WORKDIR"' EXIT

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null

if grep -q 'docker buildx build "${BUILD_ARGS\[@\]}" "\$REPO_ROOT"' "$PUBLISH"; then
  fail "publish script still builds the worktree"
fi
grep -q 'materialize-core-release-context.sh' "$PUBLISH" || fail "publish script does not archive the commit"
grep -q 'assert-core-publisher-revision.sh' "$PUBLISH" || fail "publish script does not keep the publisher revision on origin/main"
grep -q 'assert-core-source-published.sh' "$PUBLISH" || fail "publish script does not require an approved source record"
if grep -q 'SOURCE_COMMIT is not HEAD' "$ROOT/scripts/assert-canonical-core-publish.sh"; then
  fail "publication still requires the archived commit to be the publisher checkout"
fi
grep -q '"\$CONTEXT"' "$PUBLISH" || fail "publish script does not build the archive directory"
grep -q 'REFUSING: Core publish does not push floating tags.' "$PUBLISH" || fail "publish script can still push a floating tag"
if grep -q 'IMAGE}:build-cert' "$PUBLISH"; then
  fail "publish script still tags build-cert"
fi

FIXTURE="$WORKDIR/repo"
git init -b main "$FIXTURE" >/dev/null
mkdir -p "$FIXTURE/public" "$FIXTURE/app"
printf 'tracked\n' > "$FIXTURE/app/a.php"
printf 'console.log(1)\n' > "$FIXTURE/public/js.js"
printf '/public/hot\n/public/build.bak-*\n' > "$FIXTURE/.gitignore"
printf 'hot\n' > "$FIXTURE/public/hot"
git -C "$FIXTURE" add .gitignore app/a.php public/js.js
git -C "$FIXTURE" -c user.email=release@example.com -c user.name=release commit -m init >/dev/null
base="$(git -C "$FIXTURE" rev-parse HEAD)"

"$CLEAN" --repo "$FIXTURE" >/dev/null || fail "gitignored public/hot blocked a clean tree"

printf 'extra\n' > "$FIXTURE/app/extra.php"
if "$CLEAN" --repo "$FIXTURE" >/dev/null 2>&1; then
  fail "untracked file was allowed"
fi
rm -f "$FIXTURE/app/extra.php"

printf 'dirty\n' >> "$FIXTURE/app/a.php"
if "$CLEAN" --repo "$FIXTURE" >/dev/null 2>&1; then
  fail "dirty tracked file was allowed"
fi
mkdir -p "$FIXTURE/public/build.bak-pre-380"
printf 'old\n' > "$FIXTURE/public/build.bak-pre-380/app.js"
printf 'http://[::1]:5173\n' > "$FIXTURE/public/hot"

dest="$WORKDIR/context"
mkdir -p "$dest"
"$MATERIALIZE" --repo "$FIXTURE" --commit "$base" --dest "$dest" >/dev/null
[[ "$(cat "$dest/app/a.php")" == "tracked" ]] || fail "archive kept a dirty tracked file"
[[ ! -e "$dest/public/hot" ]] || fail "archive included public/hot"
[[ ! -e "$dest/app/extra.php" ]] || fail "archive included an untracked file"
[[ ! -e "$dest/public/build.bak-pre-380/app.js" ]] || fail "archive included a build backup"
[[ -f "$dest/public/js.js" ]] || fail "archive dropped a tracked file"

hot_repo="$WORKDIR/hot-repo"
git init -b main "$hot_repo" >/dev/null
mkdir -p "$hot_repo/public"
printf 'http://[::1]:5173\n' > "$hot_repo/public/hot"
git -C "$hot_repo" add -f public/hot
git -C "$hot_repo" -c user.email=release@example.com -c user.name=release commit -m hot >/dev/null
hot_dest="$WORKDIR/hot-context"
mkdir -p "$hot_dest"
if "$MATERIALIZE" --repo "$hot_repo" --commit HEAD --dest "$hot_dest" >/dev/null 2>&1; then
  fail "committed public/hot was accepted"
fi

bak_repo="$WORKDIR/bak-repo"
git init -b main "$bak_repo" >/dev/null
mkdir -p "$bak_repo/public/build.bak-pre-380"
printf 'old\n' > "$bak_repo/public/build.bak-pre-380/app.js"
git -C "$bak_repo" add -f public/build.bak-pre-380/app.js
git -C "$bak_repo" -c user.email=release@example.com -c user.name=release commit -m bak >/dev/null
bak_dest="$WORKDIR/bak-context"
mkdir -p "$bak_dest"
if "$MATERIALIZE" --repo "$bak_repo" --commit HEAD --dest "$bak_dest" >/dev/null 2>&1; then
  fail "committed build.bak was accepted"
fi

printf 'two\n' > "$FIXTURE/app/a.php"
git -C "$FIXTURE" add app/a.php
git -C "$FIXTURE" -c user.email=release@example.com -c user.name=release commit -m two >/dev/null
child="$(git -C "$FIXTURE" rev-parse HEAD)"

git -C "$FIXTURE" checkout -q -b side "$base"
printf 'side\n' > "$FIXTURE/app/a.php"
git -C "$FIXTURE" add app/a.php
git -C "$FIXTURE" -c user.email=release@example.com -c user.name=release commit -m side >/dev/null
side="$(git -C "$FIXTURE" rev-parse HEAD)"

bare="$WORKDIR/origin.git"
git init --bare -b main "$bare" >/dev/null
git -C "$FIXTURE" remote add origin "https://github.com/fixture/core.git"
git -C "$FIXTURE" config url."${bare}".insteadOf "https://github.com/fixture/core.git"
git -C "$FIXTURE" push -q origin "$base:refs/heads/main"
git -C "$FIXTURE" push -q origin "$side:refs/heads/side"

"$PUBLISHER" --repo "$FIXTURE" --publisher "$base" --source "$child" >/dev/null || fail "publisher on main refused a different source commit"
if "$PUBLISHER" --repo "$FIXTURE" --publisher "$side" --source "$child" >/dev/null 2>&1; then
  fail "publisher revision off main was allowed"
fi
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "source commit without a release record was allowed"
fi
if ARK_SKIP_RELEASE_CHECKS=1 "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "ARK_SKIP_RELEASE_CHECKS was allowed"
fi
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" --skip-checks >/dev/null 2>&1; then
  fail "skip-checks bypass was allowed"
fi

diff_id="$("$ROOT/scripts/core-release-diff-id.sh" --repo "$FIXTURE" --baseline "$base" --source "$child")"
git -C "$FIXTURE" checkout -q -B release-authorization "$base"
mkdir -p "$FIXTURE/releases"
write_release_record() {
  local deploy="$1"
  cat > "$FIXTURE/releases/${child}.json" <<EOF
{
  "schema": "ark-core-source-release/v1",
  "repository": "fixture/core",
  "source_sha": "${child}",
  "baseline_sha": "${base}",
  "diff_sha256": "${diff_id}",
  "approval": {"state": "source_approved", "pull_request": 7},
  "stages": {
    "source_approved": true,
    "tests_verified": true,
    "artifact_published": false,
    "saas_image_verified": false,
    "production_deployment_authorized": ${deploy},
    "deployment_completed": false
  }
}
EOF
  git -C "$FIXTURE" add "releases/${child}.json"
  git -C "$FIXTURE" -c user.email=release@example.com -c user.name=release commit -q -m "record ${deploy}"
  git -C "$FIXTURE" push -q --force origin "HEAD:refs/heads/release-authorization"
}
write_release_record false
record_commit="$(git -C "$FIXTURE" rev-parse HEAD)"
git -C "$FIXTURE" push -q --force origin "${child}:refs/tags/core-release/${child}"

mkdir -p "$WORKDIR/bin"
cat > "$WORKDIR/bin/gh" <<'EOF'
#!/usr/bin/env bash
url=""
for arg in "$@"; do
  case "$arg" in
    repos/*) url="$arg" ;;
  esac
done
mode="${ARK_FIXTURE_MODE:-success}"
sha="${ARK_FIXTURE_SHA}"
review="${ARK_FIXTURE_REVIEW}"
if [[ "$url" == *"/check-runs"* ]]; then
  conclusion="success"
  head="$sha"
  count=2
  case "$mode" in
    failed) conclusion="failure" ;;
    skipped) conclusion="skipped" ;;
    stale) head="${ARK_FIXTURE_STALE}" ;;
    missing) count=0 ;;
  esac
  if [[ "$count" -eq 0 ]]; then
    printf '%s\n' '{"total_count":0,"check_runs":[]}'
    exit 0
  fi
  printf '{"total_count":2,"check_runs":[{"name":"gitleaks","head_sha":"%s","status":"completed","conclusion":"%s","completed_at":"2026-10-20T00:00:00Z","app":{"slug":"github-actions"}},{"name":"pest","head_sha":"%s","status":"completed","conclusion":"%s","completed_at":"2026-10-20T00:00:01Z","app":{"slug":"github-actions"}}]}\n' "$head" "$conclusion" "$head" "$conclusion"
  exit 0
fi
if [[ "$url" == *"/reviews" ]]; then
  if [[ "$mode" == "no-approval" ]]; then
    printf '%s\n' '[]'
    exit 0
  fi
  reviewer="reviewer"
  if [[ "$mode" == "self-approval" ]]; then
    reviewer="author"
  fi
  printf '[{"state":"APPROVED","commit_id":"%s","user":{"login":"%s"}}]\n' "$review" "$reviewer"
  exit 0
fi
if [[ "$url" == *"/pulls/"* ]]; then
  printf '{"merged":true,"base":{"ref":"release-authorization"},"user":{"login":"author"}}\n'
  exit 0
fi
echo "unexpected gh api: $url" >&2
exit 1
EOF
chmod +x "$WORKDIR/bin/gh"
export PATH="$WORKDIR/bin:$PATH"
export ARK_FIXTURE_SHA="$child"
export ARK_FIXTURE_STALE="$base"
export ARK_FIXTURE_REVIEW="$record_commit"

export ARK_FIXTURE_MODE=success
"$PUBLISHED" --repo "$FIXTURE" --commit "$child" | grep -q 'production deployment: not authorized' || fail "approved source was refused or implied deployment"

export ARK_FIXTURE_MODE=failed
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "failed checks were allowed"
fi
export ARK_FIXTURE_MODE=skipped
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "skipped checks were allowed"
fi
export ARK_FIXTURE_MODE=stale
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "stale checks were allowed"
fi
export ARK_FIXTURE_MODE=missing
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "missing checks were allowed"
fi
export ARK_FIXTURE_MODE=no-approval
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "missing approval was allowed"
fi
export ARK_FIXTURE_MODE=self-approval
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "self-approval was allowed"
fi

export ARK_FIXTURE_MODE=success
git -C "$FIXTURE" push -q --force origin "${base}:refs/tags/core-release/${child}"
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "tag pointing at another commit was allowed"
fi
git -C "$FIXTURE" push -q origin ":refs/tags/core-release/${child}"
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "missing tag was allowed"
fi
git -C "$FIXTURE" push -q origin "${child}:refs/tags/core-release/${child}"

php -r '$path=$argv[1]; $text=file_get_contents($path); file_put_contents($path, str_replace($argv[2], $argv[3], $text));' \
  "$FIXTURE/releases/${child}.json" "$diff_id" "$(printf '0%.0s' {1..64})"
git -C "$FIXTURE" add "releases/${child}.json"
git -C "$FIXTURE" -c user.email=release@example.com -c user.name=release commit -q -m "wrong diff"
git -C "$FIXTURE" push -q --force origin "HEAD:refs/heads/release-authorization"
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "mismatched diff was allowed"
fi

write_release_record true
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "deployment authorization was treated as source publication"
fi

good_image="ghcr.io/edwardsoaresjr/ark-core@sha256:$(printf 'a%.0s' {1..64})"
"$IMAGE_REF" "$good_image" >/dev/null || fail "digest pin was refused"
if "$IMAGE_REF" "ghcr.io/edwardsoaresjr/ark-core:latest" >/dev/null 2>&1; then
  fail ":latest was allowed"
fi
if "$IMAGE_REF" "ghcr.io/edwardsoaresjr/ark-core:production" >/dev/null 2>&1; then
  fail ":production was allowed"
fi
if "$IMAGE_REF" "ghcr.io/edwardsoaresjr/ark-core:${child}" >/dev/null 2>&1; then
  fail "commit tag without a digest was allowed"
fi
if "$IMAGE_REF" "ghcr.io/edwardsoaresjr/ark@sha256:$(printf 'b%.0s' {1..64})" >/dev/null 2>&1; then
  fail "wrong repository was allowed"
fi

echo "Core release integrity ok"
