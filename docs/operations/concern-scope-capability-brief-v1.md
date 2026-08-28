# Concern → Scope Capability Brief v1

**Status:** Earned · Brief only · Not doctrine · Not closed  
**Discovered by:** Writing RO #1644 (front/rear brakes) — customer statement attached to one approval unit; splitting front vs rear lost the narrative or forced combined approval.  
**Not:** Service Catalog · Estimate Editor redesign · reusable repair templates · visit-level narrative

---

## Mission

Represent **independently approvable work** beneath a **single customer concern**.

```text
Customer says it     → Concern
Advisor recommends it → Scope
Technician performs it → Repair Action
```

Three truths. Three moments. One parent chain.

---

## Owns / Does not own

| Authority | Owns | Does not own |
| --- | --- | --- |
| **Concern** | Why they’re here · customer statement · problem identity | Approval · disposition · line totals · reusable templates · billing posture (unassigned until write path forces it) |
| **Scope** | What we’re recommending · approval/disposition · summary · totals · repair actions · labor · parts · scope notes | Customer wording · service catalog · Operation Class · pricing policy |
| **Repair Action** | Named repair steps | Money · disposition · customer statement |

**Billing posture:** assign ownership only when the RO write path forces it — not in this brief.

---

## Invariants

1. **A Scope cannot exist without exactly one parent Concern.**
2. **A Concern may exist with zero or more Scopes.**
3. Customer statement lives on the Concern — never duplicated onto Scopes, never required on Visit for multi-concern visits.
4. Disposition and billable totals live on the Scope — not on the Concern.
5. This capability is **not** a Service Catalog. No reusable repair template authority here.

---

## Litmus (brake RO)

Customer: *“Need front and rear brakes. Noise on hard stops.”* → **one Concern**  
Inspection: front pads/rotors + rear pads/rotors → **two Scopes**  
Customer: approve front, defer rear → **independent Scope dispositions**  
Six months later: *“Why did I come in?”* → Concern customer statement — not *Front Pads & Rotors*

---

## Migration (discipline)

Mental model first. Operational truth second. Code names last.

| Step | Do | Do not |
| --- | --- | --- |
| 1 | Keep `RepairOrderConcern` as today’s parent implementation | Rename everything to `Concern` |
| 2 | Introduce a **child Scope** authority under that parent | Rip apart the estimate editor / new estimate screen |
| 3 | Move approval, disposition, totals, repair actions, labor/parts to the child | Put customer statement on Visit or on Scope titles |
| 4 | Leave customer statement on the parent | Build Service Catalog / templates |
| 5 | Prove on one brake-shaped RO | Platform-wide rename of `RepairOrderConcern` |

Only after the slice feels natural on real ROs: decide whether `RepairOrderConcern` becomes `Concern` in code. Same rename-last discipline as Repair Authority / Pricing.

**Today’s collapse:** `RepairOrderConcern` wears both hats (customer problem + authorization unit). The child Scope is the missing hat — not a missing Concern.

---

## Vertical slice (implement only this)

**Brake-shaped RO**

1. One Concern with customer statement: brake noise / front+rear request.
2. Two Scopes: Front Pads & Rotors · Rear Pads & Rotors.
3. Independent approve / defer on each Scope.
4. Concern statement remains once; neither Scope title carries the paragraph narrative.
5. Existing single-hat ROs keep working (compat path: one Concern ↔ one Scope, or migrate existing rows 1:1).

**Out of slice:** editor redesign, mobile parity, catalog, billing posture ownership, renaming `RepairOrderConcern`, inspection attachment UX beyond what’s required for the two scopes.

---

## Evidence gate

After the slice ships: write ~10 real repair orders.

- Feels natural → earn foundational status (doctrine / close later).
- Still fights → refine from floor pressure before committing the platform.

No doctrine `.mdc` until that evidence.

---

## Companions (consume, don’t reopen)

| Capability | Relationship |
| --- | --- |
| Estimate Pricing Engine | Scope lines still snapshot rates; this does not reopen pricing |
| Operation Authority | Lines still resolve Operation Class; this does not own operations |
| Repair Action (`RepairOrderWorkGroup`) | Remains named steps under Scope |

---

## One sentence

**Concern owns why they’re here. Scope owns what they approve. Repair Action owns what we perform.**
