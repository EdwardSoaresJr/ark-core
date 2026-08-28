# ARK CHECKPOINT — PRE-HOME INVERSION

**Date:** 2026-06-09  
**Status:** Complete — all four sprints shipped (2026-06-09, commit `2822304`)  
**Related:** [Communications Authority](../communications-authority.md) (authority boundaries unchanged)

---

## Purpose

Create a rollback point before Sprint 1 (Home / Attention identity pass).

This checkpoint preserves the current product posture in case the Home-first experiment proves incorrect on the shop floor.

---

## Current State (Checkpoint)

### Primary surfaces

| Surface | Route | Role |
|---------|-------|------|
| **Home** | `/app` | Attention + Shop Pressure |
| **Workboard** | `/app/workboard` | Full lane-based operational board |
| **Customer Hub** | `/app/customers/{customer}` | Relationship context, communications, open ROs |
| **RO Workspace** | `/app/repair-orders/{repairOrder}` | Estimate / review / execution authority |
| **Communications** | Telephony, SMS/MMS, Quick Reply, Estimate Links, Payment Links | Customer relationship ingress and action |

### Current doctrine posture

- Customer pressure and shop pressure are both visible.
- Home exists but product identity is **not fully committed**.
- Workboard remains a psychologically primary surface in several workflows.

---

## Experiment Being Introduced

**Declare Attention as the front door.**

### Target hierarchy

```
Attention
    ↓
Customer
    ↓
RO
    ↓
Work
```

- **Customer pressure** becomes primary.
- **Shop pressure** becomes secondary context.
- **Workboard** becomes a deliberate operational drill-down — not the default mental model.

### Two pressure systems (not two products)

| | Customer pressure | Shop pressure |
|---|---|---|
| **Question** | Who needs me? | What's stuck in the building? |
| **Examples** | Missed call, unread text, "Can I pick up today?" | Waiting approval, parts hold, bay clog, ready pickup |
| **Home band** | Since Last Shift, Needs Attention | Waiting Approval, Parts, Shop Floor, Ready Pickup |
| **Drill-down** | Customer Hub, reply, intake | Workboard lanes |

---

## What May Change

- Home composition
- Navigation language
- Search language
- Pressure hierarchy
- Workboard prominence
- Customer-first routing patterns

---

## What Must Not Change

### No authority changes

Do **not** modify:

- Conversation authority
- CallSession authority
- RepairOrder authority
- Workflow authority
- Customer authority

### No schema changes required for the experiment

### No new attention authority

**Specifically prohibited:**

- `AttentionItem`
- Inbox table
- Queue record authority
- Parallel communications authority

Communications infrastructure remains authoritative. Home is a **projection and routing experiment**, not a new domain.

---

## Success Criteria

Advisors naturally start their day with:

```
Home → Customer → RO
```

instead of:

```
Workboard → RO
```

…without being instructed to do so.

---

## Failure Criteria

- Advisors repeatedly bypass Home.
- Workboard remains the practical first screen.
- Customer pressure and shop pressure become **harder** to understand.
- Operational throughput decreases.

---

## Rollback Criteria

If the experiment fails:

1. Keep Attention Queue as a **projection** (no new authority).
2. Restore Workboard as the primary operational landing surface.
3. Retain all communications infrastructure.

Do **not** remove:

- Relationship Context
- Telephony
- SMS/MMS
- Customer Hub
- Quick Reply
- Estimate Links
- Payment Links

Those remain valuable regardless of landing-page strategy.

---

## Important Distinction

This sprint is a **product identity experiment**.

It is **NOT**:

- A communications rebuild
- A workboard rebuild
- An authority rewrite
- A CRM initiative
- A Tekmetric clone

### The decision under test

Whether ARK's primary operating surface should be:

```
Attention → Customer → RO → Work
```

or remain:

```
Workboard → RO → Work
```

…using **real shop behavior** as the deciding signal.

---

## Sprint Order (Post-Checkpoint)

1. **Home / Attention identity** — declare the front door (this experiment)
2. **RO workspace craft** — review rail collapse, financial disclosure, chrome flattening
3. **Encounter + Hub continuity** — fold admin surfaces into the flow
4. **Settings cleanup** — advisors don't live here

---

## Outcome (2026-06-09)

All four sprints shipped. `/app` is Attention; workboard is lane drill-down; RO review rail and financial closeout use progressive disclosure; encounter and customer hub surfaces collapsed; settings and profile match ops density.

**Post-checkpoint follow-ups** (shipped 2026-06-09): mobile review line scan, settings routes in `routes/operations/settings.php`, front-door landing telemetry, Shop Behavior Pulse on operational report.

---

## Why This Checkpoint Exists

Six months from now, the team should be able to answer:

> Was this the sprint where we **intentionally** made Attention the front door, or did it just gradually happen?

That distinction becomes surprisingly important later.

---

## Directive for Implementation

**Stop building communications. Start building Home.**

The comms stack has done its job — it revealed what the primary operating surface should be. Sprint 1 makes the software admit it.
