# Parked Observation — Lifecycle Separation

**Status:** Observed. Not yet frozen as platform doctrine.  
**Date parked:** 2026-07-20  
**Neighborhood so far:** Repair-order lifecycle (and adjacent pricing/operation authorities)

Not a `.cursor/rules` doctrine. Not a build ticket. A strong hypothesis waiting for evidence **outside** this neighborhood.

---

## Current hypothesis

1. **Authorities begin when the operational truth they own begins** — not when the UI needs a field.
2. **Authorities may legitimately have no children yet.** Downstream truth is never forced because upstream exists.
3. **The absence of a truth is not itself a state** — it is the absence of the authority that owns that truth.

| Wrong | Right |
| --- | --- |
| Unsure Visit | No Visit |
| Fake authorization headline | Concern with zero Scopes |
| Pending before anything is presented | No Approval |
| Recalculating a price that was never taken | No Snapshot |

---

## Evidence so far (same neighborhood)

| Capability | Early mistake | Mature / hypothesized model |
| --- | --- | --- |
| Estimate Pricing | Prices recompute | Snapshot when priced |
| Operation Authority | Service catalog | Own Operation Class only |
| Visit | Starts with RO | Starts when logistics begin |
| Concern | Starts with recommendation | Starts when customer speaks |
| Scope | Carries complaint + recommendation | Starts when something is approvable |
| Approval | Exists before present | Starts when something is presented |

Five-plus examples → pay attention. All still inside repair-order lifecycle → **not yet platform law**.

---

## Freeze gate

Freeze only after this hypothesis **explains a future capability outside the repair-order lifecycle without modification**.

### Validation criteria

This observation graduates to doctrine only if it **repeatedly predicts** the correct ownership and lifecycle of authorities in **unrelated** capabilities **before implementation**, without requiring exceptions or special-case wording.

Potential validation domains:

- Communications
- Documents
- Customer Portal
- Market Operations
- Stinson Rides

If those capabilities naturally follow the same lifecycle separation, the hypothesis has become a platform principle. If they require modifications, exceptions, or different wording, **revise or discard** the hypothesis rather than forcing it to fit.

**Likely first test:** Communications — if, without trying to prove this note, you naturally say things like *“A Conversation exists before it has any Observations”* or *“An Observation doesn’t exist until behavior actually occurs”* using the same reasoning, that is independent evidence.

Until then: **exceptionally strong hypothesis. Not doctrine.**

### Explicitly not doing

- No `.cursor/rules` file
- No milestone
- No backlog item
- No Concern → Scope redesign because of this observation

---

## Related floor notebook (Concern → Scope)

Not implementation. Observation questions while writing real ROs:

1. Multiple concerns per RO?
2. Every scope under exactly one concern?
3. Every scope independently approvable?
4. Customer statement spanning multiple concerns?
5. Every labor line under exactly one scope?

Do not redesign Concern → Scope until those hold (especially #5).

---

## Why not freeze today

Pricing snapshot immutability and Operation-owns-class earned doctrine from **converging pressures** across many situations. Lifecycle Separation has repetition — but still one product neighborhood.

Freezing it now would violate the same discipline that produced those doctrines: **observe → wait for repetition → freeze only when earned.**
