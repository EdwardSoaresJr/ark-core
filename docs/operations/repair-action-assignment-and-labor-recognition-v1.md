# Repair Action Assignment & Labor Recognition Authority v1

**Status:** Frozen · 2026-08-03 · **Doctrine only — not yet implemented**  
**Category:** Operations foundation (same class as Inspection · Maintenance · Evidence · Customer Recognition · Financial Authority)  
**Companions:** [`concern-scope-capability-brief-v1.md`](concern-scope-capability-brief-v1.md) · [`technician-compensation-flag-floor-v1.md`](technician-compensation-flag-floor-v1.md) · Technician Scope · Projection Rule

**Not Financial Authority.** Money stays Estimate · Ledger · Final Invoice. This owns **who owns the package**, **when the package is complete**, and **which labor has already been paid as flag**. Financial Authority remains RED until F1; do not couple this freeze to invoice work.

---

## Architectural shift (one sentence)

**Repair Orders organize customer work. Repair Actions organize technician work.**

Everything in this freeze follows from that: tech sheets, pending work, ownership, parts riding with labor, evidence, maintenance, recognition, assists.

---

## Philosophy

**Repair Action Assignment answers “Who owns this package of work?” Labor Recognition answers “Which labor lines from that package have already been paid?”**

Ownership and performance are not always the same person. Recognition is never “the RO was paid.”

Two sibling authorities. Never collapse them into RO assignment.

| R1 is | R1 is not |
| --- | --- |
| The first true unit of shop work that belongs to a technician | “Assign technicians” onto repair orders |

---

## Why this exists

Two floor failures collided:

| Problem | Wrong model | Reality |
| --- | --- | --- |
| **Multi-tech RO** | `repair_orders.assigned_technician_id` owns the job | Edward diagnoses · Landon installs starter · Caleb oils — one RO, three packages |
| **Diag Friday / repair Monday** | Recognition / pending attributed from the whole RO | Diag 1.0 already recognized Friday must never reappear Monday when starter 3.2 sells |

RO cannot own technician assignment. Recognition cannot scan “the RO.” Tech sheets cannot be “filter the RO.”

---

## Three layers

```text
Customer says it              → Concern
Advisor recommends it         → Scope (approval unit — see Concern→Scope brief)
Accountable party owns it     → Repair Action
Labor line is paid as flag    → Labor Recognition
```

| Layer | Code today (do not rename yet) | Operator language |
| --- | --- | --- |
| Concern | `RepairOrderConcern` (also wears Scope hat until Scope splits) | Why they’re here |
| **Repair Action** | `RepairOrderWorkGroup` | What we perform (package) |
| Labor / parts / sublet / notes | `RepairOrderLine` under the work group | Contents of the package |
| Flag recognition | `technician_flag_recognitions` + `_lines` | Labor paid once |

**Rename last.** Operator language is **Repair Action**. Engineer model may stay `RepairOrderWorkGroup` until adoption earns a rename.

---

## Authority A — Repair Action Assignment

### Primary question

**Who owns this package of work?**

Not merely “who is turning the wrench right now.”

### Ownership identity — `RepairActionOwner` (freeze now)

Do **not** freeze ownership as a bare `owner_technician_id` you will eventually regret.

Freeze:

```text
RepairActionOwner
  = the accountable party for the Repair Action
```

| Today (R1) | Later (only when earned) |
| --- | --- |
| **Technician** — only owner type | Team (transmission, diagnostics, …) |
| | Vendor / Sublet |
| | Unassigned |
| | Training pair (via **R4 assists** — not dual owners) |

No polymorphism required in R1. Doctrine requires: **the owner is the accountable party; today the only owner type is Technician.**

R1 may persist a technician id **behind** `RepairActionOwner` of type Technician. Model the **owner concept**, not “the technician column on the work group.”

Owner vs performer (freeze the distinction; R1 performer may default to owner):

```text
Check Engine Light — Repair Action
──────────────────────────────────
Owner (RepairActionOwner)  Edward
Performing tech            Landon   ← may install; does not steal ownership
```

### Owns

| Concept | Meaning |
| --- | --- |
| **`RepairActionOwner`** | Exactly one accountable party for the package |
| Work package | Labor + parts + sublets + notes + future evidence / maintenance / inspection follow-up |
| **Package completeness** | Whether the *package* is done — not labor alone |
| **Ownership transfer history** | Assign / transfer events — never simultaneous owners |

### Does not own

Payroll dollars · Estimate approval · Financial Position · Invoice · Coverage · Flag recognition (sibling) · Helper / assist time (R4)

### Hard invariants

1. **Exactly one `RepairActionOwner` at a time.**
2. **Ownership transfers. It is never copied.**
3. **Never dual-own a Repair Action.** Collaborate → **split into two Repair Actions**.
4. Ownership lives on the Repair Action — never on the RO as authority.
5. Labor does not own parts; parts ride with the Repair Action.
6. RO `assigned_technician_id` = **Primary Technician** — visibility / dispatch hint only.
7. **A technician never receives a Repair Order. A technician receives owned Repair Actions.**
8. **Repair Action Complete ≠ labor complete.** Completeness is package completeness.
9. **Completion ownership:** **Completed by** = the **current `RepairActionOwner`** (including after transfer) — never “last technician who touched a labor line,” never an R4 helper as completer by side effect.

### Transfer semantics (frozen)

```text
Starter Replacement
  Owner: Edward
       ↓ transfer (reason: shop balancing)
  Owner: Landon

History (append-only):
  09:12  Assigned → Edward
  13:47  Transferred → Landon  · Shop balancing
```

Not Edward and Landon both owning. Pending work stays deterministic: only the **current** owner’s queue includes the action.

### Collaboration → split packages

```text
Engine Replacement
  Repair Action: Remove Engine  → Owner Edward
  Repair Action: Install Engine → Owner Landon

Diagnosis → Owner Edward
Repair    → Owner Landon
```

Helper time is **not** a second owner. That is **R4 Assists / shared work**.

### Package completeness (frozen)

```text
Repair Action
  Labor complete?           ✓ / ·
  Parts installed?          ✓ / · / N/A
  Maintenance confirmed?    ✓ / · / N/A
  Evidence required?        ✓ / · / N/A
  Inspection follow-up?     ✓ / · / N/A
        ↓
  Complete
  Completed by = current RepairActionOwner
```

**Forbidden:** `Labor done → Complete` as the only path when other facets apply.  
**Forbidden:** attributing completion to the last line editor or an R4 helper.

Exact facet enforcement ships when those authorities attach; the **shape** is frozen now.

### Tech sheets are projections (frozen rule)

**A technician never receives a Repair Order. A technician receives owned Repair Actions.**

The RO is the customer-work container. The work packet is:

```text
Today's Work
  • Starter Replacement
  • Oil Service
  • Brake Flush
```

Not `RO #1842`.

```text
Caleb — Engine Oil Service
──────────────────────────
Labor     1.0
Parts     Mobil 1 · Filter · Washer
Notes     Reset oil life
Evidence  (before/after if required)
```

No filtering. No guessing. No “hide unrelated lines.”

### Pending production (target workload view)

Wrong: `Landon → RO 1842 · RO 1845 · RO 1850`  
Right: `Landon → Pending: Starter · Brake Flush · Timing Belt | Done: Oil Service`

Pending is a projection of **currently owned** Repair Actions.

---

## Authority B — Labor Recognition

### Owns

| Concept | Meaning |
| --- | --- |
| **Recognized labor line** | This flag hour identity has been paid — forever |
| `recognized_at` · recognition id · actor | Audit |
| Technician attribution snapshot | Who was paid for **this line** (defaults from **`RepairActionOwner`** at recognition when owner type is Technician) |

### Does not own

Assignment · Package completeness · Parts · Estimate · Invoice · RO primary tech

### Recognition rule (frozen)

**A labor line may be recognized exactly once.**

Never: RO / Concern / Estimate / Repair Action recognized as a pay unit.  
Only: **Labor line** (`repair_order_line_id`).

Coverage does not change recognition.

### Already shipped (preserve)

Phase 1A unique on `repair_order_line_id`. Reopen-safe. New labor can recognize later. **Keep that identity.**

### What is wrong today (replace)

| Today | Target |
| --- | --- |
| Attribution = RO assignee | Attribution from **`RepairActionOwner`** |
| Pending by RO assignee | Pending by **owned Repair Actions** |
| Tech sheets from RO | Tech sheets from **owned Repair Actions** |
| “Assign tech on RO” | Own via **`RepairActionOwner`**; RO primary = visibility |

### Friday / Monday

```text
Friday   Diagnosis (Owner Edward)  · Diag 1.0 → Recognized
Monday   Starter (Owner Landon)    · 3.2 → Recognized later
Payroll  Edward 1.0 · Landon 3.2 — diag never again
```

---

## Sibling split (do not merge)

| Authority | Question |
| --- | --- |
| **Repair Action Assignment** | Who **owns** this package of work? (`RepairActionOwner`) |
| **Labor Recognition** | Which **labor lines** have already been paid? |

Ownership **transfers** before or after recognition. After recognition, the labor line’s paid identity stays immutable; transferring the Repair Action owner does not rewrite past recognition.

---

## One question per surface

| Surface | Primary question |
| --- | --- |
| Repair Action | What package of work is this? |
| Ownership | Who is the accountable party? |
| Package completeness | Is this package fully done? |
| Tech sheet | What packages do **I** own? |
| Pending work | Which of **my** Repair Actions are still open? |
| Labor Recognition | Which labor lines are already paid as flag? |
| RO Primary Technician | Who is the visibility / dispatch lead? (not package ownership) |

---

## Explicit non-goals (this freeze)

- Dual owners on one Repair Action
- Copying ownership (must transfer)
- Bare `owner_technician_id` as the permanent vocabulary (use `RepairActionOwner`)
- Helper / assist time in R1 (that is R4)
- Team / vendor / training-pair owners in R1
- Payroll export / OT dollars / settlement UI
- Renaming `RepairOrderWorkGroup` in code
- Concern → Scope split implementation (separate brief)
- Financial Position / invoice / deposit work (Financial Authority RED)
- Auto-assigning ownership without advisor/tech intent

---

## Adoption sequence (do not skip)

| Milestone | Deliverable |
| --- | --- |
| **R0** | This doctrine freeze |
| **R1** | `RepairActionOwner` (type Technician only) + transfer history; UI; pending by current owner begins — **Shipped 2026-08-03** |
| **R1.1** | Status + **Latest Update** on Repair Actions; deprecate RO Primary Tech from production surfaces — **Shipped 2026-08-04 · observe** |
| **R2** | Package-based tech sheets polish (only if earned) |
| **R3** | Labor recognition attribution from `RepairActionOwner`; update `FlagRecognitionPolicy` |
| **R4** | Assists / shared work (helper time) — **not** dual ownership |
| **R5** | Observe shop friction (Complete, package-completeness facets) |

**Resist skipping to R3.** Ownership + operational communication are the foundation.

### R1 / R1.1 Observation Period — Locked

**Duration:** 2–4 weeks or ~100 Repair Actions (whichever comes first).

R3 is blocked because R1 changed the shop’s unit of work — not because recognition is hard. Floor evidence before building on top.

**Principle:** The current state of a repair must be discoverable from its Repair Actions. If an advisor must interrupt a technician to answer a customer, ARK failed.

**Stop rules during observation:** no Labor Recognition · no payroll · no assists · no Financial (RED) · no chat/timeline · no workflow redesign unless verified defect. Fix only bugs, missing information that blocks normal use, and verified usability defects.

**Earn R2/R3 from:** ownership habit · package trust · My Work fidelity · advisor answers from Latest Update without interruption — plus transfers/day, actions/RO, multi-tech share, sheet reprints, wrong-owner corrections, “Where are my parts?”, “Why is this on my list?”

---

## Relationship to existing docs

| Doc | Relationship |
| --- | --- |
| Concern → Scope brief | Repair Action remains the perform unit; this freeze adds **`RepairActionOwner`**, transfers, package completeness, package projections |
| Flag + floor compensation | Phase 1A line identity stays; RO-assignee attribution **superseded** by `RepairActionOwner` at R3 |
| Technician Scope | Technician work = owned Repair Actions, not whole ROs |
| Financial Authority v2 | Orthogonal; remains RED until F1 |

---

## Implementation stop / start

**Permitted when intentionally resumed:** R1 → R2 → R3 → R4 → R5 in order.

**Do not:** dual-own or copy ownership; ship bare `owner_technician_id` as the concept; assign only on labor lines; keep RO as ownership authority; give technicians RO packets instead of owned Repair Actions; attribute Complete to last line touch; recognize by RO/concern/estimate; reopen Financial invoice sync; skip to R3 before R1.
