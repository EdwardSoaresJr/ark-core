from __future__ import annotations

import json
import uuid
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

GOAL_FILE = "current-goal.json"
TASK_FILE = "active-task.json"
EVENTS_FILE = "events.jsonl"


def utc_now() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


class EventStore:
    def __init__(self, state_dir: Path) -> None:
        self.state_dir = state_dir
        self.state_dir.mkdir(parents=True, exist_ok=True)

    @property
    def goal_path(self) -> Path:
        return self.state_dir / GOAL_FILE

    @property
    def task_path(self) -> Path:
        return self.state_dir / TASK_FILE

    @property
    def events_path(self) -> Path:
        return self.state_dir / EVENTS_FILE

    def load_goal(self) -> dict[str, Any]:
        return self._read_json(self.goal_path)

    def load_task(self) -> dict[str, Any]:
        return self._read_json(self.task_path)

    def save_goal(self, goal: dict[str, Any]) -> None:
        self._write_json(self.goal_path, goal)

    def save_task(self, task: dict[str, Any]) -> None:
        self._write_json(self.task_path, task)

    def append_event(self, event_type: str, payload: dict[str, Any]) -> dict[str, Any]:
        event = {
            "id": f"evt-{uuid.uuid4().hex[:12]}",
            "type": event_type,
            "at": utc_now(),
            "payload": payload,
        }
        with self.events_path.open("a", encoding="utf-8") as handle:
            handle.write(json.dumps(event, ensure_ascii=False) + "\n")
        return event

    def read_events(self) -> list[dict[str, Any]]:
        if not self.events_path.exists():
            return []
        events: list[dict[str, Any]] = []
        for line in self.events_path.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line:
                continue
            events.append(json.loads(line))
        return events

    def latest_event(self, event_type: str) -> dict[str, Any] | None:
        for event in reversed(self.read_events()):
            if event.get("type") == event_type:
                return event
        return None

    def latest_events_by_prefix(self, prefix: str) -> list[dict[str, Any]]:
        seen: set[str] = set()
        found: list[dict[str, Any]] = []
        for event in reversed(self.read_events()):
            etype = str(event.get("type", ""))
            if not etype.startswith(prefix):
                continue
            if etype in seen:
                continue
            seen.add(etype)
            found.append(event)
        return found

    def _read_json(self, path: Path) -> dict[str, Any]:
        if not path.exists():
            raise FileNotFoundError(f"Missing state file: {path}")
        data = json.loads(path.read_text(encoding="utf-8"))
        if not isinstance(data, dict):
            raise ValueError(f"Expected object in {path}")
        return data

    def _write_json(self, path: Path, data: dict[str, Any]) -> None:
        path.write_text(json.dumps(data, indent=2) + "\n", encoding="utf-8")
