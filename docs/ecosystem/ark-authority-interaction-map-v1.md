# ARK Authority Interaction Map v1

**Status:** E0.5 — **signed architecture** (relationships, not tables).  
**Prerequisite:** [`event-contracts-v1.md`](../mobile/event-contracts-v1.md) · [`companion-authority-model-v1.md`](../mobile/companion-authority-model-v1.md).  
**Next:** [`ark-scoped-event-streams-v1.md`](ark-scoped-event-streams-v1.md).

**Question this doc answers:**

> How do authorities influence each other **without owning** each other?

No implementation. No storage. No UI. Relationship verbs only.

---

## Relationship verbs

| Verb | Meaning |
|------|---------|
| **owns** | Authoritative truth for this domain |
| **references** | Points at another authority — foreign key, not copy |
| **produces** | Emits event contracts |
| **observes** | Receives events from another domain (read-only) |
| **projects** | Surfaces another authority's facts without owning them |
| **scopes** | Filters events into a stream anchor |
| **contains** | Parent-child **entity** containment (RO contains lines — not "RO owns inspection truth") |
| **never owns** | Explicit boundary |

---

## Customer

```text
Customer
    │
    ├── owns ──────────────► identity · phones · consent · classification
    │
    ├── references ────────► Vehicle(s)           (default owner link)
    │
    ├── observes ──────────► Conversation events · Call events · Portal events
    │
    ├── projects ──────────► Financial facts       (payments authoritative in Financial domain)
    │
    ├── references ────────► Repair Order(s)       (open & historical)
    │
    ├── scopes ────────────► Customer Stream
    │
    └── never owns ────────► Vehicle identity · RO workflow · Inspection findings · Payments
```

**Influence without ownership:** Customer timeline **shows** payment received; Financial authority **owns** it.

---

## Vehicle

```text
Vehicle
    │
    ├── owns ──────────────► VIN · YMM · plate · vehicle identity · history notes
    │
    ├── references ────────► Customer              (relationship — vehicle can move)
    │
    ├── contains ──────────► Repair Order history  (via RO.reference → vehicle)
    │
    ├── produces ──────────► Vehicle Checked In · VIN Verified
    │
    ├── scopes ────────────► Vehicle Stream
    │
    └── never owns ────────► Customer · RO lifecycle · Inspection · Financial
```

Technician Companion orients on **Vehicle Stream**; advisor orients on **Customer Stream**. Same events may appear in both scopes — different anchors.

---

## Repair Order

```text
Repair Order
    │
    ├── owns ──────────────► workflow · concerns · lines · lifecycle · assignments
    │
    ├── references ────────► Customer · Vehicle
    │
    ├── references ────────► Inspection         (RO-scoped — Inspection owns findings)
    │
    ├── references ────────► Financial          (ledger anchored on RO)
    │
    ├── produces ──────────► RO Created · Waiting Approval · Technician Blocked · Part Backordered · …
    │
    ├── observes ──────────► Estimate events · Approval events · Payment events (projected)
    │
    ├── scopes ────────────► RO Stream
    │
    └── never owns ────────► Customer identity · Vehicle identity · Inspection truth · Payment truth
```

**Contains vs owns:** RO **contains** concern lines. RO **references** Inspection — findings live in Inspection authority.

---

## Inspection

```text
Inspection
    │
    ├── owns ──────────────► findings · measurements · photos · checklist state
    │
    ├── references ────────► Repair Order · Vehicle
    │
    ├── produces ──────────► Inspection Started · Finding Added · Inspection Completed · …
    │
    ├── scopes ────────────► RO Stream · Vehicle Stream (technician lens)
    │
    └── never owns ────────► RO lifecycle · Customer · Financial
```

---

## Financial

```text
Financial
    │
    ├── owns ──────────────► ledger · invoice · balance · payment attempts · refunds
    │
    ├── references ────────► Repair Order        (anchor)
    │
    ├── projects ──────────► Customer Stream · RO Stream   (read surfaces)
    │
    ├── produces ──────────► Payment Requested · Payment Received · Refund Issued · …
    │
    └── never owns ────────► Customer · RO workflow · Estimate lines
```

Payment truth is **authoritative in Financial domain** — projected onto Customer Stream for relationship context.

---

## Conversation · Message · Call

```text
Conversation
    ├── owns ──────────────► thread identity · participants
    ├── references ────────► Customer (relationship)
    └── never owns ────────► message content (Message authority)

Message
    ├── owns ──────────────► what was said · attachments
    ├── references ────────► Conversation · Customer · RO (context links)
    └── produces ──────────► Message Sent · Message Received

Call
    ├── owns ──────────────► call lifecycle · voicemail · recording metadata
    ├── references ────────► Customer · Operator · RO (when known)
    └── produces ──────────► Call Started · Call Missed · Voicemail Received · …
```

Communication **produces** events — does not organize the shop. Streams organize.

---

## Appointment

```text
Appointment
    ├── owns ──────────────► scheduled time · status · arrival facts
    ├── references ────────► Customer · Vehicle
    ├── produces ──────────► Appointment Scheduled · Customer Arrived · No-Show
    └── scopes ────────────► Customer Stream · Shop Stream
```

---

## Operator · Identity · Presence

```text
Operator (User)
    ├── owns ──────────────► person · roles · permissions
    └── references ────────► Identity · Presence

Operator Identity
    ├── owns ──────────────► extension · device · station assignment
    ├── references ────────► Operator
    ├── produces ──────────► Extension Registered · Call Moved · …
    └── scopes ────────────► Operator Stream (identity events)

Presence
    ├── owns ──────────────► availability state (Available · Busy · On Call · Driving · Lunch)
    ├── references ────────► Operator
    ├── produces ──────────► Presence Changed · On Call · Available
    ├── influences ────────► routing · dispatch · PTT · transfers   (future policy — does not own calls)
    └── never owns ────────► Identity · Customer · RO
```

Edward · extension 105 = **Identity**. Driving = **Presence**. Separate truths.

---

## Shop (tenant boundary)

```text
Shop
    ├── owns ──────────────► tenant · configuration (behavior — not operational history)
    ├── scopes ────────────► Shop Stream          (cross-customer delta for an operator)
    └── never owns ────────► customer facts · events (events remain in domain authorities)
```

Shop Stream is a **projection scope** — not a sixth event store.

---

## Cross-authority patterns (repeat everywhere)

| Pattern | Example |
|---------|---------|
| **Reference, don't copy** | RO references Vehicle — does not store YMM as truth |
| **Project, don't own** | Customer Stream shows Payment Received — Financial owns it |
| **Produce events, not UI** | Inspection produces Inspection Completed — screens consume streams |
| **Scope, don't fork** | Same event in Customer Stream and RO Stream — one contract, two filters |
| **Observe streams, not tables** | Observation engine reads Customer Stream — not 12 JOINs |

---

## What this prevents

| Anti-pattern | Why wrong |
|--------------|-----------|
| Customer owns payments | Financial history test fails |
| RO owns inspection findings | Inspection authority collapse |
| Comms owns the OS | Events come from many producers |
| Timeline table as truth | Streams rebuild from events |
| `OperationalEventType` mega-enum | Authorities lose vocabulary |
| Event contract named "Payment" | Noun — authority name, not verb |

---

## Acceptance (E0.5)

| # | Criterion |
|---|-----------|
| 1 | Every major authority has owns / references / produces / scopes / never owns |
| 2 | Financial → Customer relationship is **project**, not **own** |
| 3 | Inspection separate from RO **own** |
| 4 | Presence separate from Identity |
| 5 | No implementation language in body |
| 6 | Sets up Scoped Event Streams (E0.6) without defining storage |

**Signed:** architecture relationships frozen for stream model.
