# ARK Business Language v1

**Status:** Vocabulary registry — the dictionary. **Not** a new architecture layer.  
**Purpose:** When someone proposes a name, ask: **authority · event · observation · or projection?**  
**Companions:** [`event-contracts-v1.md`](../mobile/event-contracts-v1.md) · [`companion-authority-model-v1.md`](../mobile/companion-authority-model-v1.md) · [`ark-authority-interaction-map-v1.md`](ark-authority-interaction-map-v1.md).

**Do not add vocabulary here without updating event contracts or observation vocabulary.** This file **indexes** — it does not invent.

---

## Doctrine (locked)

> **Events describe what happened. Observations describe what it means. Projections never invent events.**

> **Implementation conforms to the business language. Never the reverse.**

| Layer | May | Must not |
|-------|-----|----------|
| **Event** | Record business fact from authority | Be synthesized by UI or invented in code |
| **Observation** | Interpret events on a stream | Replace or invent events |
| **Projection** | Compose and present | Author history (e.g. "Customer Paid" when only Payment Received occurred) |
| **Implementation** | Emit signed contract verbs | Introduce `CustomerPaymentCompleted` when language says **Payment Received** |

**Litmus:** *"Let's create VehicleNotification"* → Is that an authority? An event verb? An observation? A projection label? **Pick one.**

---

## Authorities (nouns — who may say this is true)

| Term | Authoritative question |
|------|------------------------|
| **Customer** | Who is this person / relationship? |
| **Vehicle** | What is this vehicle? |
| **Repair Order** | What work was authorized and executed? |
| **Inspection** | What was found on the vehicle? |
| **Financial** | What money changed hands? |
| **Conversation** | What relationship thread exists? |
| **Message** | What was said (content)? |
| **Call** | What call occurred? |
| **Communication fact** | What portal / estimate / delivery fact occurred? |
| **Appointment** | What was scheduled and did arrival happen? |
| **Operator** | Who is this staff person? |
| **Operator Identity** | What extension / device / station? |
| **Presence** | Are they reachable right now? |
| **Shop** | Tenant boundary · configuration (not operational history) |

**Not authorities:** Event Stream Engine · Timeline · Workspace · Feed · Dashboard.

---

## Event verbs (facts — past tense business language)

Each authority owns its verbs. **Never use authority names as event names.**

### Repair Order

RO Created · RO Started · Waiting Approval · RO Approved · RO Ready · RO Closed · Technician Blocked · Part Backordered

### Inspection

Inspection Started · Finding Added · Inspection Completed · Inspection Published

### Message & call

Message Sent · Message Received · Call Started · Call Answered · Call Missed · Call Completed · Voicemail Received · Call Transferred

### Financial

Payment Requested · Payment Received · Refund Issued · Balance Due

### Estimate & portal (communication facts)

Estimate Sent · Estimate Viewed · Estimate Approved · Estimate Deferred

### Appointment

Appointment Scheduled · Customer Arrived · Appointment No-Show

### Vehicle

Vehicle Checked In · VIN Verified

### Operator Identity (reserved)

Extension Registered · Device Attached · Call Moved

### Presence (reserved)

Presence Changed · On Call · Available

---

## Observations (interpretive — what it means)

Observations **consume streams** of events. They are not events.

| Term | Interprets (examples) |
|------|------------------------|
| **Customer Waiting** | Waiting posture events without resolution |
| **Customer Engaged** | Repeated estimate views · no reply |
| **Approval Aging** | Waiting approval beyond shop threshold |
| **Technician Blocked** | Blocking events on RO stream (may pair with Technician Blocked event) |
| **Vehicle Idle** | RO waiting · no production progress |
| **Estimate Viewed Multiple Times** | Multiple Estimate Viewed events (existing vocabulary) |

**Wrong:** Observation named "Customer Paid" — payment is **Payment Received** (event).

---

## Projections (operator questions — compose, never author)

| Term | Question answered | Consumes |
|------|-------------------|----------|
| **Shop Feed** | What changed since I last looked? | Shop stream slice |
| **Customer Timeline** | What happened with this customer? | Customer stream (UI label) |
| **RO Timeline** | What happened on this RO? | RO stream |
| **Vehicle Timeline** | What happened to this vehicle? | Vehicle stream |
| **Operator Feed** | What happened for this operator? | Operator stream |
| **Recovery Queue** | Which transport events need handling? | Action filter on streams |
| **Customers Browse** | Who is this customer? | Customer authority + latest stream head |
| **Command Palette** | What work am I starting? | Intent resolver |
| **Workboard** | What ROs need shop attention? | RO authority + observations |
| **Dashboard** | (desktop) Organize streams for review | Multiple streams — **not** new events |

**Wrong projection:** UI generates "Customer Paid" row without **Payment Received** event.

**Right projection:** Customer Timeline renders **Payment Received** from Financial authority.

---

## Infrastructure (not vocabulary entries)

These organize truth — they do **not** originate truth.

| Term | Role |
|------|------|
| **Event Stream Engine** | Filters event contracts into scoped streams by membership + anchor |
| **Observation Engine** | Interprets streams → observations |
| **Timeline (UI)** | Chronological presentation of a stream |

---

## Stack (reference)

```text
Truth
    ↓
Authorities
    ↓
Events
    ↓
Event Stream Engine
    ↓
Observations
    ↓
Projections
    ↓
Surfaces
```

---

## Adding new terms (gate)

Before adding any row:

1. Classify: authority · event · observation · projection · infrastructure  
2. If event — add to [`event-contracts-v1.md`](../mobile/event-contracts-v1.md) with eight questions  
3. If observation — must cite source events; never invent facts  
4. If projection — must cite streams/authorities; **projections never invent events**  
5. Update scope membership mechanically in [`companion-timeline-scopes-v1.md`](../mobile/companion-timeline-scopes-v1.md)  
6. Update this registry index  

**Stop adding architecture documents** unless implementation proves the model cannot express reality. **E1 Contract Realization** is next — spreadsheet boring, not design debate.

---

## Rejected examples

| Proposal | Verdict | Why |
|----------|---------|-----|
| **VehicleNotification** | Reject unless classified | Likely projection label or observation — not a new authority |
| **Customer Paid** (projection-only) | Reject | Invented event — use **Payment Received** |
| **`CustomerPaymentCompleted`** (code) | Reject | Implementation invented vocabulary — use **Payment Received** |
| **OperationalEventType** (147 values) | Reject | Mega-enum — kills per-authority vocabulary |
| **Event Stream** (authority) | Reject | Infrastructure — cannot originate truth |
