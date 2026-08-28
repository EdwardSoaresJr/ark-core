#!/usr/bin/env python3
"""Quick smoke check for ARK Engineering v0.1 (no pytest required)."""
from __future__ import annotations

import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))


def main() -> int:
    from ark_engineering.config import EngineeringConfig
    from ark_engineering.observer import capture_evidence, local_diff_for_repo, local_git_status_for_repo
    from ark_engineering.reviewer import record_review
    from ark_engineering.seed import seed_state
    from ark_engineering.store import EventStore
    from ark_engineering.worker import declare_done, start_work

    with tempfile.TemporaryDirectory() as tmp:
        state_dir = Path(tmp) / "state"
        repo = Path(tmp) / "repo"
        repo.mkdir()
        for cmd in (
            ["git", "init"],
            ["git", "config", "user.email", "smoke@test.local"],
            ["git", "config", "user.name", "Smoke"],
            ["git", "add", "-A"],
        ):
            pass
        subprocess.run(["git", "init"], cwd=repo, check=True, capture_output=True)
        subprocess.run(
            ["git", "config", "user.email", "smoke@test.local"],
            cwd=repo,
            check=True,
            capture_output=True,
        )
        subprocess.run(
            ["git", "config", "user.name", "Smoke"],
            cwd=repo,
            check=True,
            capture_output=True,
        )
        (repo / "README.md").write_text("hello\n", encoding="utf-8")
        subprocess.run(["git", "add", "README.md"], cwd=repo, check=True, capture_output=True)
        subprocess.run(["git", "commit", "-m", "init"], cwd=repo, check=True, capture_output=True)
        (repo / "README.md").write_text("hello world\n", encoding="utf-8")

        config = EngineeringConfig(
            state_dir=state_dir,
            bridge_url="http://127.0.0.1:9471",
            bridge_token="",
            openai_api_key=None,
            openai_model="gpt-5.5",
            default_repo=str(repo),
            port=19472,
        )
        store = EventStore(state_dir)
        seed_state(store, config, force=True)
        task = store.load_task()
        task["repo"] = str(repo)
        store.save_task(task)

        start_work(store)
        declare_done(store, summary="smoke", files_touched="README.md")
        assert any(e["type"] == "worker.declared_done" for e in store.read_events())

        status = local_git_status_for_repo(repo)
        diff = local_diff_for_repo(repo)
        assert status["dirty"]
        assert "README.md" in diff["summary"]

        capture_evidence(store, config)
        assert store.latest_event("observer.git_status.captured")
        assert store.latest_event("observer.diff.captured")

        record_review(
            store,
            config,
            decision="approved",
            reason="Smoke test evidence OK.",
        )
        assert store.latest_event("reviewer.approved")

    print("smoke: OK — event append, observer git/diff, reviewer event")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
