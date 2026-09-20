# ARK Staff — Shop Posture Audit v3

**Status:** **Frozen** — philosophy complete. Implement against [`ark-staff-product-constitution-v1.md`](ark-staff-product-constitution-v1.md).  
**Rule:** doctrine `ark-staff-product-constitution.mdc`

**Standing review criterion (every ARK Staff UI change):**

> If Edward opened this screen in the middle of a busy Tuesday, would he immediately understand the **current state of the operation** and **what the operation needs from him**?

If no — the screen is not finished, regardless of how much functionality it contains.

---

## The product (read this first)

> **The operator should never have to inspect the operation to understand its state.**

That's it. That's the product.

Today, every shop management system makes you **inspect**: lists, queues, badges, tabs, counts.

ARK should simply tell you:

- *You're caught up.*
- or *Customers are waiting.*

Then **every screen inherits that context** — open, mid-workflow, inside a repair order. The operation does not disappear because you're looking at an RO.

**The interface should mirror the emotional posture of the operation.**

reference CRM is emotionally flat — same visual intensity everywhere. ARK should not be. Not because of decorative color — because the **state of the business** is calm, waiting, deciding, or interrupted.

---

## Secondary framing

> **generic CRM mobile removes decisions per task. ARK still asks Edward to learn its navigation.**

We are **designing ARK**, not reacting to reference CRM.

**North star:** Walking into Demo Auto Repair at 8:00 AM — bays, waiting, rhythm — not CRM, not records.

**Primary metric:** **Decision budget** — count stops where Edward asks *where / which tab / which customer / which RO*, not tap count.

**Vocabulary (banned):** **module**, **screen** as a design unit, **Moments** as a platform layer (see below).

---

## Do not build screens. Build states of the operation.

| Concept | Meaning |
|---------|---------|
| **Screen** | Wrong design unit |
| **State** | What the operator walks into (*slammed* · *caught up* · *everyone waiting on me*) |
| **Observation** | Interpretive truth — what happened and why it matters (`ark-observations.mdc`) |
| **Posture** | How that reads to a human — at shop, station, or workspace scope |

**Do not add "Moments" as a layer.**  
*"Customer replied"* is an **observation**. It changes shop posture from 🟢 FLOWING to 🟡 WAITING. The workspace opens **already oriented**.

If we add Moments between posture and workspace, we will **duplicate Operational Observations under another name**. Resist.

Operator language may still say *"a moment"* on the floor — product stack does not.

---

## Product stack (build this — and only this)

```
Authority
    ↓
Observation
    ↓
Shop Posture          ← how the business feels (always present)
    ↓
Workspace             ← RO · customer · vehicle · intake · station
    ↓
Finish Work          ← design intent (Reply · Review · Check in · …)
```

**Not in the stack:** Moments · Screens · Modules · Tabs as truth.

**Finish Work** (not "Next Actions" as design intent): the **minimum** operational close that returns posture toward FLOWING — **one thing**, not a task bucket. UI may label differently. Full rules: [`ark-staff-product-constitution-v1.md`](ark-staff-product-constitution-v1.md) § Finish Work.

Observations **drive** posture changes. Finish Work lives **in** workspaces. Shop posture **persists** across every workspace.

---

## Three postures — same vocabulary, different scope

One grammar. Three scopes. Already rhymes with RO posture and station posture on desktop / VVX.

### 1. Shop Posture — how the business feels

Always visible. **Everywhere** — not only on open.

| | |
|---|---|
| 🟢 **FLOWING** | Caught up · production moving |
| 🟡 **WAITING** | Customers / comms waiting on the shop |
| 🟠 **DECISIONS** | Money · approvals · customer decisions pending |
| 🔴 **INTERRUPTED** | Right now · ringing · multiple urgent turns |

Example shell:

```
🔴 INTERRUPTED
Sarah is waiting.
Landon finished an inspection.
```

### 2. Station Posture — what this place is doing

| Station | Posture |
|---------|---------|
| Front Counter | WAITING |
| Bay 2 | PRODUCING |
| Parts | WAITING |

Mobile: operator's bound station + shop floor context when relevant.

### 3. Workspace Posture — what this object needs

| Object | Posture |
|--------|---------|
| Repair Order | Waiting Approval |
| Vehicle | Ready For Pickup |
| Customer | Waiting Reply |

Workspace posture **inherits shop posture** — never contradicts it.

Example: Edward opens RO #1599. Shop is still 🔴 INTERRUPTED because Sarah is waiting elsewhere. He must **still know** — the operation did not vanish.

```
🔴 INTERRUPTED · Sarah waiting          ← shop posture (persistent band)
──────────────────────────────────────
RO #1599 · Waiting Approval             ← workspace posture
2016 Ram · Skylar Hathorn

Finish Work: Contact Jason about estimate.          ← one thing
```

---

## How observations change posture (not a separate layer)

| Observation (existing vocabulary) | Posture shift | Workspace opens as |
|-----------------------------------|---------------|-------------------|
| CustomerWaitingResponse | 🟢 → 🟡 WAITING | Customer · Waiting Reply |
| IncomingCall / active call | → 🔴 INTERRUPTED | Call overlay + thread |
| InspectionReadyForReview | → 🟠 DECISIONS | RO · advisor review needed |
| CustomerDecisionNeeded | → 🟠 DECISIONS | RO · Waiting Approval |
| PartsArrived (when projected) | 🟢 → production pressure | RO · PRODUCING |
| No open turns on Edward | → 🟢 FLOWING | Calm copy · explicit negative |

Each observation has an **operational close** (Finish Work completes the loop):

| Observation pressure | Closes when |
|----------------------|-------------|
| Waiting reply | Reply sent |
| Arrival | Checked in |
| Inspection ready | Advisor reviewed |
| Estimate viewed | Customer contacted |
| Vehicle ready | Customer picked up |

---

## Day arc (states of the operation — not tabs)

Morning → Waiting → Decision → Production → Pickup → Closing.

Same shop. Shop posture shifts through the day. Workspaces inherit.

---

## Finish Work

Inside the workspace. Driven by **workspace posture + observations**.

> Finish Work is not a task list. It is the minimum action required to move the operation toward FLOWING.

**One thing** — not Call · Send · Review · Open. When it completes, posture improves; ARK surfaces what matters next.

Examples: Reply · Review · Check in · Call Jason (with observation context) · Pay link · Close RO.

**Not Finish Work:** unread · notifications · reminders · generic tasks.

**Operational close** — authority advances; observation resolves; item disappears without a checkbox.

Full loop + AI + litmus Q4: [`ark-staff-product-constitution-v1.md`](ark-staff-product-constitution-v1.md).

---

## Confidence (from reference CRM — not UI)

| Permission (today) | Confident (target) |
|--------------------|--------------------|
| Search… | Who are you looking for? |
| Empty list | No one waiting on you. |
| Tab labels | Inherit shop posture — not separate product centers |

---

## Emotional audit

Two-second glance **on any screen**:

| | Target | Today |
|---|--------|-------|
| Shop state without inspecting lists | **Shop posture band** | Fail — tabs and counts |
| Emotional mirror of operation | Posture-driven UI intensity | Flat like reference CRM |
| Operation persists in RO | 🔴 band still visible | Fail — RO is a silo |
| Confident copy | Shop sentences | Permission-seeking |

---

## Brutal question (per workspace)

> **Why would Edward open this instead of walking to the counter?**

| Workspace | Must inherit |
|-----------|--------------|
| Shell | Shop posture + top observations (not a list to inspect) |
| RO | Shop posture band + workspace posture + Finish Work |
| Customer / vehicle | Same |
| Intake | Waiting / morning shop posture |
| Comms capability | Waiting — not a separate emotional center |
| Apps Soon grid | Remove |

---

## What we stop doing

| Stop | Start |
|------|-------|
| Moments as a platform layer | Observations change posture |
| Unlock-only posture | Posture **everywhere** |
| ShopHeartbeat | Shop Posture (interpretation) |
| Building screens | States + three-scoped posture |
| Inspecting lists to know state | **Telling** state |
| Duplicating observations as "moments" | Reuse observation vocabulary |
| Modules | Capabilities · workspaces |

---

## Implementation order

| P | Ship | Decisions removed |
|---|------|-------------------|
| **P0** | **`ShopPostureProjection`** — 🟢🟡🟠🔴 + sentence; composes observations + Attention — **no new authority** | Inspect lists/tabs to know state? |
| **P0** | **Persistent posture band** on all workspaces (RO, customer, intake) | Did the operation disappear? |
| **P0** | **WorkspacePosture** on RO / customer / vehicle payloads | What does this object need? |
| **P0** | Finish Work tied to observation close | What closes the loop? |
| **P0** | Confident shop copy | Permission tone |
| **P1** | Station posture when operator bound to workstation | Where am I on the floor? |
| **P2** | WorkspaceShell (SYS-1/2) | Which back? |

**Backend:** Extend mobile shell / workspace projections — same pattern as `StationPostureProjection` (VVX). Observations feed posture; do not create `Moment` tables or APIs.

**Do not ship:** Moment layer · new tabs · Soon tiles · AI summaries.

---

## Screenshot board

Rows: Shop posture (🟢🟡🟠🔴) × context (**shell · inside RO · inside customer**)  
Columns: Before read · After Finish Work · Observation closed  

Prove posture **persists inside RO**.

---

## Success metric

Edward is anywhere in the app on a busy Tuesday.

Without inspecting lists:

- He knows **shop posture**.
- He knows **what this workspace needs**.
- He knows **what action finishes it**.

**The operator never inspects the operation to understand its state.**

The interface mirrors the emotional posture of the operation.

The app learns Edward. Edward doesn't learn ARK.

That is designing ARK.
