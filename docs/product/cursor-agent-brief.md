# Cursor agent brief

**Framework frozen at v3.** Full catalog: [operational-certifications.md](./operational-certifications.md). Do not invent architecture, doctrine, or new certifications.

## Accountability (judge every decision)

**Does ARK know this before the operator has to?**

Not: does ARK have a feature? automate something? use AI?

If the operator is **retrieving**, ARK failed to anticipate. If they're **confirming** what ARK assembled, we're building ARK.

Certifications certify **anticipation** — e.g. Front Counter means ARK anticipated everything required for that station to work; Portable Station means Edward can leave the counter without leaving the operation.

**Anti-drift:** Search, filters, dashboards, reports, and configuration say *go find the answer.* ARK says *I already found it — do you agree?* If a feature answer is "nothing anticipated," we're probably building software.

**Notebook reframe:** What did ARK fail to anticipate? (Not just what thought escaped.)

**Feature challenge:** *What is ARK anticipating here that the operator would otherwise have to think about?*

---

## Closing instruction

The framework is complete. Do not improve the framework. **Improve the operator's day.** Every commit should remove one thought, one click, one search, or one decision from a real piece of shop work. When that makes an operational certification greener, you've made progress. When it doesn't, you probably haven't.

ARK competes on **reducing cognitive load** — not on Asterisk, Flutter, or architecture. Traditional software asks *can the user do this?* ARK asks *does the user even need to think about this?*

**Engineering mission:** Every unnecessary thought an operator has is a product bug — not every inconvenience, not every missing feature.

**Shop owner watch:** When you catch *"It would be nice if…"* — log it in the [Operator Notebook](./operator-notebook.md). Ask: *What thought did I just have that ARK should have had instead?*

**Protect the notebook.** Every entry is a real operator thought that escaped ARK — highest-value product bugs in the project.

**Outcome worth building toward:** *I stopped thinking about the software.*

## Before writing code

Answer all five. If you cannot, do not build.

1. **Which cognitive bug does this fix?** *(Entry from [Operator Notebook](./operator-notebook.md) — outcome, not click count.)*
2. **Which operational certification advances?**
3. **Which checklist row becomes greener?**
4. **Which business decision remains human?**
5. **Which implementation detail disappears?**

## Track dependency (not three independent silos)

```text
Operations Platform     creates truth
        ↓
Orientation Platform    turns truth into understanding
        ↓
Portable Station        delivers understanding wherever the operator is
```

Portable Station must not invent orientation. Orientation must not invent authority. Operations must not worry about screen density. Each layer has one job.

| Track | Current goal |
|-------|----------------|
| **A — Operations Platform** | Front Counter → Operational |
| **C — Orientation Platform** | Orientation v1 → Operational |
| **B — Portable Station** | Portable Station Phase 1 → Engineering |

## One metric worth watching

**Cognitive bugs fixed** — operator no longer thinks about X. One fix may remove clicks, screens, training, and support calls without tracking each mechanism separately.

Also: **average interruptions** to complete a piece of shop work (e.g. customer text: five steps → tap · reply).

## Floor priorities (next several days)

These are not features. They are moments where ARK removes cognitive load:

1. First VVX on the counter
2. Front Counter operationally certified
3. Portable Station in Edward's pocket (orientation home)
4. First push opens into context
5. First customer call entirely through ARK

When a certification level turns green: file a record in [certifications/](./certifications/) with evidence and proof. Move on.

## Principles (frozen — do not extend)

- Authorities own truth
- Stations own orientation
- Capabilities project workflow
- Operators express intent; ARK performs implementation
- Infrastructure is discovered, not configured

## Design question

Not *where should this page live?* — **what is the operator trying to accomplish?**

The platform is not being defined anymore. It is being earned.
