# ARK Engineering History

**Status:** Active — first consumer of [ark-engineering-doctrine-v1.md](../ark-engineering-doctrine-v1.md)  
**Not:** A queue, orchestrator, or tool. Append-only event records for how ARK is built.

## Relationship to other engineering docs

| Document | Purpose |
|----------|---------|
| **This directory** | Replayable engineering loop — goal, hypotheses, proposed artifacts, measured evidence, decisions |
| [IMPLEMENTATION_LOG.md](../IMPLEMENTATION_LOG.md) | What shipped (PR-oriented, append-only) |
| [research/forge-observation-notebook.md](../research/forge-observation-notebook.md) | Unstructured friction before patterns cluster |
| [adr/](../adr/) | Frozen architectural decisions |

The notebook discovers. The history records. ADRs freeze. The implementation log ships.

## Question after every task

Not *"Did it succeed?"*

**What evidence changed my mind?**

## Layout

```
history/
  goals/          — stable anchors (outlive tasks)
  tasks/          — one file per task; event stream inside
```

## Task file template

```markdown
# Task: {title}

**Goal:** [{goal}](../goals/{goal-slug}.md)
**Worker:** human | codex-cli | …
**Status:** open | stopped | complete

---

## Events

### goal.referenced
{when this task was opened against the goal}

### planner.hypothesis.proposed
**Hypothesis:** …
**Falsifiable by:** …

### worker.declared_done
**Proposed artifacts:** commit(s), files, summary
**Declaration only** — not verified until observer events follow.

### observer.evidence.captured
**Source:** curl, bench gate, test run, git diff, …
**Measured:** …

### reviewer.decision.recorded
**Decision:** approved | stopped | changes_requested
**Because:** … (must cite observer evidence, not worker claims)
```

Event types may grow. Append events; do not rewrite prior events. Correct mistakes with a new event that references the prior one.

## Dogfooding rule

Run the next real task using this format before building Console, automation, or JSON schemas. Five to ten real tasks earn tooling.
