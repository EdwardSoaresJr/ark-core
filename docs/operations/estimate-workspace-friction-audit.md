# Estimate Workspace — Advisor Friction Audit

**Milestone:** Estimate Workspace: Friction Discovery  
**Status:** Observation · **zero implementation**  
**Companion closed:** Shop Memory v1 (`milestone/shop-memory-v1`)

**Anchor question:** Where does momentum stop while building an estimate?

Not: what could be automated · what fields are missing · what should AI do.

Every hesitation is one of: missing information · unnecessary navigation · unclear decision · duplicated work.

---

## Rules

1. Follow one real estimate (or fresh Check In → Review) without fixing anything.
2. Speak pauses out loud; write **one sentence** per pause.
3. Prefer **clusters** over one-offs before naming any capability.
4. Do **not** solve during the audit. Capture only.
5. After one focused walk (or a short floor week), stop — then name the next capability from dominant categories only.

---

## Categories (prediction — revise from evidence only)

| Category | Meaning |
| --- | --- |
| **Information** | I don’t know enough |
| **Navigation** | I had to leave this screen |
| **Choice** | I know what I want but there are five ways |
| **Memory** | I forgot our shop standard (Shop Memory may already cover some) |
| **Context** | I can’t see everything I need at once |

---

## Path (actual surfaces)

| Step | Surface | Route |
| --- | --- | --- |
| 1 | Check In queue | `/app/intake` |
| 2 | Check In form | `/app/intake/new` |
| 3 | Edit Estimate · visit reason | `/app/repair-orders/{id}/edit` |
| 4 | Add Concern (popup) | same Edit |
| 5 | Repair actions / work groups | same Edit |
| 6 | Labor / parts lines | same Edit |
| 7 | Intent + disposition | same Edit |
| 8 | Estimate Review | `/app/repair-orders/{id}` |
| 9 | Send estimate (SMS / email / link) | Review · Comms |
| 10 | Authorization | Review · Auth |
| 11 | Deposit / payment (if on path) | Financial strip |

---

## Capture table

| # | Step | Pause (one sentence) | Category | Who | Date |
| --- | --- | --- | --- | --- | --- |
| 1 | Check In form | Before leaving Check In, advisor briefly switches between intake-truth thinking (visit reason) and estimate-building thinking (concerns) | Choice | Edward | 2026-07-23 |
| 2 | Edit · visit reason | Accepting/dismissing suggested concerns forces a conscious decision: customer language vs operational concern authority | Choice | Edward | 2026-07-23 |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |

## Capture table

| # | Step | Pause (one sentence) | Category | Who | Date |
| --- | --- | --- | --- | --- | --- |
| 1 | Check In form | Before leaving Check In, advisor briefly switches between intake-truth thinking (visit reason) and estimate-building thinking (concerns) | Choice | Edward | 2026-07-23 |
| 2 | Edit · visit reason | Accepting/dismissing suggested concerns forces a conscious decision: customer language vs operational concern authority | Choice | Edward | 2026-07-23 |
| 4 | Edit / Review / PDF estimate | Priority classification is projected as document structure (Diagnostic / Immediate Attention / Maintenance / Plan Soon containers), producing empty group chrome and awkward page breaks | Context | Edward | 2026-07-24 |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |
| | | | | | |

### Emerging clusters (observation only — no fix)

| Cluster | Category | Occurrences | Pattern | Outcome |
| --- | --- | --- | --- | --- |
| Intake Truth → Operational Truth transition | Choice | #1, #2 | Advisor decides when customer language stops being intake and becomes estimate authority | **Handoff-localized** — Check In → Estimate entry only; does **not** continue into Add Concern |
| Approval Forecast missing | Information | #3 | While building recommendations after approved work, advisor cannot answer “if I approve all of this today, what’s my total?” without mental math or leaving the workspace | **Earned → shipped** · `ApprovalForecastProjection` on Edit/Review rails + customer PDF/portal (invoice story vs advisor compact) |
| Priority as container | Context | #4 | Intent/priority wrappers create empty headers across page breaks; concerns already carry priority identity | **Earned → shipping** · flatten PDF + advisor surfaces — concern is the visual unit; priority is metadata + sort order |

### Cleared steps (no pause)

| Step | Surface | Note | Who | Date |
| --- | --- | --- | --- | --- |
| 1 | Check In queue | No hesitation — queue answers “which customer next?”; select and enter without thinking or switching context | Edward | 2026-07-23 |
| 4 | Add Concern (popup) | No pause — mental model already operational; compose is clear authorship boundary; Intake→Operational cluster does not persist here | Edward | 2026-07-23 |

---

## Guided walk script

For each step, ask only: **Did momentum stop?**

If yes → one sentence → category → next step.  
If no → next step.  
Never propose a fix in the same breath as the pause.

### Steps (ask in order)

1. **Check In queue** (`/app/intake`) — Did momentum stop? → **No** (cleared)
2. **Check In form** (`/app/intake/new`) — Did momentum stop? → **Yes** · Choice (#1)
3. **Edit · visit reason** — Did momentum stop? → **Yes** · Choice (#2) · same cluster as #1
4. **Add Concern (popup)** — Did momentum stop? → **No** (cleared) · cluster handoff-localized
5. **Repair actions / work groups** — Did momentum stop? → **Yes** · Information (#3) · Approval Forecast earned/shipped
6. **Labor / parts lines** — Did momentum stop? ← **current**
7. **Intent + disposition** — Did momentum stop?
8. **Estimate Review** — Did momentum stop?
9. **Send estimate** — Did momentum stop?
10. **Authorization** — Did momentum stop?
11. **Deposit / payment** (if on path) — Did momentum stop?

### Session log

| Session date | Walked by | Path notes |
| --- | --- | --- |
| 2026-07-23 | Edward + engineering | Friction Discovery opened · notebook + milestone pointers live · guided walk ready at Step 1 · **no solutions** |
| 2026-07-23 | Edward + engineering | Step 1 cleared (no pause) · proceed Step 2 Check In form |
| 2026-07-23 | Edward + engineering | Pause #1 Choice on Check In form (intake vs estimate mental model) · watch for repeat on estimate entry · proceed Step 3 |
| 2026-07-23 | Edward + engineering | Pause #2 Choice · same cluster (Intake→Operational) · Step 4 Add Concern is the cluster test · no fix |
| 2026-07-23 | Edward + engineering | Step 4 cleared · cluster handoff-localized (not compose) · proceed Step 5 · still no fix |
| 2026-07-23 | Edward + engineering | Pause #3 Information · Approval Forecast candidate (approved vs recommended projected total) · ARKv1 parity request · no authority change |
| 2026-07-24 | Edward + engineering | Forecast earned · PDF presentation friction (box-in-box + “Needs your approval”) · invoice-style customer copy + compact advisor strip |
| 2026-07-24 | Edward + engineering | Pause #4 Context · Priority projected as containers (empty MAINTENANCE / PLAN SOON boxes across page breaks) · flatten: concern unit + priority badge/sort · authority unchanged |

---

## Cluster review (fill only after enough pauses)

| Category | Count | Dominant pause pattern (one line) | Earns a capability? |
| --- | --- | --- | --- |
| Information | | | Not yet |
| Navigation | | | Not yet |
| Choice | | | Not yet |
| Memory | | | Not yet |
| Context | | | Not yet |

**Next capability name:** _(blank until clusters earn it)_

---

## STOP

This notebook is the authority for Estimate Workspace next steps.

- No Estimate Workspace product code until clusters exist.
- No Shop Memory reopen from this milestone.
- Capability decision is a **separate** milestone after observation.
