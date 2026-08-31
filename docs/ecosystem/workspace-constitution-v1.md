# ARK Workspace Constitution v1

**Status:** Product constitution  
**Companion:** [workspace-surface-audit-v1.md](../operations/workspace-surface-audit-v1.md)

ARK is a **workspace operating system** — not shop management software organized by features, modules, or data models.

Every UI decision answers one question:

> **What work is happening here?**

Not: what data is shown, what model is edited, what feature this is.

---

## Law 1 — One Workspace = One Job

Every workspace owns exactly one operational responsibility.

| Workspace | Job |
|-----------|-----|
| **Work** | Coordinate today's repair orders |
| **Attention** | Recover customer communication |
| **Intake** | Convert new customers into repair orders |
| **Repair Order** | Execute one repair |
| **Today** | Produce today's work |
| **Day Review** | Close the day |

Not two. Not "mostly." Exactly one.

Same sentence, different role → **lens inside a workspace**, not a second page.

---

## Law 2 — Authority Never Moves

Every piece of information has one authority. Everything else is a projection.

Aligns with [ark-authority-vs-configuration.mdc](../../.cursor/rules/ark-authority-vs-configuration.mdc) and [ark-projection-rule.mdc](../../.cursor/rules/ark-projection-rule.mdc).

---

## Law 3 — Pages Own Work

| Unit | Owns |
|------|------|
| **Pages** | Jobs |
| **Panels** | Support jobs inside a workspace |
| **Dialogs** | Perform actions |
| **Projections** | Expose truth |
| **Settings** | Configure the system |

Nothing else exists.

---

## Law 4 — Navigation Represents Jobs

The rail answers: **What am I here to accomplish?**

It must never answer: **Here are all the things ARK can do.**

That is a capability explorer, not a workflow.

---

## Law 5 — New Surfaces Must Replace Something

Every new workspace proposal must answer:

> What existing surface becomes simpler because this now exists?

If the answer is **"Nothing"** — the workspace probably should not exist.

Growth must come with subtraction.

---

## Law 6 — Deletion Is a Feature

When a better workflow emerges, the old workflow is **technical debt** — not legacy, not "still useful."

Delete it.

---

## Law 7 — Search Is Infrastructure

Phase 1 removed Customers, Vehicles, and RO index from the rail. That only works if lookup is **faster than the old navigation**, not merely cleaner.

> **Every entity in ARK must be reachable from one global search in fewer than three interactions.**

Search is not a feature. It is infrastructure — the recovery path when interruption breaks context.

If this is not true, operators will ask for the old rail links back. That is a signal to fix search, not to restore capability-explorer navigation.

---

## Law 8 — Observation Before Optimization

No workflow may be redesigned based on intuition alone.

A workflow must first be observed in real use. Improvements come from **repeated observations**, not isolated discomfort.

Protects against both extremes: reacting to every annoyance, and ignoring recurring friction.

The shop floor is the authority for workflow — just as the RO is the authority for repair data.

Record observations in [floor-observations-july-2026.md](../operations/floor-observations-july-2026.md). Prioritize only after patterns cluster.

---

Every surviving page completes:

> **This page exists because** [one specific job] that [one role] performs [how often], and that job cannot be done inside [named primary workspace] without breaking scan rhythm or workflow continuity.

If two surfaces share the sentence → merge. If the sentence is vague → panel or delete.

---

## Named anti-pattern

**Persistent Surface Anti-Pattern**

A feature introduced a new surface. A later workspace absorbed the job. The original surface remained discoverable, creating duplicate navigation and fragmented workflow.

Sequence:

```
Capability shipped → surface persisted → better workspace absorbed the job → old surface not pruned
```

See [TECHNICAL_DEBT.md](../engineering/TECHNICAL_DEBT.md#persistent-surface-anti-pattern).

---

## Evaluation questions (every addition)

1. Does this need a **new workspace**?
2. Or is it a **projection** inside an existing workspace?
3. Or is it an **action** inside an existing workspace?

If (2) or (3), do not add a page, rail link, or nav tab.

---

## Interruption recovery (validation, not a law)

ARK is interruption-driven. Navigation must survive real shop rhythm — not happy-path clicks only.

> **Can I recover from being interrupted without rebuilding my mental model?**

See [workspace-surface-audit-v1.md](../operations/workspace-surface-audit-v1.md#phase-1-completion-click-through) — Test 6.
