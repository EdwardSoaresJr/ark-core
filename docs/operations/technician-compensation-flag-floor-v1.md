# Technician compensation — Flag + floor

**Status:** Phase 0 — **CLOSED** · Phase 1A recognition — **CLOSED** · Phase 1B Production Assist v1 — **CLOSED / OBSERVATION** (2026-07-26 · `631b5a5d` · `phase-1b-flag-floor-production-assist`)  
**Audience:** Engineering + shop owners  
**Not legal advice.**  
**Posture:** Run cars. Let shop friction choose the next feature. No Phase 2 from speculation.

## Phase 0 closed

Compensation agreement (flag + floor) is separate from estimated labor cost (`labor_cost_cents`). Utilization affects projection only. Floor suggestion is dated/configured guidance — never silently rewrites stored agreements. Do not reopen Phase 0 for settlement/WIP/OT — that is Phase 1B.

## Freeze — three layers

| Layer | Owns | Persisted? |
| --- | --- | --- |
| **Compensation agreement** | Pay type, flag rate, hourly floor | History: `technician_compensation_agreements` · Current projection: `users.*` |
| **Production assumption** | Assumed billable utilization | Calculator default **85%** — not a wage |
| **Loaded labor cost** | Estimated cost per billed hour for margin | `labor_cost_cents` — never payroll truth |

```text
effective wage cost / billed hr = max(flag_rate, floor_rate ÷ utilization)
loaded = effective × (1 + burden%) + overhead
→ labor_cost_cents
```

## Floor suggestion

Config: [`config/technician_compensation.php`](../../config/technician_compensation.php)  
Helper: `TechnicianFloorWageSuggestion`

- Seeds **new** Flag technicians when floor is omitted.
- Editable per technician.
- Changing the suggestion **does not** rewrite stored `floor_rate_cents`.

## Phase 1A — Recognition authority — **CLOSED**

Immutable flag recognition + compensation agreement history. No settlement UI, daily time, WIP UI, or floor assist.

| Authority | What it owns |
| --- | --- |
| `technician_flag_recognitions` + `_lines` | These specific flag hours became recognized for this technician at this moment |
| `technician_compensation_agreements` | Effective-dated flag/floor agreement history |
| `users.flag_rate_cents` / `floor_rate_cents` | Current-state projection only |

**Recognition boundary (shop compensation policy, not Colorado law):** concern production → `Completed`. Actor who recorded the transition is separate from attributed technician. Attribution = RO `assigned_technician_id` at recognition (`repair_order_assigned_technician`). Missing assignee → `flag_production_recognition_deferred`, no silent tech.

**Flag hours:** tech-sheet authority — `quantity` on **Labor** lines only (`countsTowardFlagHours`). Sublets are vendor service — never flag hours. Header + immutable line snapshots.

**Idempotency:** unique on `repair_order_line_id`. Completed → reopen → Completed cannot duplicate; newly added labor can recognize on a later Complete.

**Floor observation (do not retune immediately):** If techs/advisors delay `Completed` or batch-complete end-of-day, notebook it. Authority stays; a more explicit technician Complete Work action is earned only from repeated floor evidence.

**Pre-build audit (resolved by 1A):** mutable `production_status` alone was not enough; live lines/tech assignment were not historical truth; Staff rates needed history. RO close/payment/Ready Pickup remain forbidden substitutes.

## Phase 1B — Flag + Floor Production Assist v1 — **CLOSED / OBSERVATION**

**North star:** Why is this technician's recognized flag low — pending still sitting, or not much production? Floor exposure is the financial consequence, not the headline.

**Live vocabulary:** Unknown (no authority yet) · Pending (exists, not recognized) · Recognized (immutable labor) · Unassigned (exists, no honest owner) · Floor exposure (financial consequence of recognized vs clock).

**Surface:** `/app/owner/technician-production` (owner workspace) · Reports catalog card under Shop operations.

| Layer | Role |
| --- | --- |
| `technician_compensable_time_entries` | Daily compensable hours Phase 1B reads. Source: `punch_derived` or `manual_override` (locked). Period totals derive from daily rows. |
| Period range | Projection boundary only (selected week/range). Not payroll-period truth. |
| Recognized flag | Immutable Phase 1A lines whose `recognized_at` falls in the period. |
| Pending flag | Projection: approved **labor** attributable to RO assignee, anti-joined against recognition lines. Sublets excluded. No WIP ledger. Unassigned approved labor is listed separately — never silently attributed. |
| Agreements | Flag earnings use agreement at each recognition timestamp; floor uses agreement on each compensable date. Mid-period rate changes compose correctly. |
| Base compensation assist | `recognized flag earnings + max(0, floor requirement − recognized earnings)`. Pending never reduces floor exposure. |
| OT | Warning only when weekly hours > 40 or a day ≥ daily threshold. No OT dollar calculation. |
| History unknown | Periods entirely before `recognition_authority_starts_at` show **Production history unavailable** — not 0.0 recognized. Zero ≠ unknown. |

**Labels:** Pending flag (primary) · Base compensation assist (not Gross Pay / Paycheck) · Recognized / clock · Recognized + pending vs clock (not “earned efficiency”).

**Attribution limitation (documented on Show me):** Pending uses the same RO `assigned_technician_id` model as Phase 1A. Ambiguous/unassigned production stays visible and uncounted.

**Superseding target (doctrine frozen 2026-08-03 — not yet implemented):** [`repair-action-assignment-and-labor-recognition-v1.md`](repair-action-assignment-and-labor-recognition-v1.md) — assignment on Repair Action (`RepairOrderWorkGroup`); recognition attribution from Repair Action assignee; RO assignee = Primary Technician visibility only. Line uniqueness stays.

### Explicit Phase 1B non-goals (do not start)

OT compensation dollars · payroll export · automatic Poor/Good judgments · Phase 2 settlement

## Technician Time Clock v1 — **CLOSED / OBSERVATION**

**Earned by:** manual clock-hour entry friction on Phase 1B.

| Layer | Role |
| --- | --- |
| `technician_time_sessions` | Punch authority (clock in/out). Surface-independent. |
| `technician_time_session_corrections` | Append-only audit of manager corrections and deletes (original + new). |
| Daily hours | Recomputed into `technician_compensable_time_entries` — Phase 1B contract unchanged. |
| Manual week entry | Override + lock; never summed with punch hours. |

**Doorway:** `/app/time-clock` (first UI only). Companion / kiosk later call the same actions.

**Delete:** owner voids a punch with required reason (`status=deleted`). Row stays for audit; deleted punches never feed compensable hours and do not block clock-in.

**Proxy punch:** admins and advisors may Clock In / Clock Out anyone who `canBeClocked` (technician, advisor, or admin) on their behalf (`clocked_in_by_user_id` / `clocked_out_by_user_id`). Correct and delete remain admin-only.

**Overnight:** open past shop midnight → `needs_resolution`; prior-day totals not invented until clock-out/correction (then split by shop calendar date).

**Self-punch scope:** `canSelfPunch` / `canBeClocked` covers technician, advisor, or admin — not technician-only. Any active staff member in those three roles can punch their own time.

### Lunch + Auto Day (2026-07-28)

**Lunch is a real punch, not a deduction guess.** *Out for Lunch* closes the open session with `close_reason=lunch`; *Back from Lunch* opens a new session. The gap between the two sessions is simply never punched — unpaid by construction, no separate lunch ledger.

**Auto day** lets an admin assign Business Hours coverage to any staff member (`users.auto_clock_enabled` + `auto_lunch_minutes`) instead of requiring a punch:

| Rule | Behavior |
| --- | --- |
| Materialize | `EnsureAutoClockSessionsAction` opens a session at Business Hours **open**, backdated to the open instant regardless of when the sync actually runs (`origin=auto`) |
| Close | Closes at Business Hours **close**, backdated to the close instant (`close_reason=end_of_day`, `origin=auto`) |
| Auto lunch | If the auto-clock day has **no** real lunch punch (`close_reason=lunch`), `RecomputeTechnicianCompensableDayAction` deducts `auto_lunch_minutes` once from the punch-derived total |
| Explicit punches win | A real Out for Lunch / Back from Lunch during an auto day is honored as-is — the auto-lunch deduction is skipped so the real gap is never double-counted |
| Awaiting lunch return | If the most recent punch is a closed `lunch` session, sync never auto-reopens or auto-closes — it waits for the explicit Back from Lunch punch |
| Already ended | Once a shop day has an `end_of_day` close for that user, sync does nothing further that day |

Scheduled via `time-clock:sync-auto` (`MarkOvernightOpenSessionsAction` + `EnsureAutoClockSessionsAction`, every 5 minutes) and re-run inline on `/app/time-clock` index and staff show for immediate feedback.

Admin assigns auto day to **any** eligible staff member (`canBeClocked`), not self-only — `UpdateTechnicianAutoClockPolicyAction`.

### Explicit Time Clock v1 non-goals

Breaks (beyond lunch) · PTO · attendance scoring · geofencing · biometrics · payroll export · OT dollars · full-shop HR

## Explicit non-goals

Payroll provider · taxes · pay stubs · OT dollar calc · Phase 0 loaded-cost changes · customer payment / RO close as recognition
