#!/usr/bin/env bash
# Refuse a LugsNPlugs Core deploy unless the candidate commit is a strict
# descendant of the commit running in production.
#
# One pinned exception: production 8b36cf9c may move to 25d92144 and no
# other commit. That is the sanitation cutover. Once production is on that
# root, only ordinary strict ancestry applies.
#
# Tests set LNP_RUNNING_COMMIT. A live check sets LNP_CORE_HOST and
# LNP_CORE_CONTAINER. This script does not name a production host.
#
#   scripts/assert-lnp-core-forward.sh CANDIDATE
set -euo pipefail

lineage_from=8b36cf9c107d36b8b87e0f3ed639794a338970e4
lineage_root=25d9214435f1d4d680d956b5507537abca91b876

repo=""
candidate=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) repo="$2"; shift 2 ;;
    *)
      if [[ -n "$candidate" ]]; then
        echo "Unexpected argument: $1" >&2
        exit 2
      fi
      candidate="$1"
      shift
      ;;
  esac
done

if [[ -z "$repo" ]]; then
  repo="$(cd "$(dirname "$0")/.." && pwd)"
fi

if [[ -z "$candidate" ]]; then
  echo "Usage: scripts/assert-lnp-core-forward.sh [--repo ROOT] CANDIDATE" >&2
  exit 2
fi

if ! wanted="$(git -C "$repo" rev-parse --verify "${candidate}^{commit}" 2>/dev/null)"; then
  echo "REFUSING: candidate is not a commit in this repository: $candidate" >&2
  exit 1
fi

if [[ -n "${LNP_RUNNING_COMMIT:-}" ]]; then
  running_raw="$LNP_RUNNING_COMMIT"
else
  host="${LNP_CORE_HOST:-}"
  container="${LNP_CORE_CONTAINER:-}"
  if [[ -z "$host" || -z "$container" ]]; then
    echo "REFUSING: set LNP_RUNNING_COMMIT, or both LNP_CORE_HOST and LNP_CORE_CONTAINER." >&2
    exit 1
  fi
  if [[ ! "$host" =~ ^[A-Za-z0-9.-]+$ || ! "$container" =~ ^[A-Za-z0-9_.-]+$ ]]; then
    echo "REFUSING: LNP host or container name is not usable." >&2
    exit 1
  fi
  if ! running_raw="$(ssh -o BatchMode=yes -o ConnectTimeout=10 "root@${host}" \
    "docker exec ${container} cat /app/.ark-source-commit")"; then
    echo "REFUSING: could not read the running Core commit." >&2
    exit 1
  fi
fi

running_raw="$(printf '%s' "$running_raw" | tr -d '[:space:]')"

if [[ "$running_raw" == "$wanted" ]]; then
  echo "REFUSING: candidate ${wanted} is already the running Core commit." >&2
  exit 1
fi

running=""
if running="$(git -C "$repo" rev-parse --verify "${running_raw}^{commit}" 2>/dev/null)"; then
  if git -C "$repo" merge-base --is-ancestor "$running" "$wanted"; then
    echo "LugsNPlugs forward check ok"
    echo "  running:   ${running}"
    echo "  candidate: ${wanted}"
    exit 0
  fi
fi

if [[ "$running_raw" == "$lineage_from" ]]; then
  if ! root="$(git -C "$repo" rev-parse --verify "${lineage_root}^{commit}" 2>/dev/null)"; then
    echo "REFUSING: lineage root ${lineage_root} is not in this repository." >&2
    exit 1
  fi
  if [[ "$wanted" == "$root" ]]; then
    echo "LugsNPlugs lineage transition ok"
    echo "  running:   ${lineage_from}"
    echo "  candidate: ${wanted}"
    exit 0
  fi
  echo "REFUSING: running Core ${lineage_from} can move only onto ${lineage_root}." >&2
  exit 1
fi

if [[ -z "$running" ]]; then
  echo "REFUSING: running Core commit is not in this repository: ${running_raw}" >&2
  exit 1
fi

echo "REFUSING: running Core ${running} is not an ancestor of candidate ${wanted}." >&2
echo "LugsNPlugs only moves forward from the commit that is running." >&2
exit 1
