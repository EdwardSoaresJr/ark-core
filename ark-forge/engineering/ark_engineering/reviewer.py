from __future__ import annotations

import json
from typing import Any, Literal

from .config import EngineeringConfig
from .seed import project_state
from .store import EventStore

ReviewerDecision = Literal["approved", "changes_requested"]

REVIEWER_SYSTEM = """You are the Reviewer role in ARK Engineering.

You decide using evidence from the Observer — never trust Worker claims alone.
Output JSON only:
{"decision": "approved" | "changes_requested", "reason": "..."}

Approve only when observer evidence supports the plan's falsifiable criteria.
Request changes when evidence is missing, contradictory, or incomplete.
Do not claim to re-run tests yourself — cite observer evidence only."""


def record_review(
    store: EventStore,
    config: EngineeringConfig,
    *,
    decision: ReviewerDecision | None = None,
    reason: str | None = None,
    use_openai: bool = False,
) -> dict[str, Any]:
    task = store.load_task()
    projection = project_state(store)

    if use_openai and config.openai_api_key and decision is None:
        decision, reason = _review_with_openai(config, projection)
    elif decision is None or not (reason or "").strip():
        raise ValueError("Provide decision and reason, or enable OpenAI review.")

    event_type = (
        "reviewer.approved" if decision == "approved" else "reviewer.requested_changes"
    )
    event = store.append_event(
        event_type,
        {
            "task_id": task["id"],
            "decision": decision,
            "reason": reason.strip(),
            "source": "openai" if use_openai and config.openai_api_key else "manual",
        },
    )
    task["status"] = "complete" if decision == "approved" else "changes_requested"
    store.save_task(task)
    return event


def _review_with_openai(
    config: EngineeringConfig,
    projection: dict[str, Any],
) -> tuple[ReviewerDecision, str]:
    from openai import OpenAI

    client = OpenAI(api_key=config.openai_api_key)
    context = {
        "task": projection["task"],
        "plan": projection.get("plan"),
        "worker": projection.get("worker"),
        "observer": projection.get("observer"),
    }
    response = client.responses.create(
        model=config.openai_model,
        instructions=REVIEWER_SYSTEM,
        input=json.dumps(context, indent=2),
    )
    text = (response.output_text or "").strip()
    data = json.loads(text)
    decision = data.get("decision")
    reason = str(data.get("reason", "")).strip()
    if decision not in ("approved", "changes_requested"):
        raise RuntimeError(f"Invalid reviewer decision from model: {decision!r}")
    if not reason:
        raise RuntimeError("Reviewer reason missing from model output.")
    return decision, reason
