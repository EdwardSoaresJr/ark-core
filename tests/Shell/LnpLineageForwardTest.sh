#!/usr/bin/env bash
# The forward gate stays strict. One pinned pair may cross the sanitation break.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
FORWARD="$ROOT/scripts/assert-lnp-core-forward.sh"
LINEAGE_FROM=8b36cf9c107d36b8b87e0f3ed639794a338970e4
LINEAGE_ROOT=25d9214435f1d4d680d956b5507537abca91b876
WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/ark-lineage-forward-XXXXXX")"
trap 'rm -rf "$WORKDIR"' EXIT

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

git_commit() {
  git -C "$1" -c user.email=release@example.com -c user.name=release commit -q -m "$2"
}

grep -q "$LINEAGE_FROM" "$FORWARD" || fail "production pin missing"
grep -q "$LINEAGE_ROOT" "$FORWARD" || fail "public root pin missing"
if grep -q '149.28.249.13' "$FORWARD"; then
  fail "forward script names a production host"
fi

FIXTURE="$WORKDIR/fixture"
git init -q -b main "$FIXTURE"
printf 'root\n' > "$FIXTURE/a.php"
git -C "$FIXTURE" add a.php
git_commit "$FIXTURE" root
root="$(git -C "$FIXTURE" rev-parse HEAD)"

printf 'child\n' > "$FIXTURE/a.php"
git -C "$FIXTURE" add a.php
git_commit "$FIXTURE" child
child="$(git -C "$FIXTURE" rev-parse HEAD)"

git -C "$FIXTURE" checkout -q --orphan other
printf 'other\n' > "$FIXTURE/b.php"
git -C "$FIXTURE" add b.php
git_commit "$FIXTURE" other
other="$(git -C "$FIXTURE" rev-parse HEAD)"

absent=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
sideways=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb

copy="$WORKDIR/forward.sh"
sed \
  -e "s/${LINEAGE_FROM}/${absent}/" \
  -e "s/${LINEAGE_ROOT}/${root}/" \
  "$FORWARD" > "$copy"
chmod +x "$copy"

LNP_RUNNING_COMMIT="$absent" "$copy" --repo "$FIXTURE" "$root" >/dev/null || fail "transition to the pinned root was refused"
if LNP_RUNNING_COMMIT="$absent" "$copy" --repo "$FIXTURE" "$child" >/dev/null 2>&1; then
  fail "transition to a descendant of the pinned root was allowed"
fi
if LNP_RUNNING_COMMIT="$absent" "$copy" --repo "$FIXTURE" "$other" >/dev/null 2>&1; then
  fail "transition onto an unrelated commit was allowed"
fi
if LNP_RUNNING_COMMIT="$sideways" "$copy" --repo "$FIXTURE" "$root" >/dev/null 2>&1; then
  fail "an unknown running commit was allowed onto the new root"
fi

LNP_RUNNING_COMMIT="$root" "$FORWARD" --repo "$FIXTURE" "$child" >/dev/null || fail "ordinary descendant was refused"
if LNP_RUNNING_COMMIT="$child" "$FORWARD" --repo "$FIXTURE" "$root" >/dev/null 2>&1; then
  fail "ordinary ancestor deploy was allowed"
fi
if LNP_RUNNING_COMMIT="$root" "$FORWARD" --repo "$FIXTURE" "$other" >/dev/null 2>&1; then
  fail "ordinary sideways deploy was allowed"
fi
if LNP_RUNNING_COMMIT="$child" "$FORWARD" --repo "$FIXTURE" "$child" >/dev/null 2>&1; then
  fail "same commit was treated as forward"
fi

LNP_RUNNING_COMMIT="$LINEAGE_FROM" "$FORWARD" --repo "$ROOT" "$LINEAGE_ROOT" >/dev/null || fail "real production pin onto the public root was refused"
if LNP_RUNNING_COMMIT="$sideways" "$FORWARD" --repo "$ROOT" "$LINEAGE_ROOT" >/dev/null 2>&1; then
  fail "real script allowed an unknown running commit onto the public root"
fi

clone="$WORKDIR/public-child"
git clone -q "$ROOT" "$clone"
git -C "$clone" checkout -q main
printf 'later\n' >> "$clone/README.md"
git -C "$clone" add README.md
git_commit "$clone" later
later="$(git -C "$clone" rev-parse HEAD)"

if LNP_RUNNING_COMMIT="$LINEAGE_FROM" "$FORWARD" --repo "$clone" "$later" >/dev/null 2>&1; then
  fail "real production pin onto a later public commit was allowed"
fi
LNP_RUNNING_COMMIT="$LINEAGE_ROOT" "$FORWARD" --repo "$clone" "$later" >/dev/null || fail "forward move after the transition was refused"

printf 'grandchild\n' >> "$clone/README.md"
git -C "$clone" add README.md
git_commit "$clone" grandchild
grandchild="$(git -C "$clone" rev-parse HEAD)"
LNP_RUNNING_COMMIT="$LINEAGE_ROOT" "$FORWARD" --repo "$clone" "$grandchild" >/dev/null || fail "later descendant after the transition was refused"
if LNP_RUNNING_COMMIT="$later" "$FORWARD" --repo "$clone" "$LINEAGE_ROOT" >/dev/null 2>&1; then
  fail "rollback to the public root was allowed after a later commit"
fi

git -C "$clone" checkout -q --orphan stray
printf 'stray\n' > "$clone/stray.txt"
git -C "$clone" add stray.txt
git_commit "$clone" stray
stray="$(git -C "$clone" rev-parse HEAD)"
if LNP_RUNNING_COMMIT="$LINEAGE_FROM" "$FORWARD" --repo "$clone" "$stray" >/dev/null 2>&1; then
  fail "real production pin onto an unrelated commit was allowed"
fi
if LNP_RUNNING_COMMIT="$later" "$FORWARD" --repo "$clone" "$stray" >/dev/null 2>&1; then
  fail "a child was allowed to move sideways"
fi
if LNP_RUNNING_COMMIT="$LINEAGE_ROOT" "$FORWARD" --repo "$clone" "$stray" >/dev/null 2>&1; then
  fail "strict ancestry allowed an unrelated commit after the transition"
fi

echo "LugsNPlugs lineage forward ok"
