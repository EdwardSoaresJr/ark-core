# Repair Order Discovery Contract

**Status:** Draft — doctrinal milestone; **not a build mandate** until observation closes open questions  
**Version:** 0.1 — 2026-06-15  
**Sequence:** Doctrine → **Contract** → Observation → Authority → Routes/UI

**Predecessors:**

- [Technician Scope Doctrine v1](technician-scope-doctrine-v1.md)
- [Technician Discovery Audit](technician-discovery-audit.md)

**Peers (same pattern):**

- [Identity Authority Contract](../identity/identity-authority-contract.md)
- [Inspection Authority 1.0 Contract](../inspection/inspection-authority-1.0-contract.md)

---

## 1. What this is

Phase “technician permissions” split **`production.access`** from **`operations.access`**. That fixed **advisor inheritance** — Work, Communications, Customers, Vehicles.

It did **not** fix **repair order discovery**.

Today:

```
repair_orders.view  =  shop-wide discovery + access
```

Doctrine requires:

```
Technicians cannot discover work that is not theirs.
```

This contract defines **how a repair order may be discovered** — separately from **what a technician may do on an RO they legitimately hold**.

**Rule:** Do not change routes, nav labels, or menu items until this contract is **accepted** and observation questions are **answered**.

---

## 2. Core distinction

| Concern | Question |
|---------|----------|
| **Discovery** | How did the technician **find** this RO? |
| **Access** | What may they **do** on an RO they legitimately hold? |

Technicians **must** open assigned ROs. They **must not** browse the repair order system as a shop-wide information source.

| Allowed discovery pattern | Not allowed |
|----------------------------|-------------|
| My Work → assigned RO → open | Repair Orders index → browse 200 ROs → pick one |
| Assigned list → open | Global search across shop |
| Direct URL → **assigned** RO (TBD) | Direct URL → arbitrary RO id |

---

## 3. Guiding sentence

> **The burden of proof is on any surface seeking technician visibility.**

Default answer:

> **No — unless it directly supports diagnose, perform, or document assigned work.**

---

## 4. Open questions (observation required)

These must be answered on the floor **before** capability or route changes. Hypotheses are not contract text.

### 4.1 Unassigned work pool

**Question:** May a technician **discover and claim** unassigned work, or **only** work already assigned to them?

| Model | Shop behavior |
|-------|---------------|
| **Claim pool** | Technician sees unassigned ready work and self-assigns |
| **Assigned only** | Advisor/owner assigns; technician sees only `assigned_technician_id = me` |

**Demo Auto Repair hypothesis:** Ben/Edward assign → Landon performs → **assigned only**.

**Validate with Landon before hard-coding.**

**If assigned only:** Workboard unassigned lanes and global index are doctrinal violations for technicians.

**If claim pool:** Unassigned discovery must be **explicit, bounded, and justified** — not an accidental side effect of `repair_orders.view`.

---

### 4.2 Vehicle history on assigned RO

**Question:** When working assigned RO #1457, may the technician discover **other ROs on the same vehicle** (prior visits, deferred recommendations)?

**Diagnostic value (often legitimate):**

- Prior diagnosis
- Prior repair
- Prior recommendation
- Prior measurements

**Discovery risk:**

- Links to completed ROs technician was never assigned to
- Dollar totals on deferred work
- “Other open work for customer” on different vehicles

**Classification:** **Requires observation** — not an immediate violation. Do not over-correct without floor evidence.

**Contract direction (hypothesis):** Same-vehicle history **on an assigned RO** may remain a **read-only projection** scoped to vehicle context — not a shop-wide index substitute.

---

### 4.3 Historical repair orders

**Question:** May technicians discover **closed/completed** ROs unrelated to current assignment?

**Default under doctrine:** **No** — unless vehicle-history observation (§4.2) proves otherwise.

Global index explicitly advertises “active and historical ROs” — shop-wide discovery, not production role.

---

### 4.4 Other technicians' work

**Question:** May technicians discover ROs assigned to another technician?

**Default under doctrine:** **No.**

Current violations: global index, Waiting Parts / QC workboard lanes, unscoped direct URL.

---

### 4.5 Direct URL to assigned RO

**Question:** Must deep links keep working (advisor message, bookmark, printed sheet)?

**Hypothesis:** **Yes** — for ROs **assigned to the technician**, even if not discovered via My Work.

**Contract implication:** Access gate ≠ discovery gate. Direct URL may be **access without catalog browse** — still requires assignment validation.

---

## 5. Discovery mechanisms inventory (current violations)

From [Technician Discovery Audit](technician-discovery-audit.md). Implementation waits on §4 answers.

| Mechanism | Class today |
|-----------|-------------|
| Global RO index (`/repair-orders`) | Shop-wide discovery — **violation** |
| Global RO search (customer, phone, VIN) | Shop-wide discovery — **violation** |
| Repair Orders nav + topbar search | Entry to above — **violation** |
| Caller lookup | Shop-wide discovery — **violation** |
| Direct URL to arbitrary RO id | Shop-wide access — **violation** |
| Workboard — unassigned ready pool | **Requires observation** (§4.1) |
| Workboard — other tech Waiting Parts / QC | Shop-wide discovery — **violation** |
| RO History rail — prior visits / deferred ROs | **Requires observation** (§4.2) |
| RO History rail — other customer open ROs | Likely **violation**; confirm |
| Broadcast `operations.repair-orders.{id}` | Shop-wide subscription — **violation** |

---

## 6. Capability direction (contract intent — not implemented)

**Problem capability:**

```
repair_orders.view  →  implies shop-wide see + discover
```

**Contract intent (names TBD at implementation):**

| Capability | Meaning | Technician | Advisor |
|------------|---------|------------|---------|
| Shop-wide RO discovery | Index, search, caller lookup, browse | **No** | Yes |
| Assigned RO access | Open/work assigned ROs | Yes | Yes |
| Assigned RO lifecycle | Lifecycle/inspection on assigned ROs | Yes | Yes |

Exact capability split is **implementation** — this contract fixes **semantics** first.

**Anti-pattern:** Hide Repair Orders nav while leaving `repair_orders.view` shop-wide on `/repair-orders` and direct URLs.

---

## 7. Target technician discovery origins (hypothesis)

After contract acceptance + observation, navigation should **trend toward**:

```
My Work
Assigned Repair Orders   (optional if My Work suffices)
Inspection               (via assigned RO; maybe top-level later)
ARKademy
```

**Not:**

```
Work · Communications · Repair Orders (global) · Customers · Vehicles
```

**Operations (workboard)** may become **My Work** or remain advisor/owner-oriented shop coordination — **observe first**.

---

## 8. Success criteria (contract acceptance tests)

When implemented, the system must satisfy:

1. A technician can **diagnose, perform, and document** work on ROs **assigned to them**.
2. A technician **cannot browse** the repair order system as a shop-wide catalog.
3. Every technician discovery path passes: *Does this directly support assigned work?*
4. Vehicle history (if allowed) is **bounded projection** from an assigned RO — not a second global index.
5. `repair_orders.view` (or its successor) **does not imply** shop-wide discovery for production-role users.

---

## 9. Forbidden until contract + observation

- Renaming nav without scoping discovery authority
- Read-only global index “for technicians”
- Granting `operations.access` for convenience
- Expanding technician visibility without assigned-work justification
- Implementing capability split before §4 questions are answered

---

## 10. ARK sequence

```
Technician Scope Doctrine v1
        ↓
Technician Discovery Audit (accepted)
        ↓
Repair Order Discovery Contract (this document — accept after observation)
        ↓
Observation (Landon: index? pool? history?)
        ↓
Capability / policy authority
        ↓
Routes · nav · My Work
```

Same shape as:

```
Inspection Workflow Principle → Inspection Authority → UI
Identity Contract → OIDC Design → Issuer
```

---

## 11. Observation notebook (fill on floor)

| Question | Landon’s answer | Contract decision |
|----------|-----------------|-------------------|
| Where do you go first when you clock in? | | |
| Do you use Repair Orders search? | | |
| Do you need unassigned ready work visible? | | |
| Do you use vehicle history on the RO rail? | | |
| Do you need completed ROs from prior visits? | | |
| Do advisors send you direct RO links? | | |

**Do not implement until this table has evidence.**

---

## 12. Related documents

| Document | Role |
|----------|------|
| [technician-scope-doctrine-v1.md](technician-scope-doctrine-v1.md) | Production role north star |
| [technician-surface-principle.md](technician-surface-principle.md) | Surface vs capability (shipped split) |
| [technician-discovery-audit.md](technician-discovery-audit.md) | Evidence for this contract |
