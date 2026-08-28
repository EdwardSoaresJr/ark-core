from __future__ import annotations

import json
import subprocess
import tempfile
from pathlib import Path

import pytest

from ark_engineering.config import EngineeringConfig
from ark_engineering.observer import capture_evidence, local_diff_for_repo, local_git_status_for_repo
from ark_engineering.reviewer import record_review
from ark_engineering.seed import seed_state
from ark_engineering.store import EventStore
from ark_engineering.worker import declare_done, start_work


@pytest.fixture()
def temp_git_repo(tmp_path: Path) -> Path:
    repo = tmp_path / "repo"
    repo.mkdir()
    subprocess.run(["git", "init"], cwd=repo, check=True, capture_output=True)
    subprocess.run(
        ["git", "config", "user.email", "test@example.com"],
        cwd=repo,
        check=True,
        capture_output=True,
    )
    subprocess.run(
        ["git", "config", "user.name", "Test"],
        cwd=repo,
        check=True,
        capture_output=True,
    )
    (repo / "README.md").write_text("hello\n", encoding="utf-8")
    subprocess.run(["git", "add", "README.md"], cwd=repo, check=True, capture_output=True)
    subprocess.run(["git", "commit", "-m", "init"], cwd=repo, check=True, capture_output=True)
    (repo / "README.md").write_text("hello world\n", encoding="utf-8")
    return repo


@pytest.fixture()
def engineering_env(tmp_path: Path, temp_git_repo: Path) -> tuple[EventStore, EngineeringConfig]:
    state_dir = tmp_path / "state"
    config = EngineeringConfig(
        state_dir=state_dir,
        bridge_url="http://127.0.0.1:9471",
        bridge_token="",
        openai_api_key=None,
        openai_model="gpt-5.5",
        default_repo=str(temp_git_repo),
        port=9472,
    )
    store = EventStore(state_dir)
    seed_state(store, config, force=True)
    task = store.load_task()
    task["repo"] = str(temp_git_repo)
    store.save_task(task)
    return store, config


def test_event_append(engineering_env: tuple[EventStore, EngineeringConfig]) -> None:
    store, _ = engineering_env
    start_work(store)
    declare_done(store, summary="Touched README", files_touched="README.md")
    events = store.read_events()
    types = [e["type"] for e in events]
    assert "worker.started" in types
    assert "worker.declared_done" in types
    assert len(events) == len({json.dumps(e) for e in events})


def test_observer_captures_git_and_diff(
    engineering_env: tuple[EventStore, EngineeringConfig],
    temp_git_repo: Path,
) -> None:
    store, config = engineering_env
    status = local_git_status_for_repo(temp_git_repo)
    assert status["dirty"] is True
    diff = local_diff_for_repo(temp_git_repo)
    assert "README.md" in diff["summary"]

    result = capture_evidence(store, config)
    assert result["git_status"]["source"] in ("local_git", "forge.git.status.read")
    assert store.latest_event("observer.git_status.captured") is not None
    assert store.latest_event("observer.diff.captured") is not None


def test_reviewer_event_written(engineering_env: tuple[EventStore, EngineeringConfig]) -> None:
    store, config = engineering_env
    capture_evidence(store, config)
    event = record_review(
        store,
        config,
        decision="approved",
        reason="Observer shows expected diff on README; scope matches plan.",
    )
    assert event["type"] == "reviewer.approved"
    assert store.load_task()["status"] == "complete"
