# Scheduling Runtime Authority

**State:** Soft-capacity scheduling — **FROZEN** (2026-07-22) · DayLens projection contract — **FROZEN** (2026-07-22) · Floor Proof / Observation next  
**Board:** ARK Sellable Track — Scheduling Workspace  
**Reopen:** Verified defects or earned pressure (Dispatch / RO bay / assignment UX) only — not roadmap momentum. DayLens itself is a stable projection contract — see Day board + DayLens.

## Mental model (frozen)

```text
Appointment
 ↓
Consumes soft capacity

Optional assignment data (tech / bay columns)
 ↓
DayLens — filtered perspective on the same day
```

Not: Appointment → Bay as booking authority.  
Not: separate Agenda / Bays / Tech schedulers.

Floor language: *“Yeah, we’ve got room Thursday.”* Morning ops may place work later; lenses only change what you see on that day.

## Protected sentences

**Appointments reserve soft shop capacity.**

**Technician and workstation assignments are optional operational planning.**

**Appointments reserve capacity. Repair Orders consume work.**

**Scheduling is policy-driven** — same family as Pricing Policy, Labor Policy, Communication Policy. Behavior lives in Settings + `SchedulingCapacityCalculator`, not hard-coded in the guard.

| Authority | Answers |
|-----------|---------|
| **Appointment** | Can the shop fit this work into the day? (time + expected workload) |
| **Capacity snapshot** | How full is this open period vs base and target? |
| **Repair Order** | What work is being performed? (production) |

Day lenses are projections. Agenda must never hide a valid appointment; other lenses may reduce visibility to a subset — they must never reinterpret what an appointment means.

## Workload ownership (do not let these merge)

| Concept | Owner | Meaning |
|---------|-------|---------|
| Expected workload | Appointment (`estimated_labor_hours`) | What we planned to accept when booking |
| Proposed repair work | Estimate lines | What we offered the customer |
| Approved production work | Repair Order / approvals | What we are authorized to perform |

Different lifecycles. Do not treat them as interchangeable truths. Sync or projection between them only when pressure earns it — never silent overwrite.

## Capacity as authority (projections consume; they do not own)

`SchedulingCapacityCalculator` → `SchedulingCapacitySnapshot` is the single calculation truth.

Today: Schedule capacity rail + create/reschedule enforcement.  
Later projections may reuse the same snapshot (owner “tomorrow at 138%”, advisor availability, self-scheduling slots, staffing) **without changing authority**.

Controllers, views, and guards must not duplicate the math.

## Authority stores

| Store | Owns |
|-------|------|
| `Appointment` | Start/end, status, customer/vehicle, expected workload, optional technician, optional workstation |
| `Workstation` | Places; `accepts_scheduled_work` contributes to bay capacity / optional planning |
| `shop_settings.scheduling_hours` | Optional staff booking window overrides. **Null = inherit Business Hours** (`telephony_call_flow.weekly_hours`). Custom JSON only when the shop blacklists days or narrows hours for appointments. |
| `shop_settings.appointment_request_availability` | Weekly defaults + horizon + min notice for public `/book` requests (Lead intake — not Appointment Truth) |
| `appointment_request_exceptions` | Date-specific enable/disable overrides for public requests |
| `shop_settings.appointment_capacity_basis` | `technicians` · `bays` · `limiting_resource` |
| `shop_settings.appointment_scheduling_target_percent` | Target = base × percent / 100 (25–300, default 100) |
| `shop_settings.appointment_capacity_enforcement` | `warn` or `block` when beyond target |
| `users.scheduling_hours` | Optional technician windows (null inherits shop) |

**Business Hours (telephony) are the default scheduling windows.** Custom `scheduling_hours` are optional deviations (blacklist a day, narrower open/close). Request availability answers “when may customers request?” — not “when is the shop open?” Soft capacity is not consulted for `/book` day lists (v1).

## Soft capacity

```text
base_capacity     ← technicians hours · bay-hours · or min(both)
target_capacity   ← base × scheduling_target_percent / 100
scheduled_work    ← sum of active appointment workload on the day
```

Workload prefers `estimated_labor_hours`. Duration is a fallback only when labor hours are unset.

**Warn:** save allowed; clear warning.  
**Block:** reject create/reschedule when beyond target.  
Editing excludes the appointment’s current workload before recalculation. Canceled do not consume capacity.

Capacity unavailable → appointments may still schedule; rail shows unavailable — never a false 0% block of the shop.

Do **not** require bay or technician to create or update an appointment.

Resource-specific overlaps are planning warnings when an optional assignment exists — not save blockers while frozen.

## Soft-remove bays (frozen)

1. Clear `workstation_id` on non-canceled appointments → Unassigned  
2. Set `accepts_scheduled_work = false`  
3. Keep the row (phones, history)

Communications projections continue excluding schedule bays from “needs a phone.”

## Day board + DayLens (frozen projection contract)

**The schedule is one soft-capacity day board with reusable lenses.**

It answers: *Can we fit this work into today?* Lenses answer: *Show me today’s appointments from this perspective.*

```text
Authority owns truth.          → Appointment
Projection owns presentation.  → ScheduleDayProjection (chips + filtered cards)
Surfaces choose perspective.   → default DayLens only
```

### Stable contract

| Rule | Meaning |
|------|---------|
| One authority | `Appointment` — optional `technician_user_id` / `workstation_id` are data, not a second store |
| One day board | Same calendar component; no Agenda / Bays / Tech product pages |
| Reusable lenses | `DayLens::agenda()` · `unassigned()` · `technician($id)` · `workstation($id)` |
| Projection-owned chips | Projection returns `chips[]` + filtered cards; Blade does not count, sort, or invent chips |
| Surface-owned default | Advisor Schedule → `agenda`; Companion → `technician:{me}`; Bay / TV → `workstation:{id}` |
| No assignment UI bundled | DayLens does not ship Floor Planner or booking-time bay/tech required fields |
| No reinterpretation | **A lens may reduce visibility; it may never reinterpret operational truth.** |

### Chip grammar

| Chip | Always? | Filter |
|------|---------|--------|
| **Agenda** | Yes | All active appointments for the day |
| **Unassigned** | If count > 0 | No technician **and** no workstation |
| **{Tech name}** | If count > 0 | `technician_user_id` |
| **{Bay name}** | If count > 0 | `workstation_id` |

One strip. Mixed techs and bays. Empty lenses stay hidden. Agenda is never hidden.

Keys: `agenda` · `unassigned` · `technician:{id}` · `workstation:{id}` · Query: `?lens=…` (invalid/empty → Agenda).

Chip order: Agenda → Unassigned (if any) → technicians with count (stable name sort) → bays with count (natural bay sort).

Example projection payload (Blade is dumb):

```text
chips: [
  { key: "agenda", label: "Agenda", count: 14, selected: true },
  { key: "unassigned", label: "Unassigned", count: 3, selected: false },
  { key: "technician:5", label: "Edward", count: 5, selected: false },
  { key: "workstation:3", label: "Bay 3", count: 2, selected: false },
]
```

### Capacity rail

Soft capacity stays **shop-wide** (Agenda truth) regardless of selected lens. Capacity answers “can the shop fit this?”, not “is Edward full?”

### Assignment data vs assignment UI

Chips need assignment **data**, not a dedicated assignment product. Data may arrive via edit dialog, import, or a later Floor Planner. Restoring prominent bay/tech assignment UX is a **separate** reopen — not part of the DayLens contract.

Multi-lane bay/tech boards (`lanes=technician|workstation` as product modes) stay retired. A lens filters cards on the day board; it does not resurrect lane boards as the center of Schedule.

```text
Customer calls
 ↓
Schedule (Agenda lens) — can we fit them?
 ↓
Appointment created (time + expected workload)
 ↓
Optional assignment data later
 ↓
Same day board · DayLens chips when counts exist
```

### Consumers (same projection)

| Surface | Default lens |
|---------|----------------|
| Advisor Schedule | `agenda` |
| Companion / iPad | `technician:{me}` |
| Bay monitor / shop TV | `workstation:{id}` |

### DayLens implementation gate

**Ship DayLens UI when at least two operational surfaces benefit from the same Day Lens projection.**

The projection owns chip generation and filtering; surfaces choose only the default lens. Do not gate on “operators asked to assign on the floor.” Do not build a second chip/filter implementation per shell.

## Settings

**Settings → Appointments → Scheduling capacity** — basis, target %, warn/block.  
**Settings → Appointments → Bays** — operational locations / capacity inputs; not mandatory booking resources.

## Explicit non-goals (pressure-first)

Dedicated Bay table · **Dispatch** · **RO workstation assignment** · vehicle location · tech↔bay matrix · multi-tech · drag/drop · blocked-slot UI · month view · AI scheduling · per-day overpack % · customer self-scheduling until capacity authority is trusted on the floor · bundling assignment UX into DayLens · per-lens reinterpretation of card meaning · per-lens capacity math.

## Observation gate (soft capacity sellable)

Engineering frozen ≠ Sellable. Soft capacity is sellable when Floor Proof shows it replaces Google Calendar for another shop. Do not open Dispatch or RO bay until operators repeatedly ask for them. DayLens ships under its own implementation gate above — not this sellable gate.
