# The Companion — Event Architecture Sprint v1

**Status:** **FROZEN · ARCHIVED** — work complete for now. Do not extend.  
**Active mission:** [`companion-sprint-1-run-the-shop.md`](companion-sprint-1-run-the-shop.md)

**Platform reference (read-only):** [`ark-event-native-platform-v1.md`](../ecosystem/ark-event-native-platform-v1.md) · [`ark-business-language-v1.md`](../ecosystem/ark-business-language-v1.md)

> **ARK is an event-native operating system that models how an automotive repair shop actually works.**

This sprint did its job. **Companion Sprint 1** is product execution — run the shop from the phone.

---

## Frozen — leave alone

Architecture is good enough. Reopen only when a **P0 Companion experience** cannot be built without a model change.

**Do not** add rows to the contract register · **do not** write new architecture docs · **do not** resume E1 unless implementation proves a gap.

---

## Roadmap

| Phase | Name | Status |
|-------|------|--------|
| E0 | Event Contracts | ✅ |
| E0b | Scope Membership | ✅ |
| E0.5 | Authority Interaction Map | ✅ |
| E0.6 | Event Stream Engine | ✅ |
| **E1** | **Contract Realization** | **Frozen** (Payment Received slice shipped — register archived) |
| E2 | Projection APIs | after E1 |
| E3 | Shell contract | after E2 |
| E4 | Surfaces | last |

---

## E1 — Contract Realization

**Question E1 answers:**

> Does every business event contract have exactly one authoritative implementation?

Not: *Does every mapper map correctly?* Mappers are implementation. **Contracts** are the product.

### Frozen rule

**Implementation conforms to the business language. Never the reverse.**

| Bad (invents vocabulary) | Good (conforms) |
|--------------------------|-----------------|
| `CustomerPaymentCompleted` | **Payment Received** |
| `CustomerPaid` (projection-only) | **Payment Received** (event) → Customer Timeline (projection) |

**Engineering rule:** one contract · one vertical slice · one PR. Ship · observe · next row.

**Register:** [`contract-realization-register-v1.md`](contract-realization-register-v1.md) — columns: Contract · Authority · Emits · Stream Membership · Projections · Observations · Verified.

**Litmus on a completed row:** this business fact exists everywhere it should, nowhere it shouldn't, and means exactly one thing.

**Required question:** what business truth would be lost if this implementation disappeared?

**Failure test:** no projection invents this event — timeline entries must trace to exactly one authority emit.

**First slice:** Payment Received ✅

### Vertical slice validation (architecture verification)

For **each** event contract in the companion-critical set — not unit-test trivia, **architecture verification**:

```text
Event Contract
        ↓
Implementation          — exactly one authority emits this verb
        ↓
Projection Test         — engine output includes it in signed scope(s)
        ↓
Observation Test        — where applicable, observation clears or interprets
```

**Example — Payment Received**

| Step | Pass when |
|------|-----------|
| **Contract** | **Payment Received** — Financial domain · eight questions signed |
| **Implementation** | Financial authority emits event (one path; no parallel names) |
| **Projection Test** | Customer Stream + RO Stream + Shop Feed show **Payment Received** — not "Customer Paid" |
| **Observation Test** | **Customer Waiting Payment** (or equivalent waiting observation) clears when event occurs |

**Example — Message Received**

| Step | Pass when |
|------|-----------|
| **Contract** | **Message Received** |
| **Implementation** | Message authority emits on inbound |
| **Projection Test** | Customer Stream + Shop Feed membership per E0b |
| **Observation Test** | Customer-replied / waiting-response observation resolves on advisor reply event |

Every companion-critical verb gets a row in the **Contract Realization Register** (E1 deliverable — spreadsheet or markdown table, not a new doctrine doc).

If every contract has this vertical slice, **E2 becomes almost impossible to get wrong**.

---

### E1 deliverables

1. **[Contract Realization Register](contract-realization-register-v1.md)** — one row per v1 verb: implementation owner · projection scopes verified · observation link (if any)
2. **One implementation path per contract** — no duplicate emitters under different names
3. **Scope membership** matches E0b — no drift
4. **No invented vocabulary** in PHP — names match [`ark-business-language-v1.md`](../ecosystem/ark-business-language-v1.md)

**Non-goals:** Flutter · shell API · new architecture docs · new event verbs without contract amendment

---

### E1 acceptance

| # | Criterion |
|---|-----------|
| 1 | Every companion-critical contract has realization register row |
| 2 | Vertical slice passes: contract → implementation → projection → observation |
| 3 | Exactly one authoritative emitter per contract |
| 4 | Zero invented vocabulary in implementation |
| 5 | E1 feels boring — spreadsheet work, not design debate |

---

## After E1

**E2 — Projection APIs:** Shop Feed · streams · recovery — shapes follow realized contracts.

**E3 — Shell:** Nav as projection pointers — obvious after E2.

**E4 — Surfaces:** Flutter last.

---

**Archived. Active work:** Companion Sprint 1 — Run the Shop.
