from __future__ import annotations

from typing import Any

from .store import EventStore


def start_work(store: EventStore) -> dict[str, Any]:
    task = store.load_task()
    event = store.append_event(
        "worker.started",
        {
            "task_id": task["id"],
            "worker": task.get("worker", "human-cursor"),
        },
    )
    task["status"] = "in_progress"
    store.save_task(task)
    return event


def declare_done(
    store: EventStore,
    *,
    summary: str = "",
    files_touched: str = "",
) -> dict[str, Any]:
    task = store.load_task()
    event = store.append_event(
        "worker.declared_done",
        {
            "task_id": task["id"],
            "worker": task.get("worker", "human-cursor"),
            "summary": summary.strip(),
            "files_touched": files_touched.strip(),
            "note": "Proposed artifacts — not verified until observer captures evidence.",
        },
    )
    task["status"] = "awaiting_evidence"
    store.save_task(task)
    return event
