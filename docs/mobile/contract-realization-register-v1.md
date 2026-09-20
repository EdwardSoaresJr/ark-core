# E1 — Contract Realization Register v1

**Status:** **FROZEN** — Payment Received ✅ archived. Active mission: [`companion-sprint-1-run-the-shop.md`](companion-sprint-1-run-the-shop.md). Do not add rows until Companion Sprint 1 P0 experiences pass floor test.  
**Sprint:** [`companion-event-architecture-sprint-v1.md`](companion-event-architecture-sprint-v1.md)  
**Contracts:** [`event-contracts-v1.md`](event-contracts-v1.md) · scopes: [`companion-timeline-scopes-v1.md`](companion-timeline-scopes-v1.md)

**Question:** Does every business event contract have **exactly one** authoritative implementation path?

**Engineering rule (frozen):** **One contract · one vertical slice · one PR.** Ship · observe · next row. Do not realize twelve contracts in one giant PR.

---

## Register columns (permanent)

| Column | Meaning |
|--------|---------|
| **Contract** | Signed business verb |
| **Authority** | Domain that owns truth |
| **Emits** | Exactly one implementation path that records this fact |
| **Stream Membership** | Scopes where engine includes this contract (E0b) |
| **Projections** | Surfaces that render the contract verb — never invent it |
| **Observations** | What clears or interprets — never stored as parallel truth |
| **Verified** | Vertical slice + failure test pass |

**Litmus on a completed row:**

> This business fact exists everywhere it should, nowhere it shouldn't, and means exactly one thing.

**Required question (every row):**

> What business truth would be lost if this implementation disappeared?

| Layer | Example |
|-------|---------|
| **Payment Received** (event) | The shop no longer knows money was received. |
| **Customer Waiting Payment** (observation) | **None** — it is interpretive; removing it does not erase payment truth. |

**Failure test (every row):**

> No projection invents this event.

If Customer Timeline says **Payment Received**, there must be **exactly one** originating authority event behind it — not a projection synthesizing history.

---

## Vertical slice (architecture verification)

```text
Authority (truth)
    ↓
Event Contract
    ↓
Implementation (Emits)
    ↓
Event Stream Engine
    ↓
Projections (timeline · feed)
    ↓
Observations (clear · interpret — never invent)
```

Not required in the register: controller names · Eloquent models · DTOs · mapper class names · Flutter · API routes. Those are implementation.

---

## Companion-critical queue

**Order:** one PR per contract. First: **Payment Received**.

| Contract | Authority | Emits | Stream Membership | Projections | Observations | Verified |
|----------|-----------|-------|-------------------|-------------|--------------|----------|
| **Payment Received** | Financial | `EmitPaymentReceivedEvent` via `RecordLedgerEntryAction::recordPayment` | Customer · RO · Shop Feed | Customer Timeline · RO Timeline · Shop Feed | Waiting payment authority clears (`payment_status` paid · balance 0) | ✅ |
| Message Received | Message | | | | | ⬜ |
| Call Missed | Call | | | | | ⬜ |
| Voicemail Received | Call | | | | | ⬜ |
| Estimate Sent | Communication fact | | | | | ⬜ |
| Estimate Viewed | Communication fact | | | | | ⬜ |
| Estimate Approved | Communication fact | | | | | ⬜ |
| Payment Requested | Financial | | | | | ⬜ |
| Inspection Completed | Inspection | | | | | ⬜ |
| Customer Arrived | Appointment | | | | | ⬜ |
| Technician Blocked | Repair Order | | | | | ⬜ |
| Waiting Approval | Repair Order | | | | | ⬜ |

---

## Payment Received — slice spec

**Truth lost if implementation disappears:** The shop no longer knows money was received.

| Step | Pass when |
|------|-----------|
| **Authority** | Financial domain owns payment truth |
| **Contract** | **Payment Received** — signed eight questions |
| **Emits** | Single path when payment is captured (portal · terminal · keyed) |
| **Stream Membership** | Customer · RO · Shop Feed per E0b |
| **Projections** | Customer Timeline + RO Timeline render **Payment Received**; Shop Feed only if contract scopes include Shop |
| **Observations** | Customer Waiting Payment / waiting-payment pressure clears; no new observations invented |
| **Failure test** | Timeline entry traces to exactly one Financial emit — projection did not invent event |

---

## Full v1 catalog (after companion-critical)

Same columns as rows close — lower priority after companion-critical queue.

| Domain | Contracts |
|--------|-----------|
| Repair Order | RO Created · RO Started · RO Approved · RO Ready · RO Closed · Part Backordered |
| Inspection | Inspection Started · Finding Added · Inspection Published |
| Message & call | Message Sent · Call Started · Call Answered · Call Completed · Call Transferred |
| Financial | Refund Issued · Balance Due · Payment Requested |
| Estimate & portal | Estimate Deferred |
| Appointment | Appointment Scheduled · Appointment No-Show |
| Vehicle & intake | Vehicle Checked In · VIN Verified |
| Operator Identity | Extension Registered · Device Attached · Call Moved (reserved) |
| Presence | Presence Changed · On Call · Available (reserved) |

---

## Vocabulary audit (before ✅)

- [ ] Emits uses **Payment Received** — not `CustomerPaymentCompleted` or "Customer Paid"
- [ ] Exactly **one** authority emits this contract
- [ ] Scope membership matches E0b
- [ ] Failure test: projection trace → single authority event
- [ ] Observation columns cite interpretive truth only

**Success:** filling rows feels like checking boxes. That is correct.
