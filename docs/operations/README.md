# ARK Operation Videos

**Status:** Primary engineering artifact  
**North star:** [operation-follows-operator-v1.md](../product/operation-follows-operator-v1.md)

---

## Certification vs operation

| Certification video | Operation video |
| --- | --- |
| "Did we pass?" | "How does Edward actually work?" |
| Demo of a checklist | Recording of Demo Auto Repair running |
| Pass/fail gate | Reference implementation |

**Stop filming certifications. Film operations.**

Future engineers — including people who never met Edward — should watch these **before** reading doctrine. They should immediately understand why phone, continuity, stations, and observations exist — and why search is not the product.

---

## Catalog (Demo Auto Repair reference implementations)

Videos are **not requirements**. They are **executable examples** of how one shop runs. Another tenant records its own versions when ARK multi-shops.

Store files under `operations/videos/` (gitignored — too large for git). This README is the index.

| # | File | Operation (how Edward actually works) | Status |
| --- | --- | --- | --- |
| 01 | `01-customer-arrival.mp4` | Walk-in / lot arrival → find/create → VIN → concern → photos → RO → assign | ⬜ |
| 02 | `02-phone-call.mp4` | Incoming call → context → handle → note → done | ⬜ |
| 03 | `03-technician-inspection.mp4` | Landon at vehicle → inspection → photos → recommendations | ⬜ |
| 04 | `04-estimate-approval.mp4` | Send estimate → customer approves → RO updates | ⬜ |
| 05 | `05-vehicle-pickup.mp4` | Ready vehicle → invoice → payment → close | ⬜ |
| 06 | `06-shop-walk.mp4` | Edward walks bays → counter → parts without search | ⬜ |
| 07 | `07-opening-the-shop.mp4` | Morning open → stations → first moves | ⬜ |
| 08 | `08-closing-the-shop.mp4` | End of day → Day Review → handoff | ⬜ |
| 09 | `09-parts-arrival.mp4` | Parts hit shelf → RO/production notified | ⬜ |
| 10 | `10-warranty-claim.mp4` | Warranty path through shop | ⬜ |

Add operations as the floor earns them. Do not invent operations that Demo Auto Repair does not perform.

---

## How to record

- **Real shop**, real (or realistic) work — not scripted demos
- **~5–15 minutes** — long enough to see friction, short enough to re-watch
- **One operator perspective** where possible (Edward, Landon, Molly)
- **Internal only** until explicitly approved for external use
- Note date, participants, and RO/customer IDs in the index row when filmed

---

## Pull request acceptance

Evolved gate:

> **Re-record the operation.**

If the new recording has:

- fewer interruptions  
- fewer searches  
- fewer forgotten steps  
- fewer context switches  

…then the PR improved the product.

That is hard to game. A checklist can be faked. A smoother real operation is evidence.

When a PR touches an operation, attach or link the before/after recording (or timestamp note in PR body).

Secondary question:

> If you can't point to a **real operation** this change improves, you probably aren't improving ARK.

---

## Competitive moat (why these exist)

ARK is designed from **observed shop operations**, not software modules.

Most systems started with: Customers · Vehicles · Invoices · Scheduling → software organized by database tables.

ARK started with: Customer arrives · Phone rings · Technician starts work · Vehicle gets picked up → software organized by **how people spend their day**.

Operation videos protect that starting point as ARK grows and other shops record their own reference sets.

---

## Multi-shop (future)

Demo Auto Repair videos = **reference implementations**, not platform requirements.

Shop B may not warranty-claim the same way. Shop C may open differently. The platform must stay flexible enough that each shop records its own `operations/` catalog.

Doctrine explains *why*. Operation videos show *how this shop*.

---

## Onboarding (engineers)

1. Watch `01-customer-arrival.mp4` and `06-shop-walk.mp4` minimum  
2. Read [operation-follows-operator-v1.md](../product/operation-follows-operator-v1.md) (one page)  
3. Read [CURRENT_MILESTONE.md](../engineering/CURRENT_MILESTONE.md)  
4. Only then touch code  

Skip the fifty-page doctrine tour until a specific decision requires it.

---

## Companions

- [workflow-completion-certification.md](../engineering/workflow-completion-certification.md) — workflow checklists map to operations 01–06  
- [certifications/README.md](../product/certifications/README.md) — formal sign-off when needed; videos are primary
