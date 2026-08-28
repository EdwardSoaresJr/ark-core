from __future__ import annotations

import subprocess
from pathlib import Path
from typing import Any

from .bridge_client import BridgeClient, BridgeError
from .config import EngineeringConfig, load_bridge_config
from .store import EventStore


def capture_evidence(
    store: EventStore,
    config: EngineeringConfig,
    *,
    test_command: str | None = None,
) -> dict[str, Any]:
    task = store.load_task()
    repo = Path(task["repo"])

    git_status = _capture_git_status(config, repo)
    store.append_event("observer.git_status.captured", git_status)

    diff_stat = _capture_diff_stat(repo)
    store.append_event("observer.diff.captured", diff_stat)

    tests_payload: dict[str, Any] | None = None
    command = (test_command or task.get("test_command") or "").strip()
    if command:
        tests_payload = _capture_tests(repo, command)
        store.append_event("observer.tests.captured", tests_payload)

    return {
        "git_status": git_status,
        "diff": diff_stat,
        "tests": tests_payload,
    }


def _capture_git_status(config: EngineeringConfig, repo: Path) -> dict[str, Any]:
    source = "local_git"
    snapshot: dict[str, Any]

    if config.bridge_token:
        try:
            bridge_url, bridge_token = load_bridge_config(config)
            bridge = BridgeClient(bridge_url, bridge_token)
            response = bridge.observe("forge.git.status.read", {"path": str(repo)})
            if response.get("ok") and isinstance(response.get("result"), dict):
                source = "forge.git.status.read"
                snapshot = dict(response["result"])
                snapshot["source"] = source
                return snapshot
        except BridgeError:
            pass

    snapshot = _local_git_status(repo)
    snapshot["source"] = source
    return snapshot


def _local_git_status(repo: Path) -> dict[str, Any]:
    branch = _run_git(repo, "rev-parse", "--abbrev-ref", "HEAD").strip() or "unknown"
    status_lines = _run_git(repo, "status", "--short").splitlines()
    modified_files = [line.strip() for line in status_lines if line.strip()]
    return {
        "branch": branch,
        "dirty": len(modified_files) > 0,
        "modified_count": len(modified_files),
        "modified_files": modified_files,
    }


def _capture_diff_stat(repo: Path) -> dict[str, Any]:
    stat = _run_git(repo, "diff", "--stat")
    numstat = _run_git(repo, "diff", "--numstat")
    return {
        "summary": stat.strip(),
        "numstat": numstat.strip(),
        "source": "git diff --stat",
    }


def _capture_tests(repo: Path, command: str) -> dict[str, Any]:
    completed = subprocess.run(
        command,
        cwd=repo,
        shell=True,
        capture_output=True,
        text=True,
        timeout=600,
        check=False,
    )
    output = (completed.stdout or "") + (completed.stderr or "")
    if len(output) > 12000:
        output = output[:12000] + "\n… (truncated)"
    return {
        "command": command,
        "exit_code": completed.returncode,
        "output": output,
        "source": "shell",
    }


def _run_git(repo: Path, *args: str) -> str:
    completed = subprocess.run(
        ["git", *args],
        cwd=repo,
        capture_output=True,
        text=True,
        check=False,
    )
    if completed.returncode != 0:
        raise RuntimeError(
            f"git {' '.join(args)} failed: {(completed.stderr or completed.stdout).strip()}"
        )
    return completed.stdout or ""


def local_git_status_for_repo(repo: Path) -> dict[str, Any]:
    """Test helper — local git only."""
    snapshot = _local_git_status(repo)
    snapshot["source"] = "local_git"
    return snapshot


def local_diff_for_repo(repo: Path) -> dict[str, Any]:
    return _capture_diff_stat(repo)
