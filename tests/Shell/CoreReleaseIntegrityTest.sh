#!/usr/bin/env bash
# Release context is the commit.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MATERIALIZE="$ROOT/scripts/materialize-core-release-context.sh"
PUBLISH="$ROOT/infra/build-runner/mac/publish-ghcr-ark.sh"
CLEAN="$ROOT/scripts/assert-core-worktree-clean.sh"
PUBLISHED="$ROOT/scripts/assert-core-source-published.sh"
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
grep -q 'assert-core-source-published.sh' "$PUBLISH" || fail "publish script does not require origin/main"
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
git -C "$FIXTURE" remote add origin "$bare"
git -C "$FIXTURE" push -q origin "$base:refs/heads/main"
"$PUBLISHED" --repo "$FIXTURE" --commit "$base" >/dev/null || fail "origin/main commit was refused"
if "$PUBLISHED" --repo "$FIXTURE" --commit "$child" >/dev/null 2>&1; then
  fail "unpublished commit was allowed"
fi
git -C "$FIXTURE" push -q origin "$side:refs/heads/side"
if "$PUBLISHED" --repo "$FIXTURE" --commit "$side" >/dev/null 2>&1; then
  fail "non-main branch was allowed"
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
