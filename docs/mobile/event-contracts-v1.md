# ARK Event Contracts v1

**Status:** **Signed** (Edward, 2026-07-04) — foundational ARK document.  
**Prerequisite:** [`companion-authority-model-v1.md`](companion-authority-model-v1.md) — approved.

This document defines **ARK's business language**. It intentionally contains **no database schema, API contracts, mobile widgets, server models, transport protocols, or storage decisions**. Those are implementations of this language — not the language itself.

Six months from now this document may matter more than any single app surface. Once the event language is right, the Companion, desktop, AI, notifications, reporting, automation, voice, and analytics are all **projections of the same business facts** — not parallel vocabularies.

**Next (E1):** [`contract-realization-register-v1.md`](contract-realization-register-v1.md) — vertical slice per companion-critical verb. Architecture closed.

---

## Platform doctrine (locked)

> **Screens never own truth. Authorities own truth. Events move truth. Timelines organize truth. Projections answer operator questions. Screens present projections.**

> **Events describe what happened. Observations describe what it means. Projections never invent events.**

Example: **Estimate viewed** — event. *Customer appears engaged* — observation. Fundamentally different. Observations never invent truth; AI must not skip the event layer.

> **Desktop organizes information. Mobile responds to events.**

### Architecture stack

```text
Truth
    ↓
Authorities
    ↓
Events                   ← this document (verbs)
    ↓
Event Stream Engine      ← infrastructure — organizes by scope; see ark-scoped-event-streams-v1.md
    ↓
Observations             ← what it means
    ↓
Projections              ← compose; never invent events
    ↓
Surfaces
```

**Timeline** is UI for engine output — not an authority. See [`ark-scoped-event-streams-v1.md`](../ecosystem/ark-scoped-event-streams-v1.md).

### Ordering (do not invert)

```text
Authority → Event Contract → Scope Membership → Scoped Event Stream → Observation → Projection → Surface
```

See [`ark-authority-interaction-map-v1.md`](../ecosystem/ark-authority-interaction-map-v1.md) for how authorities relate without owning each other.

---

## Language-first rule

**Event Contracts must be understandable without knowing any implementation language or framework.**

Contracts use business vocabulary only. Implementation mapping lives in a **separate, non-normative** appendix at the end of this document — never in the contract definitions themselves.

Never create a platform-wide event type enum with 147 values. **Each authority owns its vocabulary.**

**Contracts are verbs. Authorities are nouns.**

| Verbs (events) | Nouns (authorities — never event names) |
|----------------|----------------------------------------|
| Inspection Completed · Finding Added | Inspection |
| Payment Received · Payment Requested | Financial |
| Message Received · Call Missed | Message · Call |
| Estimate Sent · Estimate Viewed | Estimate / portal facts |

**Bad event name:** Payment · Conversation · Inspection — those are domains, not facts.

---

## What an event contract is

An **event contract** is the product definition of a business fact — the sentence an advisor would say on the floor:

*"Customer replied."* · *"Inspection completed."* · *"Payment received."*

- Not a table row shape  
- Not a server enum entry  
- Not a UI row type  

---

## The eight questions (every event, exactly eight)

Every event contract must answer these — not twenty, not a schema dump.

### 1. Where is this truth authoritative?

**Domain** — where the fact lives if all projections are deleted.

Subtle but important: a payment is **authoritative in the Financial domain**. The Financial authority owns it; the wording scales when authorities distribute later.

| Domain | Authoritative facts |
|--------|---------------------|
| Customer | Person, phones, consent, classification |
| Vehicle | VIN, YMM, plate, identity |
| Repair Order | Workflow, concerns, lines, lifecycle |
| Inspection | Findings, measurements, photos |
| Financial | Ledger, invoice, payment, balance |
| Conversation | Relationship thread |
| Call | Call lifecycle |
| Message | What was said |
| Communication fact | Portal views, estimate sends, delivery facts |
| Appointment | Scheduled arrival |
| Operator Identity | Extension, device, station assignment |
| **Presence** | Availability state — **separate from identity** |
| Audit | Compliance-only facts |

One contract → one **primary** authoritative domain. Links to customer, vehicle, RO are anchors — not ownership.

---

### 2. What happened?

**Business language** — not implementation identifiers.

| Wrong | Right |
|-------|-------|
| `conversation_message_received` | **Message received** |
| `repair_order_lifecycle_changed` | **Repair order started** |
| `CallSessionStatus::Missed` | **Call missed** |
| `EstimateViewed` (enum) | **Estimate viewed** |

---

### 3. Who should observe this?

**Observers** — who or what surfaces should see this event. Not emotional interest; not "subscribers."

| Observer | Role |
|----------|------|
| **Advisor** | Human role |
| **Technician** | Human role |
| **Manager** | Human role |
| **Operator** | The acting staff member |
| **Customer Timeline** | Scoped stream |
| **Repair Order Timeline** | Scoped stream |
| **Vehicle Timeline** | Scoped stream |
| **Shop Feed** | Cross-customer change projection |
| **Operator Feed** | Identity / internal stream |
| **Audit Only** | Never operator UI |

**Example — Message received**

- Observed by: **Advisor** · **Customer Timeline** · **Shop Feed**

**Example — Inspection completed**

- Observed by: **Advisor** · **Technician** · **Customer Timeline** · **Shop Feed**

---

### 4. Where should it appear?

**Stream scopes** — not routes, not tabs. Membership defines which **scoped event stream** includes this contract.

| Scope | Stream |
|-------|--------|
| **Customer Timeline** | Customer Stream |
| **Repair Order Timeline** | RO Stream |
| **Vehicle Timeline** | Vehicle Stream |
| **Operator Feed** | Operator Stream |
| **Shop Feed** | Shop Stream |
| **Audit Only** | None (operator UI) |

"Timeline" in operator UI = chronological **projection** of a stream. Architecturally: **scoped event stream**.

**Mechanical rules (derived later):**

```text
Customer Timeline = contracts WHERE Customer Timeline ∈ scopes ORDER BY time
Shop Feed         = contracts WHERE Shop Feed ∈ scopes AND active
Recovery Queue    = contracts WHERE action ∈ {Action Required, Blocking} AND transport domain AND active
```

**Shop Feed is a projection** — not an authority.

---

### 5. What is the action posture?

| Posture | Meaning | Example |
|---------|---------|---------|
| **None** | History only | Call completed |
| **Informational** | Awareness; no operational wait | Vehicle checked in |
| **Waiting** | Waiting on someone or something — **most common shop state** | Estimate sent · waiting on customer |
| **Action Required** | Operator should act now | Customer replied · voicemail received |
| **Blocking** | Work cannot proceed | Technician blocked · part backordered |
| **Completed** | Deliberate close; often supersedes a prior event | Payment received · estimate approved |

**Waiting vs Informational:** Estimate sent is not merely informational — the shop is **waiting** on the customer. Waiting belongs on Shop Feed and decision surfaces until superseded.

**Recovery Queue** (transport recovery only):

```text
action ∈ {Action Required, Blocking} AND NOT superseded
```

Waiting customer decisions surface on **Shop Feed** and **observation-driven** emphasis — not the transport recovery queue.

---

### 6. Does it expire?

When does this event leave **active** feeds?

| Expiry rule | Example |
|-------------|---------|
| **When handled** | Customer replied → expires when advisor replies |
| **When superseded** | See supersession graph (Q8) |
| **When terminal** | Waiting approval → expires when approved or lost |
| **Never** | Payment received · inspection completed — permanent history |

Expiry prevents feed garbage. Truth is never deleted — only active surfacing ends.

---

### 7. Supersession graph

Lifecycle is a **directed graph** — queryable, not a bullet list.

Each contract may declare:

- **supersedes →** prior contract(s) this event closes on active feeds  
- **superseded by →** successor contract(s)

**Example — estimate decision chain**

```text
Estimate Sent
    supersedes → (none — opens waiting)
    superseded by → Waiting Approval

Waiting Approval
    supersedes → Estimate Sent
    superseded by → Estimate Viewed | Estimate Approved | Estimate Deferred

Estimate Viewed
    supersedes → (none on first view)
    superseded by → Estimate Approved

Estimate Approved
    supersedes → Waiting Approval · Estimate Sent (waiting posture)
    superseded by → (terminal)
```

**Example — call chain**

```text
Call Started
    superseded by → Call Answered | Call Missed

Call Answered
    supersedes → Call Started
    superseded by → Call Completed

Call Completed
    supersedes → Call Answered
    cause → Remote hangup | Operator ended | Transfer completed
```

When B supersedes A, A **drops from active feeds** but remains in timeline history.

---

### 8. What caused this?

**Causation** — what business or physical fact produced this event.

| Event | Cause (examples) |
|-------|------------------|
| **Payment received** | Customer payment · Card captured · Portal pay |
| **Repair order approved** | Estimate approved |
| **Call ended** | Remote hangup · Operator ended · Transfer completed |
| **Inspection completed** | Technician submitted inspection |
| **Part backordered** | Vendor delay · Part unavailable |
| **Presence changed** | Operator selected Lunch · Auto on-call from answered call |

Causation powers analytics, observations, root-cause review, and future AI — **without** AI inventing events. Causes reference other contracts or named external facts — not stack traces.

---

## Presence — separate from Identity

**Operator Identity** and **Presence** are different authoritative domains.

| | **Identity** | **Presence** |
|--|--------------|--------------|
| **Truth** | Edward · Extension 105 · Desk phone · Mobile device | Available · Busy · On call · Driving · Lunch · Offline |
| **Question** | *Who is this operator and where is their line?* | *Are they reachable right now?* |
| **Affects (future)** | Provisioning · extension mapping | Routing · dispatch · PTT · transfers · scheduling |

**Presence Changed** is a Presence-domain contract — not an Identity contract.

Identity: *Edward, extension 105, mobile device.*  
Presence: *Driving.*

---

## Authority event vocabularies (v1 catalog)

Each authority owns its language. Tables use business fields only.

**Columns:** Contract · Authoritative domain · Observers · Scopes · Posture · Expires · Cause (examples)

---

### Repair Order

| Contract | Domain | Observers | Scopes | Posture | Expires |
|----------|--------|-----------|--------|---------|---------|
| **RO Created** | Repair Order | Advisor · Customer Timeline · Shop Feed | Customer · RO · Shop | Informational | Never |
| **RO Started** | Repair Order | Technician · RO Timeline · Shop Feed | RO · Shop | Informational | Never |
| **Waiting Approval** | Repair Order | Advisor · Customer Timeline · Shop Feed | Customer · RO · Shop | **Waiting** | When approved/deferred/lost |
| **RO Approved** | Repair Order | Advisor · Customer Timeline · Shop Feed | Customer · RO · Shop | Completed | Never |
| **RO Ready** | Repair Order | Advisor · Customer Timeline · Shop Feed | Customer · RO · Shop | Action Required | When picked up |
| **RO Closed** | Repair Order | Advisor · Customer Timeline | Customer · RO | None | Never |
| **Technician Blocked** | Repair Order | Technician · Advisor · Shop Feed | RO · Shop | Blocking | When unblocked |
| **Part Backordered** | Repair Order | Technician · Advisor · Shop Feed | RO · Shop | Blocking | When received/canceled |

**Supersession (selected):** Waiting Approval superseded by Estimate Approved · Estimate Deferred · RO Closed (lost).

---

### Inspection

| Contract | Domain | Observers | Scopes | Posture | Expires |
|----------|--------|-----------|--------|---------|---------|
| **Inspection Started** | Inspection | Technician · RO Timeline | RO · Vehicle | Informational | Never |
| **Finding Added** | Inspection | Technician · RO Timeline | RO · Vehicle | Informational | Never |
| **Inspection Completed** | Inspection | Advisor · Technician · Customer Timeline · Shop Feed | Customer · RO · Vehicle · Shop | Action Required | When reviewed |
| **Inspection Published** | Inspection | Advisor · Customer Timeline | Customer · RO · Shop | Completed | Never |

**Cause examples:** Inspection completed ← technician submitted · finding added ← technician recorded.

---

### Message & call

| Contract | Domain | Observers | Scopes | Posture | Expires |
|----------|--------|-----------|--------|---------|---------|
| **Message Sent** | Message | Customer Timeline · RO Timeline | Customer · RO | None | Never |
| **Message Received** | Message | Advisor · Customer Timeline · Shop Feed | Customer · RO · Shop | Action Required | When replied/handled |
| **Call Started** | Call | Advisor · Shop Feed | Customer · Shop | Action Required (inbound) | When answered/missed |
| **Call Answered** | Call | Advisor · Customer Timeline | Customer | Completed | Never |
| **Call Missed** | Call | Advisor · Customer Timeline · Shop Feed | Customer · Shop | Action Required | When handled |
| **Call Completed** | Call | Customer Timeline | Customer | None | Never |
| **Voicemail Received** | Call | Advisor · Customer Timeline · Shop Feed | Customer · Shop | Action Required | When handled |
| **Call Transferred** | Call | Operator · Shop Feed | Operator · Customer · Shop | Informational | Never |

**Cause examples:** Call completed ← remote hangup · operator ended · transfer completed.

---

### Financial

| Contract | Domain | Observers | Scopes | Posture | Expires |
|----------|--------|-----------|--------|---------|---------|
| **Payment Requested** | Financial | Advisor · Customer Timeline · RO Timeline | Customer · RO | **Waiting** | When paid |
| **Payment Received** | Financial | Advisor · Customer Timeline · Shop Feed | Customer · RO · Shop | Completed | Never |
| **Refund Issued** | Financial | Advisor · Audit | Customer · RO · Audit | Informational | Never |
| **Balance Due** | Financial | Advisor · Customer Timeline | Customer · RO | **Waiting** | When paid |

**Rule:** Truth is authoritative in **Financial** domain. Customer Timeline **observes** — Customer does not own payment truth.

**Cause examples:** Payment received ← customer portal pay · terminal capture · card keyed.

---

### Estimate & portal

| Contract | Domain | Observers | Scopes | Posture | Expires |
|----------|--------|-----------|--------|---------|---------|
| **Estimate Sent** | Communication fact | Advisor · Customer Timeline · Shop Feed | Customer · RO · Shop | **Waiting** | When superseded |
| **Estimate Viewed** | Communication fact | Advisor · Customer Timeline · Shop Feed | Customer · RO · Shop | Informational | When approved |
| **Estimate Approved** | Communication fact | Advisor · Customer Timeline · Shop Feed | Customer · RO · Shop | Completed | Never |
| **Estimate Deferred** | Communication fact | Advisor · Customer Timeline | Customer · RO | Completed | Never |

See **supersession graph** (Q7) for full estimate chain.

**Observation (not event):** Estimate viewed 4× in 2 hours → *Customer appears engaged* — observation on repeated **Estimate viewed** events.

---

### Appointment

| Contract | Domain | Observers | Scopes | Posture | Expires |
|----------|--------|-----------|--------|---------|---------|
| **Appointment Scheduled** | Appointment | Advisor · Shop Feed | Customer · Shop | Informational | Never |
| **Customer Arrived** | Appointment | Advisor · Shop Feed | Customer · RO · Shop | Action Required | When checked in |
| **Appointment No-Show** | Appointment | Advisor · Shop Feed | Customer · Shop | Action Required | When rescheduled |

---

### Vehicle & intake

| Contract | Domain | Observers | Scopes | Posture | Expires |
|----------|--------|-----------|--------|---------|---------|
| **Vehicle Checked In** | Vehicle | Advisor · Shop Feed | Customer · RO · Vehicle · Shop | Informational | Never |
| **VIN Verified** | Vehicle | Advisor · RO Timeline | Vehicle · RO | Informational | Never |

---

### Operator Identity (reserved v1)

| Contract | Domain | Observers | Scopes | Posture |
|----------|--------|-----------|--------|---------|
| **Extension Registered** | Operator Identity | Operator · Audit | Operator · Audit | None |
| **Device Attached** | Operator Identity | Operator · Audit | Operator · Audit | None |
| **Call Moved** | Operator Identity | Operator · Shop Feed | Operator · Shop | Informational |

---

### Presence (reserved v1)

| Contract | Domain | Observers | Scopes | Posture | Cause examples |
|----------|--------|-----------|--------|---------|----------------|
| **Presence Changed** | **Presence** | Operator · Shop Feed | Operator · Shop | Informational | Operator selected status · Auto busy from on-call |
| **On Call** | Presence | Operator · Shop Feed | Operator · Shop | Informational | Call answered |
| **Available** | Presence | Operator · Shop Feed | Operator · Shop | Informational | Call completed · Manual clear |

Presence affects routing, dispatch, PTT, transfers, scheduling — **future policy consumes Presence domain truth**, not Identity.

---

## From contracts to projections (mechanical)

### Shop Change Feed (projection)

*What changed since I last looked?*

```text
contracts WHERE Shop Feed ∈ scopes
          AND occurred_at > operator_cursor
          AND active (not expired · not superseded)
ORDER BY occurred_at DESC
```

Includes **Waiting** and **Action Required** — not widgets.

### Recovery Queue (projection)

*Which transport events need handling?*

```text
contracts WHERE action ∈ {Action Required, Blocking}
          AND authoritative domain ∈ {Call · Message · Communication fact (transport)}
          AND active
ORDER BY occurred_at ASC
```

### Customer Timeline (projection)

```text
contracts WHERE Customer Timeline ∈ scopes AND customer = anchor ORDER BY time
```

### Customers browse (projection)

Customer identity + latest Customer Timeline contract + open RO hint. Entry point — not a competing timeline.

---

## Observations consume streams

```text
Event        — Estimate viewed (fact)
Stream       — Customer Stream (scoped sequence)
Observation  — Customer appears engaged (meaning — observation engine on stream)
Projection   — Finish Work: Call Jason (action)
```

**Wrong:** Observation reads raw tables or AI summarizes the customer record.  
**Right:** Observation engine **interprets scoped event streams.**

Pressure First · Repeated Questions · Observations · Communications · **Events** · **Streams** — one stack.

---

## v1 boundaries

**In catalog:** RO · Inspection · Message · Call · Financial · Estimate · Appointment · Vehicle · Identity (reserved) · **Presence (reserved)**

**Out of v1:** Growth/marketing events · full PTT vocabulary · AI-generated events · platform mega-enums

**Companion-critical (implement first):** Message received · Call missed · Voicemail · Estimate sent/viewed/approved · Payment requested/received · Inspection completed · Customer arrived · Technician blocked · Waiting approval

---

## Acceptance (signed)

| # | Criterion |
|---|-----------|
| 1 | Every v1 contract answers exactly **eight** questions |
| 2 | Q1: authoritative **domain** wording |
| 3 | Q3: **observers** — not "who cares" |
| 4 | **Waiting** posture for estimate sent, payment requested, waiting approval |
| 5 | Q8: **causation** on every contract |
| 6 | Supersession as **graph** (supersedes / superseded by) |
| 7 | **Presence** separate from Identity |
| 8 | No implementation language in contract definitions |
| 9 | Shop Feed = projection |
| 10 | Events vs observations doctrine locked |

---

## Next documents

| Doc | Role |
|-----|------|
| **This doc** | Business language — **signed** |
| [`companion-timeline-scopes-v1.md`](companion-timeline-scopes-v1.md) | Scope membership — **E0b ✅** |
| [`ark-business-language-v1.md`](../ecosystem/ark-business-language-v1.md) | Dictionary |
| `companion-event-api-v1.md` | Implementation API — **E2** |
| `companion-shell-v1.md` | After E2 |

---

## Doctrine card

```text
Truth → Authorities → Events → Event Stream Engine → Observations → Projections → Surfaces

Screens never own truth.
Events describe what happened.
Observations describe what it means.
Projections never invent events.
Desktop organizes information.
Mobile responds to events.
```

---

## Appendix A — Implementation map (non-normative)

**Not part of the business language.** For engineers migrating existing code — contracts above are authoritative if this appendix drifts.

| Existing artifact | Maps toward |
|-------------------|-------------|
| Unified timeline composer | Projects contracts into scoped streams |
| Operational event entry DTO | Projection transport shape — add contract id + eight answers in E1 |
| Per-source mappers (call, message, comm fact) | Emit named contracts |
| Legacy kind enum (18 transport hints) | Not product vocabulary — shrink over time |
| Legacy name enum (40+ cases) | Migrate to per-authority contract IDs |
| Observation resolver | Sits above events — never replaces them |

Implementation work begins in **E1 Contract Realization** — one vertical slice per contract (implementation → projection → observation). Mappers are implementation detail; contracts are the product. This appendix is not required reading for product or floor vocabulary.
