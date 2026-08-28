# ARK Scoped Event Streams v1

**Status:** E0.6 — organizing **infrastructure** (not an authority).  
**Prerequisites:** [`event-contracts-v1.md`](../mobile/event-contracts-v1.md) · [`ark-authority-interaction-map-v1.md`](ark-authority-interaction-map-v1.md).  
**Vocabulary:** [`ark-business-language-v1.md`](ark-business-language-v1.md).

---

## Authority vs infrastructure

An **authority** answers: *Who is allowed to say this is true?*

| Authority | Truth |
|-----------|-------|
| Customer | Who the customer is |
| Financial | What money changed hands |
| Call | What call occurred |

**Event streams cannot originate truth.** They **organize** truth emitted by authorities as events.

The **Event Stream Engine** is infrastructure — same category as workspace layout engines and timeline renderers. Not a noun in the business dictionary.

---

## Core insight

One mechanism — many scoped views:

```text
Event Stream Engine(scope, anchor, cursor) → ordered event contracts
```

**Timeline** is UI language for presenting a stream. **Stream** is the infrastructure concept.

Analytics, AI, automation, and feeds consume **engine output** — not parallel truth models.

---

## Engine definition

```text
Stream(scope, anchor, cursor?) =
    event contracts
    WHERE scope membership matches      (E0b — mechanical)
      AND anchor matches
      AND active (expiry · supersession)
    ORDER BY occurred_at
```

| Input | Source |
|-------|--------|
| Contracts | Authority-emitted events — verbs only |
| Membership | [`companion-timeline-scopes-v1.md`](../mobile/companion-timeline-scopes-v1.md) |
| Active rules | Event contract Q6 · Q7 |
| Anchor | customer · vehicle · repair_order · operator · shop cursor |

Delete all cached streams; rebuild from authorities + contracts + engine rules.

---

## Named streams (v1)

| Stream | Anchor | UI projection (not authority) |
|--------|--------|-------------------------------|
| **Customer Stream** | `customer` | Customer Timeline |
| **Vehicle Stream** | `vehicle` | Vehicle Timeline |
| **RO Stream** | `repair_order` | RO Timeline |
| **Operator Stream** | `operator` | Operator Feed |
| **Shop Stream** | `operator` + cursor | Shop Change Feed |

Same event · multiple scopes · one contract.

---

## Observation stack

```text
Authorities
    ↓
Events
    ↓
Event Stream Engine
    ↓
Observation Engine          ← reads engine output
    ↓
Observations
    ↓
Projections                 ← never invent events
    ↓
Surfaces
```

**Wrong:** AI summarizes the customer record.  
**Right:** Observation engine interprets **Customer Stream**.

**Automation example (business rule, not code):**

```text
Observe Customer Stream
when Waiting Estimate becomes Estimate Viewed within 4 hours
→ observation Customer Engaged
→ projection notify Advisor
```

---

## Rendered rows are projections

Chronological rows, feed cards, hub chips — **projection shapes**. The engine output is ordered contracts; UI chooses presentation.

If the row format changes, events and authorities do not.

---

## Projection filters (mechanical)

| Projection | Engine query |
|------------|--------------|
| **Shop Feed** | Shop stream · since cursor · active (includes Waiting) |
| **Recovery Queue** | `action ∈ {Action Required, Blocking}` · transport domains · active |
| **Customer Timeline** | Customer stream · anchor |
| **Customers Browse** | Customer authority + head(customer stream) |

**Doctrine:** Projections compose. They do not author history.

---

## Acceptance (E0.6)

| # | Criterion |
|---|-----------|
| 1 | Event Stream Engine = infrastructure — **not** authority |
| 2 | Authorities originate events; engine organizes |
| 3 | Timeline = UI projection of engine output |
| 4 | Observation engine consumes engine output |
| 5 | Projections never invent events |
| 6 | No new architecture docs — proceed **E1 Contract Realization** |

---

## Doctrine card

```text
Authorities say what is true.
Events record what happened.
The Event Stream Engine organizes events by scope.
Observations describe what it means.
Projections present — they never invent events.
Timeline is how humans read a stream.
```
