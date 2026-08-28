from __future__ import annotations

from pathlib import Path
from typing import Any

from .config import EngineeringConfig
from .store import EventStore, utc_now

DEFAULT_PLAN = """## Falsifiable plan — Certify G4 staged provisioning gates

**Hypothesis:** Known MAC provision URL returns staged gate responses (404 → 403 → 200) before any phone bench work.

**Steps (stop at first unexpected gate):**
1. Verify G2/G3 first — unknown MAC → 404; invalid format handled.
2. Run staged device sequence: 404 (not found) → 403 (gate) → 200 (Poly body) for known MAC.
3. Do **not** plug in the phone until G4 HTTP path passes on production/staging.
4. Record observer evidence (HTTP status + body snippet) — not worker claims.

**Falsified if:** Any gate returns unexpected status (e.g. 500 HTML) or body does not match gate expectation.
"""


def seed_state(store: EventStore, config: EngineeringConfig, *, force: bool = False) -> None:
    if store.goal_path.exists() and store.task_path.exists() and not force:
        return

    goal = {
        "id": "goal-first-contact",
        "title": "Complete Voice First Contact",
        "desired_outcome": (
            "Certify the VVX350 path from provisioning through Connected in ARK"
        ),
        "status": "active",
    }
    task = {
        "id": "task-g4-certification",
        "goal_id": goal["id"],
        "title": "Certify G4 staged provisioning gates",
        "repo": config.default_repo,
        "worker": "human-cursor",
        "status": "planned",
        "test_command": "",
    }

    store.save_goal(goal)
    store.save_task(task)

    if not store.events_path.exists() or force:
        if store.events_path.exists():
            store.events_path.unlink()
        store.append_event("goal.created", {"goal_id": goal["id"], "title": goal["title"]})
        store.append_event(
            "task.created",
            {"task_id": task["id"], "goal_id": goal["id"], "title": task["title"]},
        )
        store.append_event(
            "planner.plan.ready",
            {
                "task_id": task["id"],
                "plan": DEFAULT_PLAN.strip(),
                "source": "seed",
            },
        )


def project_state(store: EventStore) -> dict[str, Any]:
    goal = store.load_goal()
    task = store.load_task()
    events = store.read_events()

    plan_event = store.latest_event("planner.plan.ready")
    worker_started = store.latest_event("worker.started")
    worker_done = store.latest_event("worker.declared_done")

    observer_git = store.latest_event("observer.git_status.captured")
    observer_diff = store.latest_event("observer.diff.captured")
    observer_tests = store.latest_event("observer.tests.captured")

    review_approved = store.latest_event("reviewer.approved")
    review_changes = store.latest_event("reviewer.requested_changes")
    review_event = review_approved or review_changes

    return {
        "goal": goal,
        "task": task,
        "plan": plan_event,
        "worker": {
            "started": worker_started,
            "declared_done": worker_done,
        },
        "observer": {
            "git_status": observer_git,
            "diff": observer_diff,
            "tests": observer_tests,
        },
        "reviewer": review_event,
        "events": events,
        "generated_at": utc_now(),
    }
