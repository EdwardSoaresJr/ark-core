# Technician Scope Doctrine v1

**Status:** Active — north star for technician experience; discovery contract is next milestone  
**Companion:** [Repair Order Discovery Contract](repair-order-discovery-contract.md) (draft) · [Technician Discovery Audit](technician-discovery-audit.md) · [Technician Surface Principle](technician-surface-principle.md) · [Inspection Workflow Principle (1.5)](../inspection/inspection-workflow-principle-1.5.md)

---

## The distinction that matters

Many shop systems assume:

```
Technician = low-permission staff member
```

ARK assumes:

```
Technician = specialized production role
```

Those are not the same thing.

| Low-permission mental model | Production-role mental model |
|----------------------------|------------------------------|
| Hide some buttons | Never enter most surfaces |
| Leave most surfaces available | Assigned work only |
| Maybe make things read-only | Earn visibility through evidence |

ARK's history leaned toward **everyone gets access → hide things later**.

This doctrine moves toward **assigned work only → earn additional visibility through evidence**.

For a single-shop operation like Demo Auto Repair, that is closer to reality.

---

## What technicians are not

Technicians are **not**:

- Junior advisors
- Customer relationship managers
- Communication owners
- Shop coordinators

Technicians are **production users** whose responsibility is **assigned work**.

---

## Technician authority (what they own)

Technicians own:

- Assigned repair orders
- Inspection recording
- Diagnostic observations
- Work performed
- Labor completion
- Required parts visibility
- Vehicle history relevant to assigned work
- Internal production notes

Technicians do **not** own:

- Customers
- Customer communications
- Follow-ups
- Decision pressure
- Approval workflows
- Scheduling pressure
- Shop-wide operational queues
- Financial data
- Estimate management
- Global customer search
- Global vehicle search
- Global repair order visibility

---

## Visibility rule

A technician should only see information required to:

1. **Diagnose** assigned work
2. **Perform** assigned work
3. **Document** assigned work

If a surface cannot justify itself through one of those three purposes, it **does not belong** in the technician experience.

**Default answer when proposing technician visibility:**

> No — unless it directly supports assigned work.

**The burden of proof is on any surface seeking technician visibility.**

Do not expand technician access for convenience. Observe actual technician behavior first.

---

## Navigation principle (target)

Technician navigation should trend toward:

```
My Work
Assigned Repair Orders
Inspection
ARKademy
```

Not:

```
Work
Communications
Customers
Vehicles
Shop-wide pressure surfaces
```

Nav labels and route scope may evolve during observation. The principle is **assigned production**, not **shop coordination**.

---

## Communication principle

Technicians may **consume** communication context attached to assigned work.

Technicians do **not**:

- Own communication queues
- Own communication recovery
- Own customer relationship workflows

Communication visibility exists **only when it directly supports assigned work** — not as a primary destination or interrupt surface.

---

## Landon's actual questions

A technician on the floor needs to answer:

- What vehicle is mine?
- What am I doing?
- What did I find?
- What parts am I installing?
- What work did I perform?

They do **not** need to know:

- Who has an overdue follow-up
- Which estimate is waiting for approval
- Which customer text needs a response

Everything else must **justify its existence** in the technician experience.

---

## Current enforcement vs this doctrine (honest gap)

**Shipped (`production.access` split):** Technicians cannot access Work, Communications, customer/vehicle search, or advisor comms interrupt. They land on Operations (workboard), not Work.

**Not yet aligned with v1 scope (observation-driven next steps):**

| Doctrine target | Current state |
|-----------------|---------------|
| **My Work** (assigned only) | Operations workboard may still show shop-wide lanes, not strictly assigned ROs |
| **Assigned Repair Orders** | Global repair order index/search still available to technicians |
| **Inspection** as primary nav destination | Inspection lives inside RO workspace, not top-level nav |
| **Comms on RO** | Context may appear on assigned RO; scope should stay read-only / assigned-work-only |

Do **not** close these gaps by guesswork. Measure whether shop-wide workboard or global RO search is actually used; then narrow scope with evidence.

---

## Observation rule

Before adding technician visibility, ask:

1. Is this for **assigned work** (diagnose, perform, document)?
2. Did floor behavior **prove** the need?
3. Would an advisor-only surface already solve it?

If any answer is no, reject the change.

**Observation questions (Demo Auto Repair):**

- When you clock in, where do you go first?
- What pages do you use all day?
- Do you search all repair orders, or only yours?
- Do you need shop-wide lane visibility, or only your bay?

---

## ARK sequence

```
Doctrine → Authority → Observation → Workflow → Projection
```

| Phase | Technician scope |
|-------|-------------------|
| **Doctrine** | This document |
| **Authority** | `production.access`, assigned RO lifecycle, inspection write — not shop CRM |
| **Observation** | **Current** — behavior before narrowing workboard / RO index |
| **Workflow** | My Work, assigned RO entry, inspection-first recording |
| **Projection** | Customer-facing views read inspection truth — later |

Do not skip observation to “finish” the technician UI.

**Next milestone:** [Repair Order Discovery Contract](repair-order-discovery-contract.md) — how ROs are discovered, not just who may open them.

---

## Anti-patterns (reject)

- Granting `operations.access` to technicians for convenience
- Read-only Work or Communications “so they can see what’s going on”
- Global customer or vehicle search for production staff
- Shop-wide decision pressure on technician surfaces
- Expanding nav because advisors use a surface — that is advisor workflow
- Hiding buttons while leaving routes accessible (permission theater)

---

## Success criterion

The technician experience succeeds when a production user can complete a day without entering advisor territory:

```
Clock in → My assigned work → RO → inspect / perform / document → next vehicle
```

No follow-up queue. No approval pressure. No relationship recovery. No shop-wide coordination — unless observation proves a specific assigned-work exception.
