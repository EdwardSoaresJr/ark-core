# Milestone

**ARK Sellable Track — frozen board**

**Plan:** private sellable-track plan (FROZEN)

**Standing order:** Ship vertical slices. No more doctrine unless implementation exposes a contradiction.

---

## Next milestone — **ARK Desk** (2026-08-24)

**Active advisor product is ARK Desk** (`apps/ark_desk`) — personal Windows command center.

**Shop Glass is PARKED.** Keep `apps/advisor_station` and station APIs. Do not polish Glass. Do not delete it.

**Job:** Installable personal Desk for Molly and Edward: tray, Sanctum auth, my work, incoming/missed calls, customer/RO context, follow-up, `CallSession.worked_at` handled, Hosted Dragon + mic.

ARK Web remains the system of record. ARK Tech is unchanged. Hosted Dragon remains one Laravel employee.

---

## Previous milestone — **Shop Glass as shared advisor command center** (2026-08-23) — PARKED

Scheduling is **floor-certified**. Do not reopen it.

**Job:** Make Shop Glass indispensable between the two advisors. Hosted Dragon supplies intelligence. Glass is not a smaller ARK workboard.

At **1920×1080**, one glance must answer:

| Glance | Source |
| --- | --- |
| What needs attention | ARK station projection |
| What’s coming in | ARK `coming_in` (certified) |
| Approvals / $ waiting | ARK (no fake F1 P&L) |
| Calls / customer follow-up | ARK communications projection |
| Shop pressure | ARK board |
| Few things Dragon thinks deserve attention | Hosted Dragon **read-only** overlay — optional; glass works if Dragon is down |

**Tap an RO → inspect it inside Glass.** Glass is a shared application, not a browser launcher. Writes stay selective. Glass does not become a second estimate editor.

### Queued / started — ARK Tech v0.1 (not Glass)

Technician handheld is a **separate Flutter app** (`apps/ark_tech`) over `/api/tech`. Shop Glass stays 1920×1080. Do not merge products. Floor APK on issued tablets is the next physical step (`docs/tech/hardware-learning-log.md`). ESP hardware remains R&D only.

Direction: `docs/tech/ark-tech-direction.md` · realignment: `docs/tech/client-realignment-v1.md`.

### Dragon (same milestone, not a new router)

Hosted Dragon is already the production *direction*. Initiative rerun was strong. Attack remaining **WEAK** bake-off cases only — **no new router architecture**:

- Inspection discovery (`21_inspection` → `inspections.get`)
- Unassigned listing (`18_unassigned`)
- In-production listing (`19_in_production`)
- Live estimate rewrite ambiguity (`23_estimate_rewrite_live`)
- Stronger comparison / synthesis (`29_website_vs_board` and similar two-source prompts)

### Explicitly do **not** start

Reminders · appointment confirmations · calendar optimization · more scheduling UI · SMS follow-up from appointments · Dragon writes to the calendar.

### Backlog (tiny, not now)

**Arrival Posture vs canceled history:** After the last appointment is canceled and there is no current upcoming visit, do not prominently show **Canceled** as if it were current posture. Operational events keep the history. Current posture should go quiet / absent. Do not pull this while Glass/Dragon is the milestone.

---

## Platform posture — **OBSERVE THE FLOOR** (2026-08-03)

Architectural work that was blocking ARK is in place. **Do not start another foundational authority right now.**

Next improvements come from real shop usage — notebook “This is awkward” / “I expected this” — not more foundational design.

### Board

| Area | Status |
| --- | --- |
| **Inspection** | 🟢 Observe (authority · corner · section walk · arrival · evidence path) |
| **Maintenance Service** | 🟢 Observe (oil slice · immutable events · package · Auto Detect · sticker gate) — observe real oil changes before expanding |
| **Evidence** | 🟢 Observe (Shared/Internal/Pending · portal · primary · audit) |
| **Repair Portal** | 🟢 Good foundation (durable QR · shared evidence · hub) |
| **Arrival Posture** | 🟢 **Certified 2026-08-23** (appointment projection · RO return scheduling · no workflow pollution · Glass `coming_in` · Dragon `appointments.query` read-only). **Freeze.** Tiny canceled-posture cleanup is backlog only. |
| **Repair Action Ownership (R1)** | 🟢 Shipped |
| **Repair Action Communication (R1.1)** | 🟢 Shipped · observe |
| **Guest booking** | 🟢 Shipped · freeze |
| **Customer Recognition** | 🟡 Doctrine frozen · not started — likely next major **customer** capability after observation, not urgency |
| **Financial Authority** | 🔴 RED until F1 intentionally resumed |
| **Work Authorization** | 🟢 v1 Testing Package shipped · Observe (Authorize + Outcome only — no Levels/pricing yet) |
| **Dragon Assist Bridge** | 🔴 Removed. Remote node / arkai bridge is gone. |
| **ARK-hosted Dragon** | 🟢 Production Dragon. Chat, rewrite, review, and recall assist run in ARK via hosted OpenAI. No remote appliance. |
| **Shop Glass** | 🟡 `coming_in` + Attention certified. **Ask Dragon** on station token certified in tests (hosted chat, `conversation_id`, RO glance, Dragon-down still usable). Floor 1920×1080 still the physical cert. Not ARK Lite. |

### Intentionally do **not** start

Labor Recognition R3 · helper time · warranty/customer-pay split · vehicle specification DB · inventory · service catalog · Companion expansion · Financial Position (until Financial intentionally resumed) · **Work Authorization Levels / Pricing Policy / portal progress** · Level 1–3 diagnostic labor SKUs

### Future (not an authority — not now)

**Dispatch / workboard by Repair Action** — projection of ownership (Edward / Landon / Caleb package lists), not RO numbers. Earn after R1 observation. No doctrine file required yet.

### Checklist

✅ Financial frozen · ✅ Repair Action Ownership shipped · ✅ Evidence · ✅ Maintenance · ✅ Arrival · ✅ Portal · ✅ Guest booking · ✅ Customer Recognition frozen · ⏸️ **Observe the floor**

---

## Financial Authority v2 — **FROZEN · RED until F1** (2026-08-03)

**Doctrine:** [`docs/ARK-FINANCIAL-AUTHORITY-V2.md`](../ARK-FINANCIAL-AUTHORITY-V2.md)

Estimate = living contract · Ledger = money · Invoice = historical **Issue Final Invoice** (consequence of closeout). Never two living financial contracts. One question per financial surface.

| Milestone | Status |
| --- | --- |
| **F0** Financial Authority v2 | 🔒 Frozen ✅ |
| **F1** Financial Position (“Customer Owes Today”) | ⏸️ **Next — sole resume point** · Authority stays **RED** until complete |
| **F2** Financial UI: Estimate · Coverage · Deposits · Projected Balance · Issue Final Invoice | ⏸️ After F1 |
| **F3** Issue Final Invoice event | ⏸️ After F1–F2 |
| **F4** Migrate open early-invoice ROs | ⏸️ After F3 |
| **F5** Retire living-invoice compatibility | ⏸️ After F4 |
| **F6** Coverage / multi-payor authority | ⏸️ After F1 trusted on floor |

**Architectural stop:** No invoice / refresh / sync / early-invoice enhancements until F1. Living-invoice = transitional compatibility only.

**PR gate:** Estimates / invoices / payments / deposits / coverage / balances → *F1 or blocked?*

---

## Repair Action Assignment & Labor Recognition — **R1 SHIPPED · observe** (2026-08-03)

**Doctrine:** [`docs/operations/repair-action-assignment-and-labor-recognition-v1.md`](../operations/repair-action-assignment-and-labor-recognition-v1.md)

**Repair Orders organize customer work. Repair Actions organize technician work.**

| Milestone | Status |
| --- | --- |
| **R0** Doctrine freeze | 🔒 Done |
| **R1** `RepairActionOwner` + transfer history | ✅ Shipped |
| **R1.1** Status + Latest Update (operational communication) | ✅ Shipping — observe with R1 |
| **R2** Package-based tech sheets polish | ⏸️ After R1/R1.1 feels invisible |
| **R3** Recognition attribution from owner | ⏸️ After observation |
| **R4** Assists / shared work | ⏸️ After R3 |
| **R5** Observe Complete / package-completeness friction | ⏸️ Observation |

**R1 shipped:** Owner on Repair Action (Technician only) · transfers never copy · migrate from Primary Technician · advisor assign/transfer · tech sheets / My Work / landing project owned packages · RO assignee = Primary Technician (visibility).

**Not in R1:** Labor Recognition · payroll · assists · package completeness facets · Financial (still RED).

### R1 Observation Period — **LOCKED**

**Duration:** 2–4 weeks **or ~100 Repair Actions** (whichever comes first).

R3 is not blocked because it is hard — it is blocked because R1 changed the shop’s unit of work. Floor evidence before building on top.

**Questions to answer**

| Theme | Ask |
| --- | --- |
| Ownership natural? | Advisors assign at the right time? Transfers occasional (healthy) or constant (dispatch problem)? |
| Tech trusts packages? | Sheets have everything needed? Techs still opening the full RO? |
| My Work matches reality? | Queue = what they own? No-owner / wrong-owner actions? |
| Remaining friction? | Only then decide if R3 solves the remaining pain. |

**Metrics to watch**

Ownership transfers/day · avg Repair Actions/RO · multi-tech vs single-tech ROs · tech sheet reprints · wrong-owner corrections · “Where are my parts?” · “Why is this on my list?”

**Stop rules (during observation)**

| Forbidden | Allowed |
| --- | --- |
| Labor Recognition changes | Bugs |
| Payroll changes | Missing information that prevents normal use |
| Helper / assist implementation | Verified usability defects |
| Financial work (still RED) | |
| Workflow redesign unless verified defect | |

**Pattern (keep):** Freeze doctrine → thin vertical slice → floor → observe → earn next capability.

**Reopen R2/R3 only from:** observation clusters above — not roadmap hunger.

**Orthogonal to Financial RED.**

---

## Flag + Floor Compensation Inputs — **CLOSED**

**Closed:** 2026-07-26 · Phase 0 only

**What shipped:** Staff compensation agreement (`flag_rate_cents`, `floor_rate_cents`) separate from `labor_cost_cents` (Estimated labor cost for margin). Flag path: `max(flag, floor ÷ utilization)` using existing 85% utilization default. Dated Colorado statewide floor suggestion — seeds new Flag techs; never silently rewrites stored floors. Pay-basis toggle preserves flag/floor.

**Doctrine:** [`docs/operations/technician-compensation-flag-floor-v1.md`](../operations/technician-compensation-flag-floor-v1.md)

**Phase 1A (CLOSED):** Immutable flag recognition + compensation agreement history — see below.

**Phase 1B (CLOSED / OBSERVATION):** daily compensable time · recognized vs pending flag · floor exposure as Base compensation assist · OT warning · Show me · history unknown ≠ zero.

**Reopen Phase 0 only from:** verified bug in agreement storage, silent floor overwrite, or loaded-cost math defect.

---

## Flag Recognition Authority (Phase 1A) — **CLOSED**

**Closed:** 2026-07-26

**What shipped:** On concern production → Completed, immutable `technician_flag_recognitions` (+ line snapshots). Unique per labor line (reopen-safe; additional labor recognisable). Technician attribution snapshotted from RO assignee; deferred if missing. Effective-dated `technician_compensation_agreements`; Staff edits version history; `users.*` remain current projection. No dollars/top-up/settlement UI.

**Reopen only from:** incorrect recognition identity, silent double recognition, or agreement history rewrite defects.

---

## Flag + Floor Production Assist v1 (Phase 1B) — **CLOSED / OBSERVATION**

**Closed:** 2026-07-26 · Shipped `631b5a5d` · tag `phase-1b-flag-floor-production-assist`  
**Status:** Complete · Closed · **Observe** — shop friction chooses the next feature. Do not open Phase 2 from roadmap momentum.

**What shipped:** Owner surface `/app/owner/technician-production` — shop week table + technician drill-down. Daily `technician_compensable_time_entries` (manual week entry). Period is a projection range over dated time + 1A recognitions + effective-dated agreements. Pending flag = approved − recognized (anti-join by labor line); unassigned pending visible, not attributed. Production picture before dollars. Base compensation assist = recognized earnings + **floor exposure**. OT review warning only. Pre-adoption periods show history unavailable (unknown ≠ zero).

**Vocabulary (keep):** Unknown · Pending · Recognized · Unassigned · Floor exposure — not “top-up” as the primary label.

**Observation notebook (next couple weeks — do not build yet):**

| Friction | If it becomes the pain | Earns |
| --- | --- | --- |
| Manual clock hours forgotten | Time entry is the bottleneck | Time capture |
| Completed marked late / batched | Recognition timing drifts from floor truth | Explicit technician Complete Work |
| Pending on wrong tech | Wrong WIP story | Assignment discipline |
| Unassigned pending annoying | Attribution gap visible | Technician assignment tightening |
| Numbers right, payroll math tedious | Assist stops short of payroll workflow | Next compensation layer (counsel-gated) |

**Reopen only from:** incorrect period composition, silent pending attribution, zero-vs-unknown regression, or assist presented as payroll due.

**Do not start:** OT dollars · payroll export · automatic judgments · Phase 2 · recognition-policy retune.

---

## Technician Time Clock v1 — **CLOSED / OBSERVATION**

**Closed:** 2026-07-27 · Earned by manual clock-hour entry friction observed on Phase 1B.

**What shipped:** `technician_time_sessions` punch authority (clock in/out, `open` · `closed` · `needs_resolution`) + `technician_time_session_corrections` append-only audit. `RecomputeTechnicianCompensableDayAction` materializes punch-derived daily hours into the existing `technician_compensable_time_entries` contract — Phase 1B reads unchanged. Manual week entries set `source=manual_override` + `manual_locked=true` and are never overwritten by punch recompute. Overnight punches (open past shop midnight) flip to `needs_resolution`; the prior calendar day is never invented — only the current shop day accrues until the punch is closed or corrected. Doorway: `/app/time-clock` (self clock in/out for technicians, advisors, and admins via `canBeClocked`; owner staff view + correction form with required reason at `/app/time-clock/staff/{user}`).

**Lunch + Auto Day (2026-07-28):** `close_reason` (`lunch` · `end_of_day`) + `origin` (`manual` · `auto`) on `technician_time_sessions`; `auto_clock_enabled` + `auto_lunch_minutes` on `users`. Out for Lunch / Back from Lunch are real punches — the gap is simply unpunched. `EnsureAutoClockSessionsAction` materializes an auto-assigned day at Business Hours open and closes it at Business Hours close (backdated to the boundary instant, not sync time); `RecomputeTechnicianCompensableDayAction` deducts `auto_lunch_minutes` once only when no real lunch punch exists that day. Admin assigns auto day to any `canBeClocked` staff member via `UpdateTechnicianAutoClockPolicyAction` — not self-only. Scheduled via `time-clock:sync-auto` every 5 minutes.

**Doctrine:** [`docs/operations/technician-compensation-flag-floor-v1.md`](../operations/technician-compensation-flag-floor-v1.md#technician-time-clock-v1--closed--observation)

**Reopen only from:** incorrect punch-to-day attribution, silent overwrite of manual-locked entries, overnight split defect, or auto-day double-deduction/double-materialization.

**Do not start:** breaks beyond lunch · PTO · attendance scoring · geofencing · biometrics · payroll export · OT dollars · full-shop HR.

---
## Public Theme System v1 — **CLOSED** (Home Theme v1 closed 2026-07-26)

**Closed:** 2026-07-26 · Theme authority established. Do not reopen from aesthetic momentum.

**Home composition:** Accepted on production (~3154px → ~2365px at 1440). Frozen. Do not redesign Home.

**Photo assignment authority (2026-07-26):** Named composition roles (`hero` · `diagnostic_evidence` · `appointment_process`) select gallery indexes. Gallery remains reusable; roles own jobs. Legacy index fallback preserves production imagery until intentionally reassigned. Propagate Contact / CP only after this authority is signed off on production.

**Surfaces covered:**

| Kind | Surfaces |
| --- | --- |
| Marketing / identity | Home |
| Task / conversion | Book · Contact · Financing · Warranty · RepairPal |
| Authority / reference | Common Problems |

**Frozen principles:** Compact confidence · Typography before containers · Real shop photography as identity and evidence · Quiet factual proof instead of trust theater · Editorial discovery instead of chip walls · One dominant action without CTA echo · Different surfaces have different jobs · Useful information density over luxury whitespace · Answer-first reference pages · No decorative design merely to make a section feel special

**Common Problems hierarchy (binding):** Answer first → progressive depth → quiet proof → appropriate action.

**Not Theme v1 debt (leave alone):** zero related links on some CP pages · “Demo City” SEO-ish H1s · sparse older problem bodies — content/SEO/authority enrichment later when earned.

**Observation only:** Mobile header Book + Call/Book sticky sandwich — watch; do not redesign without usability pressure.

**Reopen Theme v1 only from:** demonstrated usability friction · conversion evidence · accessibility failure · responsive defect · a new public surface that needs an extension of the grammar.

Otherwise: shop it. Plain English / SEO copy remains Observing separately.

---

## ARK-WEB Plain English v1 — **CLOSED · Observing**

**Ship:** `94d3eef6` (2026-07-26) · `main` ≡ `production`  
**Status:** Complete · Closed · **Observe** — do not polish public/Portal copy further until Search Console, GBP, leads, and floor behavior earn the next change.

**What shipped:** Site-wide plain-English / anti-AI rewrite (public + Portal + Common Problems); SEO intent preserved; self-serving `AggregateRating` removed from business JSON-LD (visible Google rating chips kept); review-solicitation audit documented without redesign.

**Writing freeze:**

> Easy to read. Easy to understand. Easy to act on.  
> Make the words easier. Do not make the information smaller.  
> Technical authority does not come from sounding technical.

**Carry forward (not this capability):** Portal projection friction — [`docs/product/portal-projection-friction-v1.md`](../product/portal-projection-friction-v1.md)  
**Earned later (do not build until Reviews reopens as its own capability):** Eligible Review Request — [`docs/growth/review-schema-solicitation-compliance-v1.md`](../growth/review-schema-solicitation-compliance-v1.md)

**Reopen website copy only when:** verified defect, legal/policy correction, or observation cluster (traffic / leads / customer confusion) — not template polish.

---

## Sprint ladder (build order)

| Sprint | Focus | Status |
| --- | --- | --- |
| **1** | Communications / continuity workspace — replace, don’t debate | **Current** |
| **Shop Memory** | **v1 COMPLETE** · Labor + popup ON · other providers OFF · observe before enable | **Observing** |
| **Estimate Workspace** | **Approval Forecast** shipping · Friction Discovery notebook continues | **Building** |
| **2** | Mobile Staff — one product feel (nav, rows, command bar, identity strip) | Next |
| **3** | Estimate Editor polish — only after friction clusters earn it | Later |
| **4** | Operational Memory — earn from Edward/Molly handoff questions only | Later |

---

## Operation Authority — Phase 1 (v1 Finished)

**Status:** Complete · Closed · Foundational Authority.

**Mission:** Own Operation Class. Nothing else.

**Owns:** Operation · Operation Class.  
**Does not own:** Service definitions · standard labor · inspection · parts · advisor workflow · pricing · catalog UI · search.

```text
Estimate Line → Operation → owns Operation Class → Estimate Pricing Engine
```

**Invariants:** Operation owns class · Pricing never infers class · Pricing consumes class only · Operation never prices · Pricing never owns Operation metadata. One question: `$operation->operationClassKey()`.

**Reopen:** Verified defects only. Doctrine: doctrine `ark-operation-authority.mdc`

**Ship posture:** Ready when asked — do not deploy until explicitly requested. Stop feature work. Next capability starts from shop operational pressure.

**Heuristic:** doctrine `ark-authority-answers-directly.mdc`

**Approved migration follow-ups (cleanup only — not blockers):**

1. Eliminate `Operation::forLine(..., code)` once `operation_id` is universal.
2. Eliminate labor-category posture inference once Billing Posture is always explicit.

Neither reopens design. Do not expand into Service Catalog or pricing features from this list.

**Notebook close:** No unresolved architectural debt remains within the scope of these capabilities. Remaining TODOs remove transitional compatibility paths after operational adoption demonstrates they are unused.

**Bob v2 corrections (applied):** Soft discipline for program columns (no engine flat sync) · Labor Policies displays default category read-only · fail-loud when shop default Operation missing · non-reprice edits preserve `operation_id` with snapshot.

**Bob v3 disposition:** Approve to **commit**. Production ⏸ held until intentional migration window. Pre-production: count Billing Posture Resolution (`explicit` · `category_inference` · `default`) so inference deletion is evidence-based.

---

## Concern → Scope (earned next capability)

**Status:** Brief only · Not doctrine · Not closed · **Inactive until floor asks again.**  
**Pressure:** RO #1644 — one customer problem, two independent approvals (front/rear brakes); today’s `RepairOrderConcern` wears both hats.

**Brief:** [docs/operations/concern-scope-capability-brief-v1.md](../operations/concern-scope-capability-brief-v1.md)

---

## Visit Reason — Phase 0 (frozen)

**Status:** Complete · Closed · Frozen.  
**Classification:** Small operational correction · **Not** Scope capability.

**Mission:** Preserve the customer’s original reason for the visit on the RO without auto-creating the first estimate concern. Propose concerns from Visit Reason for advisor accept — Visit Reason is never rewritten.

```text
Visit Reason (observation)
        ↓
Suggested Concerns (interpretation)
        ↓
Advisor Accept / Dismiss (confirmation)
        ↓
RepairOrderConcern (truth)
```

| Field | Role |
| --- | --- |
| `repair_orders.visit_reason` | Intake truth — why they’re here (verbatim) |
| Suggested concerns | When estimate empty only — heuristics / parser; OpenAI off on GET |
| Accept | Same `RepairOrderConcern` create path as manual add |
| Estimate concerns | Commercial truth — advisor accepts or adds manually |

**Do not reopen** unless a verified defect. Backlog only (not this PR): optional “Analyze Visit Reason” OpenAI action; shared Suggestion pattern for Findings/Scopes later.

**Scope capability** remains brief-only / inactive until floor asks again.

---

## Scheduled Outbound Messages v1 (frozen · SmsReply earned)

**Status:** Built · Estimate frozen · **SmsReply shipped** (floor pressure).  
**Classification:** Communications capability · **Not** an Estimate feature.

**Mission:** Let advisors finish after-hours estimate work **and typed SMS replies** without disturbing the customer or relying on memory — **Tomorrow Morning** (shop-local 08:00, next calendar morning).

```text
Estimate requests · typed SMS replies
        ↓
Communications owns ScheduledOutboundMessage (intent)
        ↓
Job executes
        ↓
SendEstimateDeliveryAction · SendOutboundMessageAction
        ↓
ConversationMessage (+ EstimateSent when estimate)
```

| Owns | Does not own |
| --- | --- |
| Scheduled intent · fire job · recipient/mode/body snapshot | Calendar UI · campaigns · payment/inspection schedules |

**Types:** `estimate_send` · `sms_reply`

**Observe (notebook, not roadmap):** Tomorrow Morning vs Send Now usage; cancellations; “wish I could send Friday.” Phase 2 only if that pressure clusters.

**Do not build until earned:** configurable send times · date/time picker · recurrence · payment/inspection/review schedules · scheduled-send inbox · scheduled MMS attachments.

---

## Service Catalog

**Status:** ❌ Not started · Not on the roadmap. Open only when a new operational problem cannot be solved within existing authority boundaries.

---

## Foundation — Estimate Pricing Engine

**Lifecycle complete.** Classification: Permanent Platform Infrastructure · Complete → Closed → Stable → Foundation.

**Engineering posture:** Consume it; don’t work on it. Product development exited; platform governance applies.

**Going forward:** New capabilities depend on it · Integrate through contracts · Do not modify its behavior. Enhancement requests that alter ownership, invariants, or public behavior belong elsewhere—or are rejected unless they correct a verified defect.

**Litmus test:** “Does this require changing the Estimate Pricing Engine?” → If yes: “Is this fixing a verified defect?” Yes → reopen narrowly. No → another capability.

**Doctrine:** doctrine `ark-pricing-snapshot-immutability.mdc`

---

## Sprint 1 — Communications Workspace (P0)

**Acceptance (nothing else):**

1. One Communications workspace
2. Identity always visible (name, phone, email)
3. One customer timeline (SMS, calls, voicemail, portal, email, …)
4. Next Actions always present
5. Needs Attention = sort/filter, not a separate world
6. RO view = filtered projection of the same timeline

**Sarah gate:** Open Needs attention → know who, how to reach them, vehicle/RO state, next actions — without hunting or opening the RO.

**Out of sprint:** Companion parity · CRM chrome · new transports · new doctrine

**Guardrail (binding, already written):** [communications-workspace-rules.md](communications-workspace-rules.md)

---

## Shipping posture

- **Build Mode:** doctrine `ark-build-mode.mdc` — doctrine closed; implement; Doctrine budget: ZERO; discover ≠ generalize
- Stop producing audits unless requested
- Build vertical slices that: compile · pass tests · demo (screenshot/recording) · commit independently
- No multi-topic commits · no speculative refactors · no new doctrine docs unless implementation reveals a genuine conflict

---

## Ladder

Capability → Engineering → Runtime → Operator adoption → Floor proof → Sellable
