# Orientation Platform

**Status:** Frozen contract v1  
**Track:** C — platform service, not a shippable product  
**Code:** `app/Ark/Orientation/`

Orientation is how ARK briefs a human before they act. Every product surface consumes it — Front Counter, Portable Station, call pop, Attention, Customer Hub, RO workspace. No surface invents its own briefing vocabulary.

---

## Three parallel tracks

| Track | Owns | Ships |
|-------|------|-------|
| **A — Operations Platform** | Truth about the environment | Stations, devices, voice ingress, provisioning, presence authority |
| **B — Portable Station** | Portable staff experience | Flutter, push, orientation home, comms UX, internal messaging UI |
| **C — Orientation Platform** | How humans are briefed | Contract, engines, density, composition APIs |

**Track C question:** *Should this orientation come from the platform?*  
The answer is almost always yes.

---

## Capability doctrine

**Capabilities never own workflow. They project workflow.**

Communications does not own repair orders — it projects RO context when a customer texts. Payments does not own customers — it projects payment posture onto the RO. Stations do not own conversations — they orient whoever is standing there.

**Every capability must be certifiable by completing a real piece of shop work** — not API 200 or widget rendered. Certifications split **Capability** vs **Operational** and advance **Engineering → Operational → Production**. See [operational-certifications.md](./operational-certifications.md).

---

## Frozen payload

Every orientation payload exposes the same fields. Surfaces choose density; they do not rename fields.

| Field | Question answered |
|-------|-------------------|
| `current_situation` | What is happening? |
| `context` | Why? (customer, vehicle, RO, station, conversation links) |
| `next_best_action` | What should I do? |
| `confidence` | Can I trust that? (evidence items — not AI confidence) |
| `actions` | What can I do right now? (enabled affordances + params) |
| `ownership` | Who owns the next move |
| `pressure` | What is waiting, aging, or at risk |

### Banned vocabulary

Do not invent parallel labels in UI or API:

- Summary, Status, Recommendation, Insight, Guidance, Hint, Alert copy

Use the contract fields only.

---

## Frozen verbs (order matters)

Every orientation answers exactly these, in order:

1. **What is happening?** → `current_situation`
2. **Why?** → `context`
3. **What should I do?** → `next_best_action`
4. **Can I trust that?** → `confidence`
5. **What can I do right now?** → `actions`

Same sequence at every density. Interrupt surfaces may show fewer fields; they never reorder or rename them.

---

## Density

| Density | Typical surfaces |
|---------|------------------|
| `Interrupt` | Call pop, push tap, SMS interrupt |
| `Compact` | Portable Station home rows, queue snippets |
| `Standard` | Front Counter panels, comms rail, Attention rows |
| `Full` | RO workspace band, Customer Hub briefing |

One engine derivation. `present($density)` trims fields — never duplicate logic in Blade or Flutter.

---

## Implementation today

| Entity | Engine | Status |
|--------|--------|--------|
| Repair Order | `RepairOrderOrientationEngine` | ✅ Shipped |
| Station | — | 🔲 Track A + C |
| Portable Station home | — | 🔲 Track B consumes composed orientation |
| Conversation interrupt | — | 🔲 Should compose RO + conversation context via platform |

Legacy field names in RO engine map to contract: `situation` → `current_situation`, `pressure_label` → `pressure`, `next_action` → `next_best_action`, etc. Normalize at presentation boundary when extending.

---

## Feature gate

Before building anything that briefs a human:

1. Does it reduce reconstruction or Time to Orientation (TTO)?
2. Does it use the platform contract and verbs?
3. **Where does it appear in a day-in-the-life scenario?** See [day-in-the-life/](./day-in-the-life/).

If nobody can answer (3), it probably is not important yet.

---

## Acceptance pattern

Prefer operational scenarios over user stories:

```text
❌ As an advisor, I want to see messages…
✅ At 8:10 AM, customer texts asking if brakes are ready…
```

Scenarios in [day-in-the-life/](./day-in-the-life/) are cross-surface acceptance tests — desktop, fixed station, and Portable Station must complete the same operational loop.
