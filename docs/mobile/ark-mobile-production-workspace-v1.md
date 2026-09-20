# ARK Mobile Production Workspace v1

**Status:** Active milestone  
**Parent doctrine:** [Workflow doctrine](./ark-mobile-workflow-doctrine.md) — workflow engine, not CRUD screens  
**North star:** Can a technician complete an entire repair without needing a desktop?  
**Sequence:** Authority (ARK V2) → Mobile projection → Production Workspace UX

**Interaction language:** [ark-workspace-interaction-language-v1.md](../ecosystem/ark-workspace-interaction-language-v1.md) — workspace grammar, explainable operations, six questions every object answers

---

## Vision

ARK Mobile is no longer a technician app.

**ARK Mobile is the production workspace for technicians.**

| Surface | Role |
| --- | --- |
| **ARK Operations (Desktop)** | Run the shop |
| **ARK Mobile (Phone / Tablet)** | Perform the repair |
| **ARK Portal (Customer)** | Experience the repair |
| **ARK Display (Shop TVs / Kiosks)** | See the shop |
| **ARK Voice** | Connect the shop |

Each is a **projection of the same authority**, optimized for a different audience — not separate apps with overlapping responsibilities.

---

## Principle

Technicians should not need to walk back to a desktop to record operational truth.

Everything that happens beside the vehicle should happen on the phone or tablet.

Mobile does not invent workflow. It consumes existing ARK authority through `/api/mobile/*` projections. See [ark-mobile-projection-v1.md](./ark-mobile-projection-v1.md).

---

## Primary workflow

```
My Work
  ↓
Open Repair Order
  ↓
Select Concern
  ↓
Record Findings
  ↓
Capture Evidence
  ↓
Leave Notes
  ↓
Update Production
  ↓
Move to Next Vehicle
```

The phone stays in the technician's hand.

---

## Repair Order — the technician workspace

The Repair Order is the technician's workspace, not a summary card.

**Sections (reachable within one or two taps):**

| Section | Purpose |
| --- | --- |
| Customer / Vehicle | Identity at a glance |
| Concerns | First-class entry points |
| Approved Work | What the customer authorized |
| Findings | Diagnostic truth |
| Photos | Evidence gallery |
| Production | Work pacing and completion |
| Communications | Context on assigned work only |

Avoid deep navigation stacks. Prefer concern-scoped drill-down over RO-level tabs that hide work.

---

## Concerns — first-class

Technicians think in **concerns**, not repair orders.

Tap a concern to open:

- Customer concern text
- Findings
- Photos
- Recommendations
- Notes
- Production history

Concern detail is the primary production surface. RO detail is the container.

---

## Findings — finding-first workflow

**Recent findings are the default view.** Inspection categories are metadata, not navigation.

### Capture flow

```
Take Photo  OR  Add Measurement  OR  Voice Note
  ↓
Title
  ↓
Intent
  ↓
Measurement (when applicable)
  ↓
Save
```

Large **+ Finding** affordance. Findings create existing **`InspectionItem`** authority — no parallel finding store.

### Camera first

The camera is part of the workflow, not an attachment step.

```
Open camera → Take photo → "What did you find?" → Save
```

Not: open finding form → scroll → attach photo.

### Voice notes

Support voice-to-text as another path to authority:

> "Right front brake pads measure one millimeter. Rotor heavily grooved."

ARK transcribes. Technician edits if needed. Save.

Voice becomes input to findings — not a separate voice product.

---

## Tablet optimization

Tablet is the **preferred inspection surface** beside the vehicle.

Show on one screen where layout allows:

- Vehicle identity
- Active concern
- Recent findings
- Photos
- Recommendations

Support handing the tablet to the customer beside the vehicle without excessive navigation.

Phone remains valid; tablet is not a desktop clone.

---

## Production

Technicians must be able to, through **existing ARK authority** (no mobile-only workflow):

- Start work
- Pause
- Resume
- Finish
- Add production note
- Complete labor operation
- Record repair verification

All writes go through server projections — same lifecycle and production truth as desktop.

---

## Communications

Within the assigned RO:

- Read customer conversation
- Read advisor notes
- Add internal note
- Receive approval notification (poll first; push transport deferred)

No separate messaging product. No SMS inbox. No provider SDKs in Flutter.

See [ark-mobile-communications-authority-contract.md](./ark-mobile-communications-authority-contract.md).

Technicians: assigned RO conversations only. Advisors: broader comms where permissions allow.

---

## Non-goals (Production Workspace v1)

Do **not** build on mobile:

- Desktop estimate builder
- Scheduling
- Payments
- Customer portal
- Accounting
- Shop analytics
- Management dashboards
- Shop-wide Attention queue as primary nav (technicians)
- Parallel inspection / finding / production authority

Those belong on desktop or other projections.

---

## Success criteria

A technician can:

1. Receive assigned work
2. Walk to the vehicle
3. Perform diagnosis
4. Record findings
5. Capture photos
6. Record measurements
7. Leave notes
8. Verify repair
9. Complete production

**Without returning to a desktop.**

**Floor test:** If technicians naturally reach for ARK Mobile while standing next to the vehicle, the Production Workspace has succeeded.

Same observation discipline as [ark-mobile-notification-doctrine.md](./ark-mobile-notification-doctrine.md): prove the workspace before adding transport, automation, or enforcement.

---

## Current baseline → v1 gaps

| Capability | Baseline | v1 target |
| --- | --- | --- |
| My Work + assigned RO | ✅ | Maintain |
| RO / concern drill-down | ✅ | Concern-first hierarchy ✅ |
| Finding capture + photo upload | ✅ | Camera-first flow ✅ |
| Offline photo queue (native) | ✅ | Maintain |
| Scope production status | ✅ | Full production actions |
| Assigned RO comms + internal notes | ✅ | RO-embedded comms rail |
| Voice-to-text findings | ✅ | Maintain |
| Tablet layout | ✅ | Photo thumbnails in strip |
| Labor complete / repair verification | — | Expose via API + UI |
| Push notifications | Deferred | Poll until floor proof |

Implement gaps through **new or extended `/api/mobile/*` projections** — never duplicate authority in Flutter.

---

## Companion docs

- [ark-mobile-projection-v1.md](./ark-mobile-projection-v1.md) — API and authority lock
- [ark-mobile-communications-authority-contract.md](./ark-mobile-communications-authority-contract.md) — comms transport lock
- [ark-mobile-notification-doctrine.md](./ark-mobile-notification-doctrine.md) — notification authority vs transport
- [technician-scope-doctrine-v1.md](../operations/technician-scope-doctrine-v1.md) — assigned work only
