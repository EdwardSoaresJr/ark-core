# The Companion — Authority Model v1

**Status:** **Approved** (Edward, 2026-07-04) — frozen. Next: [Event Architecture Sprint](companion-event-architecture-sprint-v1.md). **No `companion-shell-v1.md` or Flutter until feed/scoping contract ships.**  
**Product:** The Companion (phone runs the business; desktop organizes information).  
**Precedent:** [voice-runtime-authority.md](../runtime/voice-runtime-authority.md) — identity before implementation.  
**Companion:** [`ark-companion-product-convergence-v1.md`](ark-companion-product-convergence-v1.md) (operating model — subordinate to this doc).

---

## Doctrine (locked)

> **Screens never own truth. Authorities own truth. Events move truth. Timelines organize truth. Projections answer operator questions. Screens present projections.**

> **Events describe what happened. Observations describe what it means. Projections never invent events.**

> **Desktop organizes information. Mobile responds to events.**

### Stack (implementation order)

```text
Truth                ← what exists and what happened (authorities + append-only stores)
    ↓
Authority            ← who owns entity state and event vocabulary (this document)
    ↓
Event                ← business facts — see event-contracts-v1.md
    ↓
Event Stream Engine  ← infrastructure — scope membership; NOT an authority
    ↓
Observation          ← what it means
    ↓
Projection           ← never invent events
    ↓
Operator             ← human who acts
    ↓
Shell · Flutter      ← implementation last
```

**The telephony mistake:** We debated tabs, transports, and banners before asking who owns voice. Answer: Identity → Extension → Endpoint → PBX → Carrier. Everything collapsed.

**The Companion mistake to avoid:** Debating Home vs Comms vs Customers tabs before asking who owns customer truth, event scope, and operator identity.

---

## How to read this document

For each domain we answer:

| Question | Meaning |
|----------|---------|
| **Authority?** | Does this own operational truth or a stable product contract? |
| **Source of truth** | Tables / stores that must survive a projection rebuild |
| **Event producers** | What append-only facts this domain emits |
| **Event consumers** | What reads those facts (including other authorities) |
| **Timeline relationship** | Which timeline scope(s) include this domain's events |
| **Workspace relationship** | How mobile/desktop workspaces compose this authority |
| **Projections** | Disposable surfaces — never become truth |

**Verdict key:**

| Verdict | Meaning |
|---------|---------|
| **Entity authority** | Owns durable entity state (`Customer`, `Vehicle`, `RepairOrder`) |
| **Event authority** | Owns append-only facts (`CallSession`, `OperationalEvent`) |
| **Organizing authority** | Owns **relationship scope rules** (e.g. Operator Identity line) — not event streams |
| **Infrastructure** | Organizes without originating truth — Event Stream Engine, workspace layout |
| **Projection** | Rebuildable read model for a surface |
| **Configuration** | Shop behavior — points at authority, must not become truth |
| **Consumed capability** | External product (ARKademy) — link, not rebuild |

---

## The event question

We said **events are the operating system**. That is correct for **Companion posture** (react, don't browse).

It does **not** mean one monolithic `Events` table owns everything.

### What owns events?

| Layer | Owns | Role |
|-------|------|------|
| **Event authorities** (many) | Append-only records | **Truth** — what happened |
| **Event Stream Engine** | Infrastructure | Filters events into scoped views — **cannot originate truth** |
| **Observations** (interpretive) | Derived meaning | *Why it matters* — not raw events |
| **Projections** | Feed rows, workspace blocks | **Present** scoped events to an operator |

**Answer:** Events are owned by **their source authorities**. The **Event Stream Engine** organizes them — it does not own them. **Home** is a **Shop Feed projection** — not an authority.

```text
CallSession (truth)  ──produces──►  call event entry
                                        │
UnifiedOperationalTimeline (organize) ◄─┤
                                        │
MobileChangeFeedProjection (consume)  ◄─┘  "Josh transferred a call"
```

---

## Authority catalog (verdict summary)

| Domain | Verdict | One-line role |
|--------|---------|---------------|
| **Shop** | Entity + configuration boundary | Tenant — settings, hours, capabilities |
| **Operator** | Entity authority | Staff person (`User`) — roles, permissions |
| **Operator Identity** | Organizing authority | Extension, station, device, current work |
| **Presence** | Entity authority | Availability — Available, Busy, On call, Driving, Lunch (separate from Identity) |
| **Customer** | Entity authority | Person / relationship identity |
| **Vehicle** | Entity authority | **Owns itself** — YMM, VIN, plate; links to customer |
| **Repair Order** | Entity authority | Workflow execution unit on a vehicle |
| **Inspection** | Entity authority | **Owns itself** — findings, measurements, photos on an RO |
| **Financial** | Entity authority | Invoice, ledger, balance, payments — **not owned by Customer** |
| **Estimate** | Entity authority (document) | Versioned estimate lines + approvals context on RO |
| **Communication (relationship)** | Entity authority | `Conversation` — relationship thread identity |
| **Telephony (call)** | Event + entity authority | `CallSession` — call lifecycle truth |
| **Message** | Event authority | `ConversationMessage` — what was said |
| **Communication fact** | Event authority | `CommunicationEvent` — portal viewed, delivery failed, … |
| **Operational fact** | Event authority | `OperationalEvent` — RO lifecycle, production, intake |
| **Appointment** | Entity authority | Scheduled arrival — links customer + vehicle |
| **Timeline / Event Stream Engine** | **Infrastructure** — organizes authority-emitted events; cannot originate truth |
| **Observation** | Interpretive projection | Surprise / placement vocabulary |
| **Customer Timeline** | Projection | Customer-scoped event stream |
| **RO Timeline** | Projection | Production-scoped event stream |
| **Shop Change Feed** | Projection | "What changed since I last looked" (not "Home") |
| **Workspace** | Projection | Layout engine composing blocks for an anchor |
| **Recovery Queue** | Projection | Unhandled transport events (Comms tab) |
| **Customers Browse** | Projection | Identity index → opens Customer Timeline |
| **Command Palette** | Projection | Intent resolver — actions before objects |
| **Knowledge** | Consumed capability | ARKademy — external BookStack |
| **Configuration** | Configuration | Shop settings, telephony prefs, theme |
| **Shell** | Implementation | Tab sets, chrome — **never authoritative** |

---

## Resolved architecture questions

These were open in discovery. Answers are **product architecture**, not UI.

### Does Customer own Vehicle?

**No.** **Vehicle is its own entity authority.**

- **Truth:** `vehicles` — VIN, plate, YMM, identity pressure, notes.
- **Relationship:** `customer_id` links default owner — a vehicle can be reassigned; vehicle identity persists across ROs.
- **Companion implication:** Technician orients on **vehicle**; advisor orients on **customer** — same timeline scopes may differ.

### Does Repair Order own Inspection?

**No.** **Inspection is its own entity authority**, scoped to an RO.

- **Truth:** `inspections`, `inspection_items`, findings, measurements, photos.
- **Relationship:** `repair_order_id` — inspection **belongs to** RO execution without being a sub-document of RO status.
- **Events:** Finding recorded, measurement verified → `OperationalEvent` + timeline entries.
- **Companion implication:** Technician product centers Inspection authority; RO workspace **projects** inspection state.

### Does Payment belong to Customer, RO, or Financial?

**Financial authority** — anchored on **Repair Order**, attributed to **Customer** for relationship context.

- **Truth:** `repair_order_ledger_entries`, `payment_gateway_attempts`, invoice snapshots, `BalanceDueCalculator`.
- **Not** a Customer balance authority — customer may have multiple open ROs.
- **Timeline:** Payment events appear on **Customer Timeline** (relationship) and **RO Timeline** (execution).
- **Companion implication:** "Take payment" is a **command** on an RO / customer workspace — not a Payments app.

### Does Communication own the OS?

**No.** Communication **authorities produce and transport events**. They do not organize the shop.

- **Relationship authority:** `Conversation`
- **Call authority:** `CallSession`
- **Message authority:** `ConversationMessage`
- **Fact authority:** `CommunicationEvent`
- **Recovery projection:** Comms tab — unhandled transport events awaiting operator

### Does Home exist?

**No.** **Home is not an authority.**

| Wrong | Right |
|-------|-------|
| Home authority | **Shop Change Feed** projection |
| Home screen | Flutter presents the feed |
| "Pressure dashboard" | Chronological events since `last_seen_at` |

Same class as Needs Attention, Today, Recent, Unread — **projections**, not authorities.

---

## Per-authority proof

### Shop

| | |
|--|--|
| **Verdict** | Entity boundary + configuration host |
| **Source of truth** | Shop tenant, `shop_settings`, capabilities |
| **Event producers** | Shop-level config changes (rare); not operational event hub |
| **Event consumers** | All domains — read settings, never write truth on GET |
| **Timeline** | None — shop is container |
| **Workspace** | None directly |
| **Projections** | Capabilities shell, shop pulse counts, owner reports |

---

### Operator

| | |
|--|--|
| **Verdict** | Entity authority |
| **Source of truth** | `users` — name, email, roles, permissions, accent |
| **Event producers** | Auth session, assignment changes, actor attribution on events |
| **Event consumers** | Every projection needing "who acted" |
| **Timeline** | Actor field on all event entries |
| **Workspace** | Profile fragment — not a destination |
| **Projections** | `MobileUserPresenter`, role labels, staff access gates |

**Distinct from Operator Identity** — same split as Voice doc (person vs extension line).

---

### Operator Identity

| | |
|--|--|
| **Verdict** | **Organizing authority** (Voice precedent: Identity → Extension → Endpoint) |
| **Source of truth** | Extensions, endpoints, devices, workstations, voice session |
| **Event producers** | Registration, device attach, call ownership, transfer (future) |
| **Event consumers** | Provisioning, move call, multi-device |
| **Timeline** | Operator Feed — identity events |
| **Does not own** | Availability / routing posture — see **Presence** |

```text
Operator (User)
    ↓
Operator Identity (extension · station · device · current work)
    ↓
Presence (available · busy · on call · driving · lunch)   ← separate authority
    ↓
Endpoints (VVX · Companion phone · future desktop)
    ↓
PBX / Carrier (transport — see voice-runtime-authority.md)
```

**Companion rule:** Healthy identity infrastructure **does not occupy UI**. Status-bar dot only. Identity sheet on tap. **Presence** is toggled separately — it affects routing, not provisioning.

---

### Presence

| | |
|--|--|
| **Verdict** | **Entity authority** — separate from Operator Identity |
| **Source of truth** | Operator availability state (Available, Busy, On call, Driving, Lunch, Offline) |
| **Event producers** | Presence changed, auto-busy from call, manual status |
| **Event consumers** | Routing, dispatch, PTT, transfers, scheduling (future policy) |
| **Timeline** | Operator Feed |
| **Projections** | Quick status toggle, shop-visible availability |
| **Not** | Extension number, device MAC, SIP — those are Identity |

Edward · extension 105 · mobile device = **Identity**. Driving = **Presence**.

---

### Customer

| | |
|--|--|
| **Verdict** | Entity authority |
| **Source of truth** | `customers` — identity, phones, email, consent, address, classification |
| **Event producers** | Customer created/updated; consent changes; portal auth |
| **Event consumers** | Phone, portal, payments, inspections, appointments, RO, search, command palette |
| **Timeline** | **Customer Relationship Timeline** scope — primary Companion anchor for advisors |
| **Workspace** | Customer Workspace **projects** timeline + actions |
| **Projections** | Customers browse index, Customer Hub, workspace layout engine, command palette matches |

```text
Truth: Customer
Projects: Customer Timeline · Customer Workspace · Customers browse · Search/Command
Consumes: Phone · Portal · Payments · Inspections · Appointments · RO events (as timeline entries)
```

**Customers tab is a projection** of Customer authority (identity browse) — **not** duplicate of Comms. Comms recovery is a different projection on Communication events.

---

### Vehicle

| | |
|--|--|
| **Verdict** | Entity authority (**self-owned**) |
| **Source of truth** | `vehicles` — VIN, plate, YMM, identity pressure, history notes |
| **Event producers** | Vehicle linked, identity verified, VIN decode |
| **Event consumers** | RO, inspection, appointments, walk-around, command palette |
| **Timeline** | Vehicle-scoped timeline (RO history, inspections) — subset of customer relationship timeline |
| **Workspace** | Vehicle Workspace — technician-primary |
| **Projections** | VIN scan → check-in, global search vehicle rows |

---

### Repair Order

| | |
|--|--|
| **Verdict** | Entity authority |
| **Source of truth** | `repair_orders`, concerns, lines, lifecycle status, assignments |
| **Event producers** | Status transitions, concern changes, assignment, posted/closed |
| **Event consumers** | Inspection, financial, communication, workboard, technician My Work |
| **Timeline** | **RO Production Timeline** scope |
| **Workspace** | RO Workspace — execution surface |
| **Projections** | Workboard cards, lifecycle select, mobile RO workspace |

---

### Inspection

| | |
|--|--|
| **Verdict** | Entity authority (**self-owned**, RO-scoped) |
| **Source of truth** | `inspections`, `inspection_items`, measurements, finding photos |
| **Event producers** | Finding recorded, item verified, inspection completed |
| **Event consumers** | RO workspace, advisor review, Customer Timeline (summary events only) |
| **Timeline** | RO timeline + optional customer-facing summary events |
| **Workspace** | Finding capture/detail — bodies inside RO/Vehicle workspace |
| **Projections** | Inspection cards, adoption reports, mobile finding queue |

---

### Financial

| | |
|--|--|
| **Verdict** | Entity authority |
| **Source of truth** | Ledger entries, invoice snapshots, `BalanceDueCalculator`, payment attempts |
| **Event producers** | Payment captured, invoice issued, balance change |
| **Event consumers** | RO closeout, portal pay, Customer Timeline, owner reports |
| **Timeline** | Payment / invoice events on Customer + RO timelines |
| **Workspace** | Payment actions on RO / Customer workspace — no Payments app |
| **Projections** | Balance due on workboard, send payment link, Square terminal flow |

---

### Estimate (document authority)

| | |
|--|--|
| **Verdict** | Entity authority (versioned document on RO) |
| **Source of truth** | Estimate lines, snapshots, `EstimateTotalsCalculator`, approval state |
| **Event producers** | Estimate sent, viewed, approved, deferred |
| **Event consumers** | Portal, Customer Timeline, decision pressure |
| **Timeline** | Portal + approval events |
| **Workspace** | Estimate summary blocks — not standalone nav |
| **Projections** | Send estimate link, portal token, decision pressure rows |

---

### Communication (relationship + transport)

Split into **authorities**, not one blob:

| Sub-authority | Verdict | Truth |
|---------------|---------|-------|
| **Conversation** | Entity | `conversations` — relationship thread identity |
| **ConversationMessage** | Event | `conversation_messages` — SMS/MMS/email content |
| **CallSession** | Entity + event lifecycle | `call_sessions` — telephony truth |
| **CommunicationEvent** | Event | Portal viewed, delivery failed, estimate viewed, … |

| | |
|--|--|
| **Event producers** | Inbound/outbound message, call started/ended/missed, portal activity |
| **Event consumers** | Customer Timeline, Recovery Queue, Attention candidates, push |
| **Timeline** | Customer Relationship Timeline (transport entries) |
| **Workspace** | Thread embed inside Customer Timeline — not standalone route |
| **Projections** | Comms hub / recovery tab, Calls Waiting (desktop), inbound overlay |

**Internal comms (future):** separate event stream — couples to **Operator Identity**, never merged into customer `Conversation`.

---

### Appointment

| | |
|--|--|
| **Verdict** | Entity authority |
| **Source of truth** | `appointments` — scheduled time, customer, vehicle, status |
| **Event producers** | Booked, confirmed, arrived, no-show |
| **Event consumers** | Calendar projections, Customer Timeline, intake |
| **Timeline** | Customer + shop day feed |
| **Workspace** | Appointment block on Customer Workspace |
| **Projections** | Schedule tab (advisor), day agenda, "customer arrived" change feed |

---

### Event Stream Engine (infrastructure — not an authority)

| | |
|--|--|
| **Verdict** | **Infrastructure** — same class as workspace layout engines |
| **Source of truth** | **None** — rebuild from authorities + event contracts + scope membership |
| **Role** | Filter and order events into Customer · RO · Vehicle · Operator · Shop views |
| **UI names** | Customer Timeline · Shop Feed · Operator Feed — **projections** of engine output |
| **Cannot** | Originate events · invent "Customer Paid" · replace Financial authority |

See [`ark-scoped-event-streams-v1.md`](../ecosystem/ark-scoped-event-streams-v1.md) · membership [`companion-timeline-scopes-v1.md`](companion-timeline-scopes-v1.md).

---

### Observation

| | |
|--|--|
| **Verdict** | Interpretive layer — consumes **Event Stream Engine** output |
| **Source of truth** | Resolver rules over streams + events — not a parallel store |
| **Engine** | Observation engine reads streams; emits observations |
| **Event producers** | None — observations interpret |
| **Projections** | Feed ranking, Finish Work, attention candidates |

---

### Workspace

| | |
|--|--|
| **Verdict** | Projection |
| **Source of truth** | None — composes authorities |
| **Owns** | Layout, block order, command bar, Finish Work presentation |
| **Types** | Customer Workspace · RO Workspace · Vehicle Workspace |
| **Implementation** | `MobileCustomerWorkspaceProjection`, layout engine, `ArkObjectScaffold` |
| **Rule** | Workspace **composes** — never stores operational facts not elsewhere |

---

### Shop Change Feed ("Home")

| | |
|--|--|
| **Verdict** | Projection |
| **Question answered** | *What changed since I last looked?* |
| **Source of truth** | Current: `users.last_seen_at` (or dedicated continuity cursor) + event authorities |
| **Not** | Module summary, shop pulse dashboard, widget grid |
| **Examples** | Customer replied · Inspection finished · Josh transferred a call · Estimate approved · Customer arrived · New voicemail · Tech blocked |
| **Implementation target** | `MobileOrientationProjection` evolution — feed rows, not cards |
| **Flutter** | `OrientationHomeScreen` presents projection — screen name is not authority |

---

### Recovery Queue ("Comms tab")

| | |
|--|--|
| **Verdict** | Projection |
| **Question answered** | *Which transport events still need handling?* |
| **Source of truth** | `CallSession` (unhandled), unread/read state, conversation attention |
| **Distinct from** | Customers browse, Change Feed |
| **Implementation** | Comms hub — recovery sort, oldest first |

---

### Customers Browse ("Customers tab")

| | |
|--|--|
| **Verdict** | Projection |
| **Question answered** | *Who is this customer?* (identity entry) |
| **Source of truth** | `Customer` authority |
| **Distinct from** | Recovery queue — different intent, same Customer Timeline destination |
| **Keep** | ✅ Approved — entry point, not duplicate authority |

---

### Command Palette

| | |
|--|--|
| **Verdict** | Projection |
| **Question answered** | *What work am I starting?* |
| **Not** | Navigation tab — Spotlight overlay |
| **Source of truth** | Resolver over Customer, Vehicle, RO, Appointment + intent catalog |
| **Examples** | Emma → Call · Text · New RO · Take payment · Find vehicle · Schedule |

---

### Knowledge

| | |
|--|--|
| **Verdict** | Consumed capability |
| **Source of truth** | ARKademy (BookStack) — external |
| **Companion** | Link out via ecosystem switcher — not rebuilt |
| **Projections** | Learning block in shell |

---

### Configuration

| | |
|--|--|
| **Verdict** | Configuration (see `ark-authority-vs-configuration.mdc`) |
| **Source of truth** | `shop_settings`, telephony settings, owner targets |
| **Must not** | Become operational truth — history test fails if settings rewrite past |
| **Projections** | Settings surfaces, theme, business hours |

---

### Shell

| | |
|--|--|
| **Verdict** | Implementation — **last**, not first |
| **Source of truth** | None |
| **Owns** | Tab keys, chrome, role product switch (`advisor` vs `technician`) |
| **Depends on** | This authority model + projection contracts |
| **Document** | `companion-shell-v1.md` — written **after** this doc is approved |

---

## Projection map (same authorities, different lenses)

One event, many projections — **no arguments about which tab "wins"**:

```text
                    ┌─────────────────────┐
                    │   Event authorities  │
                    │ Call · Message · RO  │
                    │ Portal · Payment · … │
                    └──────────┬──────────┘
                               │
              Event Stream Engine (infrastructure)
                               │
        ┌──────────────────────┼──────────────────────┐
        ▼                      ▼                      ▼
 Shop Change Feed      Recovery Queue        Customers Browse
 ("Home")             ("Comms")              ("Customers")
        │                      │                      │
        └──────────────────────┼──────────────────────┘
                               ▼
                    Customer Timeline (scope)
                               │
                               ▼
                    Customer Workspace (layout)
                               │
                               ▼
                         Flutter screen
```

---

## Advisor vs Technician — authority visibility

Not nav profiles — **different projection sets on different authorities**:

| Authority / Projection | Advisor Companion | Technician Companion |
|------------------------|:-----------------:|:--------------------:|
| Shop Change Feed | ✅ | minimal (blocked, parts) |
| Recovery Queue | ✅ shop-wide | scoped to assigned RO |
| Customers Browse | ✅ | ❌ **never** |
| Customer Timeline | ✅ primary | via RO only |
| Vehicle Workspace | ✅ | ✅ **primary** |
| RO Workspace | ✅ | ✅ |
| Inspection | view + approve | ✅ **primary** |
| Command Palette | ✅ full | scoped (vehicle, RO, photo) |
| Operator Identity | ✅ | ✅ |
| Financial actions | ✅ | ❌ |
| Knowledge | optional | optional |

**Failure test:** Technician opens Customers browse → product failure.

---

## Existing code alignment

| Authority / projection | Code anchor |
|------------------------|-------------|
| Customer | `App\Ark\Operations\Customers\Customer` |
| Vehicle | `App\Ark\Operations\Vehicles\Vehicle` |
| Repair Order | `App\Ark\Operations\RepairOrders\RepairOrder` |
| Inspection | `App\Ark\Operations\Inspections\Inspection` |
| Financial | `BalanceDueCalculator`, ledger, payments namespace |
| Call | `CallSession` |
| Message | `ConversationMessage` |
| Comm fact | `CommunicationEvent` |
| Operational fact | `OperationalEvent` |
| Timeline organize | `UnifiedOperationalTimeline`, `OperationalEventEntry` |
| Customer timeline projection | `CustomerHubCommsTimeline`, mobile workspace projection |
| RO timeline projection | `OperationalTimeline` |
| Workspace projection | `MobileCustomerWorkspaceProjection`, layout engine |
| Shell (today) | `MobileUserPresenter` — **needs authority-aware redesign** |
| Truth stack doctrine | `docs/ecosystem/ark-truth-stack-v1.md` |

---

## Open questions (Event Architecture Sprint)

Defer implementation — record for next convergence:

1. **Shop Change Feed cursor** — `last_seen_at` vs dedicated `operator_continuity_cursors` table
2. **Vehicle timeline** — separate scope vs filtered customer timeline for technicians
3. **Internal event stream** — schema reservation for PTT, transfer, dispatch
4. **Observation → feed ranking** — which events surface on Change Feed vs timeline only
5. **Desktop parity** — same scopes, different organization posture (browse vs react)

---

## Approval record (2026-07-04)

| Decision | Verdict |
|----------|---------|
| Event Stream Engine = infrastructure; events owned by authorities | ✅ |
| Scopes: Customer · RO · Vehicle · Operator · Shop Change Feed | ✅ |
| Financial = separate authority; projected onto customer timelines | ✅ |
| Home = Shop Change Feed projection | ✅ |
| Customers = browse entry projection — not competing workspace | ✅ |
| Next sprint = Event Architecture — event contracts first | ✅ Signed — [`event-contracts-v1.md`](event-contracts-v1.md) |

**Sequence:** Event Architecture → `companion-shell-v1.md` → Flutter.

---

## Appendix — Doctrine card

```text
Screens never own truth.
Authorities own truth.
Events move truth.
Timelines organize truth.
Projections answer operator questions.
Screens present projections.

Desktop organizes information.
Mobile responds to events.
```
