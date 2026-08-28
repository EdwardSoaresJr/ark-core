from __future__ import annotations

from pathlib import Path
from typing import Any

from .config import DOCS_ROOT, EngineeringConfig
from .store import EventStore

PLANNER_SYSTEM = """You are the Planner role in ARK Engineering.

Produce a falsifiable implementation plan — hypotheses and steps that can be proven wrong by observation.
Do NOT claim tests pass, implementation is correct, or work is complete.
Do NOT approve work.
Keep the plan short and operational (markdown bullets)."""


def save_plan(
    store: EventStore,
    config: EngineeringConfig,
    *,
    manual_text: str | None = None,
    use_openai: bool = False,
) -> dict[str, Any]:
    task = store.load_task()

    if manual_text and manual_text.strip():
        plan = manual_text.strip()
        source = "manual"
    elif use_openai and config.openai_api_key:
        plan = _plan_with_openai(config, task)
        source = "openai"
    else:
        raise ValueError("Provide plan text or configure OPENAI_API_KEY for generate.")

    event = store.append_event(
        "planner.plan.ready",
        {
            "task_id": task["id"],
            "plan": plan,
            "source": source,
        },
    )
    task["status"] = "planned"
    store.save_task(task)
    return event


def _plan_with_openai(config: EngineeringConfig, task: dict[str, Any]) -> str:
    from openai import OpenAI

    doctrine = _read_doctrine_excerpt()
    client = OpenAI(api_key=config.openai_api_key)
    prompt = (
        f"Goal context: Voice First Contact certification.\n"
        f"Task: {task['title']}\n"
        f"Repo: {task['repo']}\n\n"
        f"Engineering doctrine excerpt:\n{doctrine}\n\n"
        "Write a falsifiable plan. Remind: verify G2/G3 first; staged 404→403→200; "
        "stop at first unexpected gate; no phone until G4 passes."
    )
    response = client.responses.create(
        model=config.openai_model,
        instructions=PLANNER_SYSTEM,
        input=prompt,
    )
    text = (response.output_text or "").strip()
    if not text:
        raise RuntimeError("OpenAI returned empty plan.")
    return text


def _read_doctrine_excerpt() -> str:
    path = DOCS_ROOT / "ark-engineering-doctrine-v1.md"
    if not path.exists():
        return "Plans are falsifiable hypotheses. Observers measure; reviewers decide."
    lines = path.read_text(encoding="utf-8").splitlines()
    body: list[str] = []
    for line in lines:
        if line.startswith("#"):
            continue
        if line.strip() == "---":
            if body:
                break
            continue
        if line.strip():
            body.append(line.strip())
    return "\n".join(body[:12])
