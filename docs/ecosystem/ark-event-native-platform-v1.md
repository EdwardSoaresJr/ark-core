# ARK — Event-Native Platform v1

**Status:** Architecture **closed** until implementation proves the model cannot express reality.  
**Vocabulary:** [`ark-business-language-v1.md`](ark-business-language-v1.md) — naming disputes start here.

---

## What ARK is

> **ARK is an event-native operating system that models how an automotive repair shop actually works.**

Not merely helping run a shop — **modeling its reality**.

Desktop, Companion, voice, AI, analytics, automation, portal — different ways of **interacting with that shared model**.

Nothing in this sentence says Laravel, Flutter, MySQL, Asterisk, or Twilio. That is intentional.

---

## ARK doctrine stack (how the documents stack)

Looking back, these are not separate debates — they **stack**:

```text
Pressure First                    — observe before enforce · automate
        ↓
Truth Stack                       — events are truth; projections summarize
        ↓
Authority Model                   — who may say this is true
        ↓
Business Language                 — dictionary; authority · event · observation · projection
        ↓
Event Contracts                   — business facts; eight questions per verb
        ↓
Authority Interaction Map         — how authorities relate without owning each other
        ↓
Event Stream Engine               — infrastructure; organizes events by scope
        ↓
Observations                      — what it means
        ↓
Projections                       — operator questions; never invent events
        ↓
Surfaces                          — Companion · desktop · APIs · portal · voice
```

**Runtime stack (same model, one sentence per layer):**

```text
Authorities own truth.
Events express truth.
The Event Stream Engine organizes events.
Observations interpret events.
Projections compose events.
Screens render projections.
```

Every layer has exactly one responsibility.

---

## Doctrine (locked)

> **Events describe what happened. Observations describe what it means. Projections never invent events.**

> **Screens never own truth. Authorities own truth.**

> **Implementation conforms to the business language. Never the reverse.**

Bad: `CustomerPaymentCompleted` in code when the language says **Payment Received**.

---

## When architecture reopens

Architecture is **closed**, not forever.

**Reopen doctrine only when:** implementation teaches you something the model **cannot express** — not when implementation is inconvenient.

Until then: stop writing architecture documents. Make **E1 delightfully boring**.

---

## Document map (architecture phase complete)

| Layer | Doc |
|-------|-----|
| Authority | [`companion-authority-model-v1.md`](../mobile/companion-authority-model-v1.md) |
| Events | [`event-contracts-v1.md`](../mobile/event-contracts-v1.md) |
| Scope membership | [`companion-timeline-scopes-v1.md`](../mobile/companion-timeline-scopes-v1.md) |
| Interactions | [`ark-authority-interaction-map-v1.md`](ark-authority-interaction-map-v1.md) |
| Engine | [`ark-scoped-event-streams-v1.md`](ark-scoped-event-streams-v1.md) |
| Dictionary | [`ark-business-language-v1.md`](ark-business-language-v1.md) |
| Sprint | [`companion-event-architecture-sprint-v1.md`](../mobile/companion-event-architecture-sprint-v1.md) |
| E1 register | [`contract-realization-register-v1.md`](../mobile/contract-realization-register-v1.md) |

---

## Roadmap

```text
E0    Event Contracts                         ✅
E0b   Scope Membership                        ✅
E0.5  Authority Interaction Map               ✅
E0.6  Event Stream Engine                     ✅
E1    Contract Realization                    ← next
E2    Projection APIs
E3    Shell
E4    Surfaces
```

**E1 proves:** every business event contract has **exactly one** authoritative implementation path — not "every mapper maps correctly."

**Success signal:** E1 feels like filling in a spreadsheet, not inventing ideas.

---

## AI posture

**Wrong:** Summarize the customer.  
**Right:** Interpret this scoped stream.

Observations consume engine output. Projections render events — they do not author them.
