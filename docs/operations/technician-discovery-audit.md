# Technician Discovery Audit

**Status:** Accepted — findings frozen 2026-06-15; observation before implementation  
**Companion:** [Technician Scope Doctrine v1](technician-scope-doctrine-v1.md) · [Repair Order Discovery Contract](repair-order-discovery-contract.md) (next milestone)

---

## Reframe

This audit is **not** about whether technicians can **open** repair orders.

Technicians **must** open assigned ROs to diagnose, perform, and document work.

The question is:

> **How may a technician discover a repair order?**

| Pattern | Doctrine |
|---------|----------|
| **Allowed** | My Work → Assigned RO #1457 → Open RO |
| **Potentially allowed** | Direct URL → Assigned RO #1457 → Open RO (deep link, bookmark, shared link) |
| **Not allowed** | Repair Orders Index → Browse 200 ROs → Pick one |

**Scope Doctrine v1:**

> Technicians cannot **discover work that is not theirs.**

Not: “Technicians cannot view repair orders.”

**Default answer for any new discovery surface:**

> No, unless it directly supports assigned work.

---

## Executive summary

The **`production.access` permission split was successful.** Work, Communications, Customers, and Vehicles are no longer advisor inheritance on technicians.

**The discovery model was not fixed.**

Root cause: **`repair_orders.view` acts as a shop-wide discovery capability.**

```
repair_orders.view (today)     ≈  See repair orders (shop-wide)
repair_orders.view (doctrine)  ≈  Discover assigned repair orders
```

That is a **capability design** problem — not a menu label, route name, or UI issue.

There is **no `RepairOrderPolicy`**, **no assignment gate on GET routes**, and **no split** between shop-wide discovery vs assigned-work access.

The system enforces **Technician ≠ Advisor** but still assumes **Technician = shop-wide RO participant**.

Those are separate problems.

---

## A. Can a technician discover…?

### Unassigned repair orders — **YES (violation)**

| Discovery path | Mechanism |
|----------------|-----------|
| Global RO index | Unfiltered paginated query — all ROs including unassigned |
| Global RO search | Query by RO #, customer, phone, VIN, plate — returns unassigned matches |
| Operations workboard | `WorkboardLens` shows Approved / Ready for Work when `assigned_technician_id` is null or assigned to current user |
| Topbar search | Links to global index (`operations.repair-orders.index`) |

Workboard intentionally exposes an **unassigned work pool** — shop coordination, not assigned-work discovery. **Requires observation** before allow/deny (see Discovery Contract).

---

### Other technicians' repair orders — **YES (violation)**

| Discovery path | Mechanism |
|----------------|-----------|
| Global RO index | All ROs regardless of `assigned_technician_id` |
| Workboard — Waiting Parts | `filterRepairOrders()` — all ROs in lane |
| Workboard — Quality Check | Same — all ROs in lane |
| Direct URL | `/app/repair-orders/{id}` — no assignment check |
| RO History rail | “Other open work for customer” on different vehicles |

In Progress lane correctly filters to `assigned_technician_id === user.id`.

---

### Completed ROs unrelated to assigned work — **YES (violation)**

| Discovery path | Mechanism |
|----------------|-----------|
| Global RO index | Status + date filters; “active and historical ROs” |
| Global RO search | No assignment filter |
| RO History rail | “Prior visits on this vehicle” — all prior ROs including closed |

Vehicle history may support **diagnose** — classified **requires observation**, not immediate violation.

---

### Future scheduled work unrelated to assigned work — **YES (partial violation)**

| Discovery path | Mechanism |
|----------------|-----------|
| Global RO index | Draft/estimate-posture ROs shop-wide |
| RO History rail | Deferred work links to prior ROs |

Appointments surface is `operations.access` only — technicians blocked from `/app/appointments/*` today.

---

## B. Routes relying on `repair_orders.view` instead of assignment scope

Technicians hold `repair_orders.view`. Routes use that capability **without** checking `assigned_technician_id`.

### Shop-wide discovery (doctrinal violation)

| Route | Path | Behavior |
|-------|------|----------|
| `operations.repair-orders.index` | `GET /repair-orders` | Browse/search all ROs |
| Topbar global search | Nav | Lands on index |
| Left rail Repair Orders | Nav | Same index |

**Controller:** `RepairOrderIndexController` — no assignment filter.  
**UI copy:** “Search active and historical ROs without changing the live workboard queue.”

### Workboard (partial — requires observation)

| Route | Path | Behavior |
|-------|------|----------|
| `operations.workboard` | `GET /app/workboard` | Technician lens + partial `WorkboardLens::filterRepairOrders()` |

### Caller lookup (violation if reachable)

| Route | Path | Behavior |
|-------|------|----------|
| `operations.caller-lookup` | `GET /app/caller-lookup` | Customer + RO from phone; no assignment scope |

### Assigned-work workspaces (allowed if RO is assigned — currently unscoped)

Show, estimate-review, inspection, workspace tabs, estimate PDF, tech sheet, print routes — legitimate **once on the right RO**. Violation when reached via shop-wide discovery or direct URL to another tech’s RO.

### Mutations (unscoped)

`repair_orders.lifecycle` and inspection write routes accept any RO id the technician can open.

### Broadcast

`operations.repair-orders.{repairOrderId}` — `repair_orders.view` + exists; no assignment.

### Correctly blocked (contrast)

Work, Communications, Customers, Vehicles, Appointments, Intake, Reports — gated by capabilities technicians lack.

---

## C. Route classification

| Class | Meaning | Examples |
|-------|---------|----------|
| **Assigned-work access (allowed)** | Work on RO legitimately discovered | RO show, inspection on **assigned** RO; direct URL to **own** assignment |
| **Shop-wide discovery (violation)** | Find ROs without assignment relationship | Global index, search, caller lookup, unscoped URL, other techs’ workboard lanes |
| **Requires observation** | May support assigned work but enables cross-RO discovery | Unassigned pool; vehicle history rail; other customer open ROs; financial totals on cards |

### Secondary discovery from assigned RO (History rail)

Links to prior completed ROs, deferred work on prior ROs, other customer open ROs on different vehicles — **requires observation** (diagnostic value vs discovery violation).

---

## D. Repair Orders menu vs doctrine-aligned origins

**Repair Orders top-level nav:** entry to shop-wide discovery — **does not belong** in technician experience as implemented.

**Doctrine-aligned origins (hypothesis — observe first):**

| Origin | Role |
|--------|------|
| My Work | Assigned (+ maybe claimable) work only |
| Assigned Repair Orders | Explicit `assigned_technician_id = me` list |
| Current Assignment | RO in hand — not a catalog |
| Direct URL | Potentially allowed for assigned RO deep links |

Removing the menu without scoping `repair_orders.view` would be permission theater.

---

## Success criterion

| Requirement | Met today? |
|-------------|------------|
| Perform, inspect, diagnose, document **assigned** work | Partially — workspaces exist; discovery not scoped |
| Cannot browse RO system as shop-wide information | **No** |

**Passing state:** Discover ROs only through assigned-work paths. Opening an assigned RO remains fully supported.

---

## Observation questions (before implementation)

1. Does Landon use Repair Orders index, or only workboard → assigned card?
2. **Unassigned pool:** May technicians claim work, or only see assigned work? (Demo Auto Repair hypothesis: Ben/Edward assign → Landon performs.)
3. **Vehicle history:** Prior diagnosis/repair/measurements on same vehicle — required for diagnose, or over-exposure?
4. Completed historical ROs — ever needed, or noise?
5. Direct URL to assigned RO (advisor link, print sheet) — must keep working?

---

## Next milestone

Do **not** implement from this audit alone.

Author and freeze **[Repair Order Discovery Contract](repair-order-discovery-contract.md)** — explicit answers to how ROs are discovered, what assigned work means, and what technicians may see before touching routes or capabilities.

---

## ARK sequence

```
Doctrine → Audit → Contract → Observation → Authority → Routes/UI
```

Inspection and Identity follow the same pattern. Technician scope is at **Audit complete → Contract next**.
