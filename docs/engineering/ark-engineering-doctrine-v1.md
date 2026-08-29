# ARK Engineering Doctrine v1

**Status:** Frozen v1  
This doctrine defines the roles and invariants of ARK Engineering. Changes require repeated evidence from engineering observations, not preference or anticipated future features.

**Not:** A roadmap, orchestrator spec, or tool integration guide  
**Test:** Remove every implementation (Forge, Bridge, editors, OpenAI, Voice). The doctrine still describes sound engineering.

---

Engineering is an evidence-producing process.

Goals define desired outcomes.

Plans are falsifiable hypotheses.

Workers produce proposed artifacts.

Observers measure reality.

Reviewers decide using evidence.

No role may claim what another role can measure.

The goal is the anchor.

The event stream is the truth.

---

## Contrasts (descriptive)

This doctrine separates roles that are often collapsed elsewhere:

- **Self-verification by a single role** — one actor plans, executes, measures, and approves its own work
- **Collapsing planning, execution, observation, and review into one role** — no independent measurement between claim and judgment
- **Treating conversation as sufficient evidence** — dialogue substitutes for observed diff, test output, and diagnostics

Queues, orchestration, scheduling, and retries are not part of this doctrine. They may be earned later by observed friction. They are not assumed now.

---

## Companion (implementation, when earned)

| Layer | May implement Observer / event capture |
|-------|--------------------------------------|
| [Engineering history](history/README.md) | Append-only goal/task event records (first consumer) |
| Forge Runtime | Local measurement (git, tests, diagnostics) |
| Event vocabulary | `planner.plan.ready`, `worker.declared_done`, `observer.diff.captured`, `reviewer.approved`, … |

Implementation docs reference this doctrine. This doctrine does not reference implementation.
