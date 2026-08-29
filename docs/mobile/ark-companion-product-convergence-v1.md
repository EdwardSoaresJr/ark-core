# The Companion — Product Convergence v1

**Status:** Operating model approved (Edward, 2026-07-04) — **superseded for implementation order by [`companion-authority-model-v1.md`](companion-authority-model-v1.md).**  
**Internal name during this sprint:** **The Companion** (not ARK Mobile).  
**Gate:** [`companion-authority-model-v1.md`](companion-authority-model-v1.md) ✅ → [`companion-event-architecture-sprint-v1.md`](companion-event-architecture-sprint-v1.md) → `companion-shell-v1.md` → Flutter.

---

## Doctrine (frozen for this sprint)

> **Desktop organizes information. Mobile responds to events.**

Desktop users **browse**. Phone users **react**.

| Surface | Job |
|---------|-----|
| **Desktop** | Manage the business — organize, review, configure |
| **The Companion** | Run the business — respond in the next 30 seconds |

**Primary question (every surface):** *What should I do before I put this phone back in my pocket?*

**Heart of the product:** **Customer Timeline** — not a screen name, an authority. Workspace is how we render it; timeline is what ARK owns.

**Next sprint (separate):** [Event Architecture Sprint](#9-follow-on-event-architecture-sprint) — events as the platform abstraction shared by mobile, desktop, notifications, voice, and future PTT.

**Out of scope:** Voice Phase D backend cleanup — voice subsystem remains **Observing**.

**Companions:** [`ark-staff-product-constitution-v1.md`](ark-staff-product-constitution-v1.md) · doctrine `ark-staff-product-constitution.mdc`

---

## Phase 0 approval record

| Decision | Verdict |
|----------|---------|
| Delete Apps tab + `AppsScreen` | ✅ Approved — delete tomorrow |
| Converge duplicate customer workspaces (one timeline, many entry points) | ✅ Approved |
| Build everything around **Customer Timeline** | ✅ Approved |
| Separate **advisor** and **technician** experiences (different products) | ✅ Approved |
| Search → **Command palette** (Spotlight), not navigation | ✅ Approved |
| Keep **Customers** as authority + entry point | ✅ Approved — **do not delete** |
| Communications as "the OS" | ❌ Rejected — **Events are the OS** |
| Shrink registration banner | ❌ Rejected — **delete banner**; healthy infra invisible |

---

## Executive summary

The Companion today is **desktop ARK on a phone**: modules, launchers, and duplicate paths to the same customer context.

Convergence is **not** deleting Customers because Comms also opens a workspace. The fix is recognizing:

```text
Many entry points  →  one shared destination  →  one timeline

Phone rings          ─┐
Search customer      ─┤
VIN lookup           ─┼──► Customer Workspace  ──► Customer Timeline
Appointment          ─┤         (implementation)      (authority)
Customers tab        ─┘
```

**Events are the operating system.** Phone, SMS, portal, inspection, payment, appointment, transfer — each **produces** events. Home **consumes** shop-level events ("what changed since I last looked?"). Customer Timeline **organizes** customer-scoped events. Communications **transports** some of those events — it is not the OS.

**Stop building pages. Start building workspaces around timelines.**

---

## 1. Complete architectural audit

### 1.1 What the code encodes today (wrong model)

| Layer | Today | Problem |
|-------|-------|---------|
| **Apps** | 16-tile launcher | Desktop CRM — **delete** |
| **Home** | Pulse widgets + quick actions + attention | Dashboard, not **change feed** |
| **Comms tab** | reference CRM-style inbox | Treated as OS — should be **recovery view on events** |
| **Customers tab** | Customer list | Correct **authority** — wrongly flagged as duplicate |
| **Customer workspace** | Server-projected blocks | Right shape — timeline must become **primary**, not one block |
| **Search** | Entity lookup | Objects-first — should be **command palette** |
| **Technician** | Same 5 tabs as advisor | Same **product** — should be **different product** |
| **Voice banner** | `VoicePostureBanner` ListTile | Diagnostic chrome on healthy path — **delete** |
| **Identity** | Profile page + decorative station | Not operational — needs **Identity authority** |

### 1.2 Correct operating model

```text
Event producers                    Event consumers

Phone · SMS · Portal               Home feed ("what changed?")
Inspection · Payment               Customer Timeline (organize)
Appointment · Transfer             RO workspace (execute)
Technician status · Internal       Comms recovery (unhandled transport events)
                                   Command palette (start work)
                                   Push / interrupt overlay
```

Communications is **one subsystem** among producers. Repairs, appointments, portal, and technicians produce events too.

### 1.3 Existing backend alignment (protect)

ARK already has unified timeline read-model:

- `OperationalEventEntry` — composes authority into one renderable event (`app/Ark/Operations/Timeline/`)
- `CommunicationEvent` → timeline mappers
- Customer workspace API already exposes a `timeline` block

Convergence **surfaces** this model on mobile. Event Architecture Sprint **extends** it platform-wide.

### 1.4 System bugs (unchanged — fix after IA)

| ID | Bug | Convergence fix |
|----|-----|-----------------|
| SYS-1 | Nested Scaffolds | One shell; workspace bodies only |
| SYS-2 | Identity lost on drill-in | Operator Identity + customer identity persist |
| SYS-3–4 | Spacing / card chaos | Design system after Phase 2 |
| SYS-5 | Command bar not context-aware | Timeline-scoped Finish Work |
| SYS-6 | Duplicated previews | **Timeline replaces duplicates** |

### 1.5 Advisor vs technician — different products

Not navigation profiles. **Different Companion products** sharing one backend.

| | **Advisor Companion** | **Technician Companion** |
|--|----------------------|---------------------------|
| Question | Who needs me? What changed? | What's today's vehicle? |
| Nav | Home · Comms · **Customers** · Schedule | **Today** · Inspection · Photos · Talk |
| Never | — | Customers tab, shop inbox, payments nav, approvals queue |
| Failure test | — | Landon opens Customers → **product failure** |
| Timeline | Customer-scoped | Vehicle/RO-scoped |
| Done | Pocket after customer action | Pocket after inspection/photos/talk |

---

## 2. Screen inventory (27 screens)

### Keep / evolve

| Screen | Convergence role |
|--------|------------------|
| `home_shell.dart` | Shell: status-bar voice dot · interrupt overlay · Identity strip |
| `orientation_home_screen.dart` | **Change feed** — not widgets |
| `comms_hub_screen.dart` | **Recovery** — unhandled transport events, not "the app" |
| `customers_screen.dart` | **Customer authority entry** — browse/find identity → workspace |
| `customer_workspace_screen.dart` | **Timeline-first** implementation surface |
| `repair_order_workspace_screen.dart` | Execution |
| `vehicle_workspace_screen.dart` | Technician primary object |
| `global_search_screen.dart` | **Command palette** — not a tab |
| `communication_thread_screen.dart` | Embed in timeline — not standalone route |
| Production flows | check-in, VIN, findings, walk-around — capabilities |

### Delete

| Screen | Reason |
|--------|--------|
| `apps_screen.dart` | ✅ Approved — desktop thinking |
| `conversations_screen.dart` | Orphan duplicate of comms hub |
| `customer_context_screen.dart` | Superseded by workspace + timeline |
| `attention_screen.dart` | Events feed Home — not third destination |
| `shop_screen.dart` | Shop events on Home feed |
| `more_screen.dart` | Fold into Settings / Identity |

### Role-gated

| Screen | Advisor | Technician | Manager |
|--------|---------|------------|---------|
| Customers tab | ✅ | ❌ never | ✅ |
| Comms recovery | ✅ shop-wide | scoped to assigned work | ✅ |
| Schedule | ✅ | ❌ | ✅ |
| Owner surfaces | optional | ❌ | ✅ via Settings |

---

## 3. Duplicate workflow inventory

### 3.1 Not duplicates — shared destination

| Entry point | Question asked | Same workspace? |
|-------------|----------------|-----------------|
| **Customers tab** | Who is this person? (identity browse) | ✅ Customer Workspace |
| **Comms recovery** | What happened while I was away? (transport) | ✅ Customer Workspace |
| **Home event tap** | What changed? (shop feed) | ✅ Customer Workspace |
| **Command palette** | Start work on Emma | ✅ Customer Workspace |
| **Incoming call** | Who is calling? (interrupt) | ✅ Customer Workspace |
| **VIN / appointment** | Which customer/vehicle? | ✅ Customer Workspace |

**Bug to fix:** duplicate **workspace implementations** or thread-as-route — not duplicate **entry points**.

### 3.2 True duplicates (delete)

| Duplicate | Keep |
|-----------|------|
| Apps → Calls, Photos, Payments… | Timeline + command |
| `ConversationsScreen` | Comms hub recovery |
| Attention + Shop + Home widgets | Home change feed |
| Thread route when customer known | Timeline embed |
| Customers tab search vs command palette | Command palette global; Customers tab = **browse authority** |

### 3.3 Navigation traps (unchanged)

- Search/command opens in tab navigator — wrong stack
- Nested Scaffolds on RO drill-in
- Technician seeing advisor nav

---

## 4. Navigation authority map

**Seven authorities** own navigation or shell state. Capabilities never do.

```text
┌──────────────────────────────────────────────────────────────┐
│  SHELL: Operator Identity · status-bar voice dot · interrupt  │
└──────────────────────────────────────────────────────────────┘
         │
         ├── EVENTS (Home) ─────── "What changed since I last looked?"
         │
         ├── CUSTOMERS ─────────── customer identity entry (advisor/manager)
         │
         ├── COMMUNICATIONS ────── recovery + transport (not the OS)
         │
         ├── CALENDAR ──────────── appointments (advisor)
         │
         ├── REPAIR ORDER ──────── execution workspace
         │
         ├── OPERATOR IDENTITY ─── presence · extension · device · current work
         │
         └── SETTINGS ──────────── configuration
```

### 4.1 Authority definitions

| Authority | Owns | Does not own |
|-----------|------|--------------|
| **Events (Home)** | Shop-level change feed since `last_seen_at` | Customer browse, execution |
| **Customers** | Customer **identity** entry — find, recognize, open timeline | Transport routing, inbox mechanics |
| **Communications** | Interrupt handling, recovery queue, transports (phone/SMS/email/portal/VM) | Event vocabulary, customer CRUD |
| **Customer Timeline** | Unified customer event stream (calls, texts, portal, inspection, payment, RO, VM, photos) | Shop-wide feed |
| **Repair Order** | Execution — concerns, findings, inspection, production | Shop inbox |
| **Calendar** | Appointments — day/agenda | Full scheduling CRM |
| **Operator Identity** | Edward · Ext 105 · Desk/Mobile · presence · availability · current work · move call · transfer (future) | Customer identity |
| **Settings** | Shop/device config | Operational posture |

**Operator Identity ≠ login.** Login is infrastructure. Identity is **where Edward is in the shop right now** — enormously important for shared extensions, move call, transfer, PTT, multi-device, docked phones, desktop client.

### 4.2 Advisor bottom nav (approved direction)

| Slot | Key | Label | Answers |
|------|-----|-------|---------|
| 1 | `home` | Home | What changed? |
| 2 | `comms` | Comms | What transport events need recovery? |
| 3 | `customers` | Customers | Who is this customer? |
| 4 | `schedule` | Schedule | Who's arriving? |

**Removed:** Apps.  
**Not a tab:** Command palette (global — pull down or persistent affordance).

### 4.3 Technician product (approved direction)

Not a nav profile — a **separate shell** (or mutually exclusive tab set):

```text
Today's vehicle  →  Inspection  →  Photos  →  Talk  →  Done
```

No Customers. No shop Comms hub. No Schedule tab. Talk = internal/advisor channel on assigned RO only.

### 4.4 Internal comms (reserve — NOT IMPLEMENTED)

PTT · dispatch · announcements · move call · transfer — couple to **Operator Identity** + internal event stream. Never merge into Customer Timeline transport rows.

---

## 5. Workspace convergence proposal

### 5.1 Customer Timeline — the heart

Everything flows into **one timeline**:

Calls · Texts · Portal · Inspection · Payment · Appointment · RO · Voicemail · Photos · Documents · Approvals

**Customer Workspace** is the implementation: identity strip + Finish Work + **timeline-first layout** + command bar.

| Block today | Convergence |
|-------------|-------------|
| `timeline` | **Primary surface** — not a section below conversation |
| `conversation` | Latest transport thread **inside** timeline — not parallel page |
| `calls` | Merge into timeline — delete separate calls preview block |
| payments, photos, portal | Timeline events + command actions |

### 5.2 Home — change feed, not pressure

**Question:** *What changed since I last looked?*

Not: *What pressure exists?* (different mental model)

**Feed examples (chronological, not widgets):**

- Customer replied
- Inspection finished
- Josh transferred a call
- Estimate approved
- Customer arrived
- New voicemail
- Tech blocked

One scannable list. No shop pulse dashboard. No six quick-action tiles. No module summary.

Finish Work may appear **on the event row** — not a separate widget layer.

### 5.3 Communications — transport + recovery

**Not the OS.** One event producer and one recovery surface.

| Role | UI |
|------|-----|
| Interrupt | Incoming call overlay → Customer Workspace |
| Active call | Compact bar |
| Recovery | Comms tab — oldest unhandled transport events first |
| Healthy phone | **Nothing** — see Identity / status bar |

### 5.4 Operator Identity + voice chrome

**Delete `VoicePostureBanner` entirely.** Not shrink — delete.

Healthy infrastructure **does not exist in UI**:

- Status bar: tiny green dot
- Tap if curious → sheet (extension, devices, reconnect, diagnostics, move call)
- Otherwise **disappear**

Identity authority owns: extension, device, presence, availability, current work, transfer posture (future).

### 5.5 Command palette — not Search nav

Global affordance (Spotlight pattern). Typing **Emma**:

```text
Call Emma
Text Emma
New RO for Emma
Take payment
Find vehicle
Schedule
```

Actions before objects. Opens in **root navigator**. Never a bottom tab.

Customers tab remains for **browse-by-identity** — different job than command.

### 5.6 RO workspace — execution

Advisor: lifecycle, estimate events on timeline, payments.  
Technician: inspection, photos, talk — no customer shop workflows.

---

## 6. Deletion candidates

### 6.1 Delete (approved)

| Target | Status |
|--------|--------|
| **Apps tab + `AppsScreen`** | ✅ Delete |
| **`VoicePostureBanner`** | ✅ Delete — status bar dot only |
| **`ConversationsScreen`** | Delete |
| **`CustomerContextScreen`** | Delete |
| **Standalone Attention / Shop destinations** | Delete — merge into Home feed |
| **All Apps capability tiles** | Delete with Apps |

### 6.2 Do NOT delete

| Target | Reason |
|--------|--------|
| **Customers tab** | Customer identity authority + entry point |
| **Customer Workspace screen** | Timeline implementation |
| **Comms recovery tab** | Transport recovery — distinct entry from Customers |
| **RO / vehicle workspaces** | Execution |
| **Voice runtime** | Observing — no transport changes |

### 6.3 Demote

| Target | Becomes |
|--------|---------|
| `CommunicationThreadScreen` route | Timeline embed |
| Home quick actions / pulse widgets | Command palette + change feed |
| Profile page | Operator Identity authority |
| Intake hub grid | Command: New intake |

---

## 7. New information architecture

### 7.1 Event flow (platform)

```text
Authority changes (call, text, inspection, payment…)
        ↓
Event recorded (CommunicationEvent, OperationalEvent, …)
        ↓
┌───────────────────┬────────────────────────┐
│ Home feed         │ Customer Timeline       │
│ (shop-level       │ (customer-scoped        │
│  since last look) │  organized stream)      │
└───────────────────┴────────────────────────┘
        ↓                       ↓
   Finish Work            Finish Work
        ↓                       ↓
   Operator acts           Operator acts
        ↓                       ↓
   Pocket                   Pocket
```

### 7.2 Advisor flow

```text
Unlock → Home change feed OR Customers OR Comms recovery OR Command
      → Customer Workspace (timeline-first)
      → Act in 30 seconds
      → RO if execution needed
      → Pocket
```

**One-tap test:** call → customer, vehicle, RO, timeline, estimate, payment, photos, inspection.

### 7.3 Technician flow

```text
Unlock → Today's vehicle
      → Photos · Inspection · Talk
      → Pocket
```

Landon never opens Customers.

### 7.4 Entry point vs destination matrix

| Entry | Operator intent | Destination |
|-------|-----------------|-------------|
| Home event | Something changed | Customer Timeline |
| Comms row | Recover transport | Customer Timeline |
| Customers row | Find customer identity | Customer Timeline |
| Command | Start work | Customer Timeline or RO |
| Call interrupt | Answer / identify | Customer Timeline |
| Schedule row | Appointment context | Customer Timeline |
| My Work (tech) | Assigned vehicle | RO / Vehicle workspace |

---

## 8. Migration plan

### Phase 0 — ✅ Approved (this document)

Corrections incorporated above. No Flutter until Phase 1 contract frozen.

### Phase 1 — Backend projection (arksmsv2)

| Work | Notes |
|------|-------|
| **Home change feed API** | Shop events since `last_seen_at` — not attention widgets |
| **Customer timeline API** | Promote timeline; merge calls/conversation into `OperationalEventEntry` stream |
| **Shell: advisor vs technician** | Separate `companion_product: advisor \| technician` — not nav tweak |
| **Operator Identity projection** | Extension, device, presence, current work — `/api/mobile/identity` or shell block |
| **Command palette API** | Intent results: actions + objects from one query |
| **Remove Apps from nav payload** | Delete key; no feature flag needed |
| **Keep Customers nav** | Explicit enabled for advisor/manager only |
| Document contract | `docs/mobile/companion-shell-v1.md` |

### Phase 2 — Companion shell (ark-mobile)

| Work | Priority |
|------|----------|
| Delete Apps screen + tab | P0 |
| Delete `VoicePostureBanner` — status bar dot | P0 |
| Home → change feed only | P0 |
| Timeline-first Customer Workspace | P0 |
| Command palette (global, not tab) | P0 |
| Technician shell (separate product) | P0 |
| Keep Customers tab — clarify vs Comms in UI copy | P1 |
| Root navigator for command + deep links | P1 |

### Phase 3 — Delete debt

Orphan screens, thread-as-route, Apps deep links, attention/shop standalone.

### Phase 4 — Operator Identity surface

Presence affects routing (when backend ready). Move call · transfer hooks. Multi-device.

### Phase 5 — Observation

Notebook: "Where do I go?" · technician Customers tab opens (should be zero) · command vs browse usage.

### Sequencing

| Parallel | Blocked |
|----------|---------|
| Phase 1–2 convergence | Voice Phase D |
| Event Architecture Sprint (Phase 6) | After Phase 2 shell stable |

---

## 9. Follow-on: Event Architecture Sprint

**Not a UI sprint.** The convergence that makes the platform feel inevitable.

Once you stop thinking in pages and modules, the next thing to stop thinking in is **communications alone**. Everything in ARK is a stream of events flowing through **identities, customers, vehicles, repair orders, and timelines**.

| Consumer | Speaks events |
|----------|---------------|
| The Companion (mobile) | Home feed + Customer Timeline |
| Desktop ARK | Browse + organize (different posture) |
| Push notifications | Event pointers |
| Phone system | Call events |
| Future PTT | Internal events |

**Goal:** One event vocabulary — mobile, desktop, notifications, voice, PTT — instead of each inventing its own model.

**Existing foundation (arksmsv2):**

- `OperationalEventEntry` + mappers (`CommunicationEvent`, `CallSession`, `ConversationMessage`, …)
- `OperationalEventRecorder` / `CommunicationEventRecorder`
- Customer workspace `timeline` block
- Tests: `UnifiedOperationalTimelineTest`

**Event Architecture Sprint deliverables (draft scope):**

1. Event taxonomy — shop feed vs customer timeline vs RO timeline vs identity events
2. Producer catalog — every authority that emits, mapped to existing recorders
3. `last_seen_at` / cursor model for "what changed since I last looked"
4. Mobile API: Home feed = projected shop events; workspace = projected customer events
5. Push payload maps to event ID + entry point — not module routes
6. Desktop alignment doc — same events, different organization posture

**Do not start Event Architecture until Companion Phase 2 shell is stable.**

---

## Appendix A — Glossary

| Term | Meaning |
|------|---------|
| **The Companion** | Phone runs the business |
| **Event** | Something that happened — the OS abstraction |
| **Home feed** | Shop-level: what changed since I last looked |
| **Customer Timeline** | Customer-scoped event authority — the heart |
| **Customer Workspace** | Implementation surface for timeline + actions |
| **Customers (tab)** | Identity entry — browse/find customer |
| **Comms (tab)** | Transport recovery — not the OS |
| **Command palette** | Spotlight — start work, not search nav |
| **Operator Identity** | Where the operator is — extension, presence, device, current work |
| **Advisor / Technician Companion** | Different products, same backend |

## Appendix B — Evidence paths

| Area | Path |
|------|------|
| Timeline read-model | `app/Ark/Operations/Timeline/OperationalEventEntry.php` |
| Customer workspace | `ark-mobile/lib/screens/customer_workspace_screen.dart` |
| Apps (delete) | `ark-mobile/lib/screens/apps_screen.dart` |
| Voice banner (delete) | `ark-mobile/lib/widgets/voice_posture_banner.dart` |
| Shell nav API | `app/Ark/Mobile/MobileUserPresenter.php` |

---

**Next step:** **E1 Contract Realization** — architecture closed until implementation proves a gap.
