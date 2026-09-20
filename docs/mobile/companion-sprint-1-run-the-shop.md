# Companion Sprint 1 — Run the Shop

**Status:** **Execution milestone** — starts **after** Companion v1 product discovery gate.  
**Discovery (active now):** [`../companion-v1/README.md`](../companion-v1/README.md)

**Owner:** Edward (Demo Auto Repair)  
**Device:** Razr — primary execution surface for the advisor day

---

## North star

> **Edward can leave his laptop on his desk and only walk back for work that genuinely requires a large screen.**

**Product sentence:**

> **Every interruption should become one tap to the correct workspace.**

Not perfect architecture. Not event realization. **One tap.**

---

## The test (Monday morning)

Hand Edward **only the Razr**. Take away the desktop.

Can he comfortably:

| Question | Sprint board |
|----------|--------------|
| Answer every customer call? | Incoming call |
| Reply to every text? | Reply to text |
| See every active RO? | *(via search + continuity)* |
| See what changed while away? | Morning continuity |
| Find any customer in 5 seconds? | Customer search |
| See what Ben or Landon needs? | Photo from tech · notifications |
| Approve work? | *(RO workspace — after notification routing)* |
| Take payment if needed? | *(search → pay — after customer search)* |
| Stay in context the entire time? | Ten-hour workday |

If **no** → that's what we build.

**Progress filter:**

> Does this help Edward run Demo Auto Repair from the phone **today**?

If not → not P0.

---

## Sprint board (measure here — not documents)

| Experience | Status | Notes |
|------------|--------|-------|
| **Incoming call** | ❌ | Context before answer · stay on screen after hangup |
| **Notification → correct screen** | ❌ | Tap lands in workspace — not Home |
| **Customer search** | ⚠️ | Exists · not yet "search starts work" |
| **Reply to text** | ⚠️ | API exists · shell/UX friction |
| **Photo from tech** | ❌ | Notification → RO/inspection · reply in context |
| **Morning continuity** | ❌ | Unlock → what changed · pocket · done |
| **Ten-hour workday** | ❌ | Composite — Edward loves using his own app |

Update this table when an experience passes the Monday test on the floor.

---

## Experience 1 — Customer calls

**Goal:** Never hunt.

**While ringing — before answer:**

- Customer name
- Vehicle
- Active RO
- Estimate status
- Last message
- Advisor notes

**After hangup — without backing out:**

- Add note
- Send text
- Schedule
- Open RO

**P0:** Real phone call (not overlay theater) + full context on one screen.

---

## Experience 2 — Notification

**Example:** *Ben uploaded inspection.*

**Tap →** already inside **that inspection**.

Not Home. Not Conversations. Not Customers. **The inspection.**

**P0:** Deep link every notification type to the correct workspace.

---

## Experience 3 — Search

**Type:** `Emma`

**Results actions:**

- Call
- Text
- Open RO
- Schedule
- Take payment
- History

**Search starts work** — not a directory browse.

---

## Experience 4 — Morning

**Unlock phone.** Not dashboards. Not KPIs.

Just:

- 3 customers replied
- 2 inspections finished
- 1 estimate approved
- Josh called while closed
- John arriving at 9:00

**Done. Pocket.**

**P0:** Continuity feed — what changed since last unlock.

---

## Priority stack

### P0 (this sprint)

- Phone actually works · registration stays alive
- Customer context on incoming call
- Messages + reply
- Notifications → correct screen
- Morning continuity
- Customer search → act

### P1 (after P0 passes floor test)

- Transfer · move call
- Presence
- Desktop client parity where mobile already wins

### P2 (explicitly wait)

- PTT
- Dispatch
- AI summaries
- Automation
- Event contract realization rows
- New architecture documents

---

## What we do not do this sprint

- Replace or extend the event architecture sprint (frozen — see below)
- New doctrine or platform documents
- E1 spreadsheet work unless a P0 ship is blocked
- Features Edward would not use on the Razr in a ten-hour day

---

## Architecture (frozen reference)

Event contracts, authority model, and scoped streams **already did their job**. They explain implementation when needed. They are **not** the product and **not** the sprint board.

**Frozen:** [`companion-event-architecture-sprint-v1.md`](companion-event-architecture-sprint-v1.md) — do not extend unless implementation proves the model cannot express a P0 experience.

**Use when building:** existing `/api/mobile/*` authority · conversation rail · continuity APIs · voice ingress — not new vocabulary.

---

## Ship rhythm

One experience · one vertical slice · one PR · floor test · update sprint board.

Same discipline as telephony certification — but the bar is **Edward loves using his own app**, not a completed register row.

---

## First PR (suggested)

**Experience 1 + 2 foundation:** inbound call context screen + notification deep link to correct workspace.

Success: Edward answers a call without hunting; taps one notification and lands in the right RO/thread/inspection.
