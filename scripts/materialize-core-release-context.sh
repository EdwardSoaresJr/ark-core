#!/usr/bin/env bash
# Extract one commit into an empty directory for a Core image build.
# The directory is git archive output. It is not the worktree.
set -euo pipefail

repo=""
commit=""
dest=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) repo="$2"; shift 2 ;;
    --commit) commit="$2"; shift 2 ;;
    --dest) dest="$2"; shift 2 ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

if [[ -z "$repo" || -z "$commit" || -z "$dest" ]]; then
  echo "Usage: materialize-core-release-context.sh --repo ROOT --commit SHA --dest DIR" >&2
  exit 2
fi

if [[ ! -d "$dest" ]]; then
  echo "REFUSING: destination does not exist: $dest" >&2
  exit 1
fi

if [[ -n "$(find "$dest" -mindepth 1 -print -quit)" ]]; then
  echo "REFUSING: destination is not empty: $dest" >&2
  exit 1
fi

sha="$(git -C "$repo" rev-parse --verify "${commit}^{commit}")"

git -C "$repo" archive --format=tar "$sha" | tar -x -C "$dest"

python3 - "$repo" "$sha" "$dest" << 'PY'
import os
import subprocess
import sys

repo, sha, dest = sys.argv[1:4]

def git(*args, input_bytes=None):
    return subprocess.run(
        ["git", "-C", repo, *args],
        input=input_bytes,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=True,
    ).stdout

raw = git("ls-tree", "-r", "-z", sha)
records = [part for part in raw.split(b"\0") if part]
blobs = {}
for record in records:
    meta, path = record.split(b"\t", 1)
    digest = meta.split(b" ")[2].decode()
    blobs[path.decode()] = digest

attr_in = b"\0".join(path.encode() for path in blobs)
if blobs:
    attr_in += b"\0"
listed = git(
    "check-attr", "--stdin", "-z", "--source", sha, "export-ignore",
    input_bytes=attr_in,
).split(b"\0")

skipped = set()
index = 0
while index + 2 < len(listed):
    path, _attr, value = listed[index], listed[index + 1], listed[index + 2]
    if value == b"set":
        skipped.add(path.decode())
    index += 3

expected = [path for path in blobs if path not in skipped]
found = []
for dirpath, _dirnames, filenames in os.walk(dest):
    for name in filenames:
        full = os.path.join(dirpath, name)
        found.append(os.path.relpath(full, dest))

missing = sorted(set(expected) - set(found))
extra = sorted(set(found) - set(expected))
if missing or extra:
    print("REFUSING: release context is not the commit tree.", file=sys.stderr)
    for path in extra[:20]:
        print(f"  extra {path}", file=sys.stderr)
    for path in missing[:20]:
        print(f"  missing {path}", file=sys.stderr)
    sys.exit(1)

forbidden = [
    path for path in found
    if path == "public/hot" or path.startswith("public/build.bak-")
]
if forbidden:
    print("REFUSING: release context contains a local build artifact.", file=sys.stderr)
    for path in forbidden:
        print(f"  {path}", file=sys.stderr)
    sys.exit(1)

if any("\n" in path for path in expected):
    print("REFUSING: a path in the commit contains a newline.", file=sys.stderr)
    sys.exit(1)
path_bytes = "".join(os.path.join(dest, path) + "\n" for path in expected).encode()
hashed = git("hash-object", "--stdin-paths", "--no-filters", input_bytes=path_bytes).decode().splitlines()
if len(hashed) != len(expected):
    print("REFUSING: could not hash the release context.", file=sys.stderr)
    sys.exit(1)
for path, digest in zip(expected, hashed):
    if blobs[path] != digest:
        print(f"REFUSING: {path} does not match {sha}", file=sys.stderr)
        sys.exit(1)

print(f"release context ok")
print(f"  commit: {sha}")
print(f"  files: {len(expected)}")
PY
