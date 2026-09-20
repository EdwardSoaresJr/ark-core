# Workflow Completion Certification

**Status:** Active — how we earn the north star on the floor  
**North star:** [operation-follows-operator-v1.md](../product/operation-follows-operator-v1.md) — **the operation follows the operator**  
**Architecture:** Frozen. Observations → Continuity (projection) → Surfaces.

---

## North star (one level up)

Not Phone-First Shop. Not mobile vs desktop.

> **The operation follows the operator.**

Work happens where the work is happening — Front Counter, parking lot, Bay 3, office. Devices (phone, VVX, tablet, desktop, watch) are **views** into the same continuity. Stations are **places**, not computers.

The wow moment: Edward never wonders *did that save?*, *which screen was I on?*, or *I need my computer.*

[Phone-First Shop](../product/certifications/phone-first-shop.md) is **derivative evidence** — a week on the floor without device-thinking — not the top-level product.

---

## Moat

ARK is designed from **observed shop operations**, not software modules.

Most systems: Customers · Vehicles · Invoices → tables.  
ARK: Customer arrives · Phone rings · Tech starts work · Vehicle pickup → **how people spend their day**.

Operation videos protect that starting point. See [operations/README.md](../operations/README.md).

---

## Primary artifact: operation videos

**Film operations, not certifications.**

| Certification | Operation |
| --- | --- |
| "Did we pass?" | "How does Edward actually work?" |

Ten-operation catalog (Demo Auto Repair reference implementations): `01-customer-arrival` through `10-warranty-claim`. Videos gitignored under `docs/operations/videos/`; index in README.

Other shops record their own sets — videos are **executable examples**, not platform requirements.

---

## Engineer sentence

> **If you can't point to a real operation this change improves, you probably aren't improving ARK.**

---

## PR acceptance

**Re-record the operation.**

Merge when the new recording has fewer interruptions, searches, forgotten steps, and context switches.

---

## Primary vs secondary surfaces

| Where work happens | Where depth happens |
| --- | --- |
| Counter, lot, bay, walk (any device) | Office desktop: deep estimates, reporting, admin, accounting |

When Edward opens desktop, authority already reflects floor work. No sync. No catch-up.

---

## Workflow chain (example — Customer Arrival)

```
Customer arrives (lot or walk-in)
        ↓
Find / create customer
        ↓
Scan VIN → verify vehicle
        ↓
Capture concern → take photos
        ↓
Create / open RO → assign technician
        ↓
Done — desktop never touched
```

---

## Surface grammar (every surface)

Every operator surface — Home, Customer, Vehicle, RO, Shop, Bay, VVX, Watch — exposes:

| Layer | Operator question |
| --- | --- |
| **Current Situation** | What's happening? What's true? |
| **Next Best Action** | What should I do? What needs me? |
| **Quick Actions** | Can I do it right here? |

Shop Walk (#5) is where **station architecture** becomes product: every station answers the same three questions without search.

---

## ARK Staff / Flutter design gate

Stop asking: *"What screen do I build?"*

Ask:

> **What can Edward finish while standing next to a vehicle?**

That question naturally produces:

- Bigger buttons
- Camera-first / scan-first flows
- Fewer dialogs
- Persistent context (no thread loss)
- Offline tolerance where operation requires it
- One-handed operation

---

## PR gate

> **If you can't point to a real operation this change improves, you probably aren't improving ARK.**

**Acceptance:** Re-record the operation. Fewer interruptions, searches, forgotten steps, context switches → merge.

| Good | Weak |
| --- | --- |
| "`01-customer-arrival` re-record: no search after lot photos" | "Home screen improved." |
| "`03-technician-inspection`: never leaves RO context" | "API endpoint added." |

Infrastructure PRs name **which operation** they unblock.

---

## What we do not certify

- "Continuity" as a product milestone (projection infrastructure only)
- Screen completeness or tab coverage
- "Mobile parity" with desktop
- Firebase / push transport alone

---

## Evening / standup

**Evening:** Did today's work make an operation recording smoother to re-shoot?

**Standup:** Which operation video are we closer to recording?

---

## Companions

- [operations/README.md](../operations/README.md) — **start here for engineers**
- [phone-first-shop.md](../product/certifications/phone-first-shop.md)
- [CURRENT_MILESTONE.md](./CURRENT_MILESTONE.md)
- [ACTIVE_PR.md](./ACTIVE_PR.md)
