# ARK Engineering v0.1

**Not** a chat client, Bridge feature, provider, or orchestrator.

One engineering loop:

```
Goal → Task → Plan → Worker → Observer → Reviewer
```

Doctrine: [`docs/engineering/ark-engineering-doctrine-v1.md`](../../docs/engineering/ark-engineering-doctrine-v1.md)

## Prerequisites

- Python 3.11+
- ARK Bridge + Forge Core running (optional for git status — local git fallback works)
- Copy `engineering.env.example` → `.env` or reuse `ark-forge/console/.env` for `ARK_BRIDGE_TOKEN`

## Install

```powershell
cd ark-forge\engineering
python -m venv .venv
.\.venv\Scripts\pip install -r requirements.txt
copy engineering.env.example .env
# Fill ARK_BRIDGE_TOKEN (ark-bridge pair) and optional OPENAI_API_KEY
```

## Run

```powershell
cd ark-forge\engineering
.\.venv\Scripts\python -m ark_engineering
```

Open **http://127.0.0.1:19472** (override with `ENGINEERING_PORT` in `.env`)

You should see:

- Current goal + active task
- Plan (seeded for G4 certification)
- Worker actions: **Start Work** → work in Cursor → **Declare Done**
- Observer: **Capture Evidence** (git status via Bridge when available, diff via local git)
- Reviewer: manual approve/changes or OpenAI review

No chat prompt. Buttons only.

## State files

```
ark-forge/engineering/state/
  current-goal.json
  active-task.json
  events.jsonl      ← append-only truth
```

Re-seed (destructive to events):

```powershell
python -m ark_engineering seed --force
```

## Smoke tests

```powershell
.\.venv\Scripts\python scripts\smoke.py
.\.venv\Scripts\python -m pytest tests -q
```

## v0.1 scope (frozen)

- One goal, one task
- `human-cursor` worker only
- No queues, scheduling, retries, worker registry, Cursor automation

## Event types

- `goal.created`, `task.created`
- `planner.plan.ready`
- `worker.started`, `worker.declared_done`
- `observer.git_status.captured`, `observer.diff.captured`, `observer.tests.captured`
- `reviewer.approved`, `reviewer.requested_changes`
