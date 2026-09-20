# ARK Truth Stack v1

**Status:** Platform doctrine — applies to Growth, Voice, Operations, Mobile, Portal, and future surfaces  
**Not:** A Growth doctrine, an analytics pattern, or “everything is event-driven”  
**Purpose:** Define how ARK stores truth, summarizes it, explains it, and justifies it to operators.

---

## The model

```
Events are truth.
Projections summarize truth.
Narratives explain truth.
Evidence justifies truth.
```

This is ARK's design language — not a slogan. Every significant surface should map to one of these layers.

| Layer | Question | Mutable? | Example |
| --- | --- | --- | --- |
| **Events (authority)** | What happened? | Append-only | `CommunicationEvent`, `CallSession`, `OperationalEvent`, `GrowthTouchpoint` |
| **Projections** | What should this audience see? | Disposable — rebuild anytime | `OperationalJourneyProjection`, `WorkboardCardProjection`, Attention queue rows |
| **Narratives** | What does it mean? | Composed from projections | Operational Journey story, Operations Briefing, orientation copy |
| **Evidence** | Show me why | Links to immutable sources | `JourneyEvidenceItem`, identity confidence facts, observation source events |

**Projection summarizes truth. It never becomes truth.**

If a projection is deleted, it must be rebuildable completely from authority and events. No projection table may become the only place an operational fact lives.

**Projection Rule #1 — Audience language:** Operational truth should not require users to understand operational structure. Advisors answer questions; technicians perform work; owners observe operations; engineers model authorities. Each projection speaks its audience. UI language and domain names need not match — do not rename authorities to chase operator wording. When users hesitate, first suspect a projection leaking implementation, not a wrong model. Full rule: doctrine `ark-projection-rule.mdc.`

---

## Projection catalog

Every row below is a **projection** — disposable, audience-specific, non-authoritative.

| Projection | Authority (truth sources) |
| --- | --- |
| Operational Journey | Growth session + touchpoints + Voice + Operations events |
| Attention queue | Observations + comms + calls + handoffs (composed, not stored) |
| Workboard cards | Repair order lifecycle + parts + payments + identity pressure |
| Revenue Explorer | Posted / closed repair orders + `GrowthAttribution` |
| Journey Explorer | Same + session touchpoint paths |
| Communications queue / recovery | `CallSession`, `ConversationMessage`, read state |
| Technician dashboard | Assigned work + production events |
| Daily / Operations Briefing | All of the above — **narrative**, not a dashboard |
| Lifecycle select | RO status authority + transition rules |
| Customer Hub relationship context | Conversation + calls + RO history |

When adding a new surface, ask: **Which authority does this read?** If the answer is “another projection,” stop.

---

## Platform invariants

1. **Authorities are stable.** Events and authority rows are append-only unless the domain explicitly allows mutation.
2. **Projections are disposable.** Cache them, delete them, recompute them — truth must survive.
3. **No projection may become authority.** Do not write operational facts into projection-only stores to “make the UI faster.”
4. **Narratives compose projections.** Briefings and journey stories do not invent new truth.
5. **Evidence links downward.** Every narrative claim must trace to authority or be withheld.

Companion: ark-explainability-doctrine.mdc · doctrine `ark-projection-rule.mdc`

---

## Interaction model (ARK UX)

Most software: *Click here for more details.*

ARK:

```
Here's the answer.
      ↓
Here's why.
      ↓
Here's the evidence.
```

Example — *Viewed estimate 3×*:

| Layer | Operator sees |
| --- | --- |
| **What** | Viewed estimate 3× |
| **Why** | Jun 18 2:14 PM · Jun 18 2:18 PM · Jun 18 2:27 PM |
| **Show me** | `CommunicationEvent` #4412 · estimate portal · session · channel |

Three layers. No report. No trust-me.

This applies equally to journey milestones, observations, recommendations, and future AI conclusions.

---

## Operations Briefing (grammar, not dashboard)

**Do not build:** KPI walls, chart grids, “Growth Command Center” dashboards.

**Build:** Operational narrative.

```
Good morning Edward.

Yesterday your shop completed 7 repair orders totaling $8,426.

Three things deserve your attention.

1. Two customers viewed estimates multiple times without follow-up.
   Potential revenue: $2,140
   [expand → evidence]

2. Wheel Bearing page produced four leads yesterday.
   [expand → journey paths]

3. One Google Business Profile call did not become a lead.
   [expand → call recording]
```

Every sentence expands. Every claim links to evidence. The Briefing **consumes** projections; it does not duplicate authority.

Future AI insights follow the same grammar — analyst beside you, not analytics tool:

> Wheel Bearing page generated 12 leads but only 4 appointments.  
> **Why?** Visitors increasingly leave after the repair cost section.  
> **Evidence:** scroll depth ↓ · calculator usage ↓ · competitor FAQ change.

If evidence cannot be shown, the statement is not operational truth.

---

## Sequence for new features

```
Authority (ship events)
  → Observation (vocabulary earns placement)
  → Projection (summarize once per render)
  → Narrative (explain for audience)
  → Evidence (justify on demand)
  → Earned Authority (publish only when traceable — ark-earned-authority.mdc)
  → Observation / AI (only when earned — see ark-earned-intelligence.mdc)
```

Never skip to narrative, publication, or AI before authority and observation are trusted.

**Earned Authority** is the exit gate: when knowledge is allowed to leave the shop. See [ark-earned-authority-v1.md](./ark-earned-authority-v1.md).

---

## Related doctrine

| Document | Relationship |
| --- | --- |
| [ark-constitution-v1.md](./ark-constitution-v1.md) | Coherence over capability; four grammars |
| [ark-workspace-interaction-language-v1.md](./ark-workspace-interaction-language-v1.md) | Workspace evolution |
| ark-observations.mdc | Interpretive truth vocabulary |
| ark-pressure-first.mdc | Observe before enforce |
| [ark-event-native-platform-v1.md](./ark-event-native-platform-v1.md) | Event-native OS — streams, observations, platform sentence |
| [event-contracts-v1.md](../mobile/event-contracts-v1.md) | Business event language (eight questions) |
| [ark-scoped-event-streams-v1.md](./ark-scoped-event-streams-v1.md) | Event Stream Engine (infrastructure) |
| [ark-business-language-v1.md](./ark-business-language-v1.md) | Dictionary — authority / event / observation / projection |
| [ark-earned-authority-v1.md](./ark-earned-authority-v1.md) | Exit gate — when knowledge may leave the shop |
| [ark-repair-authority-v1.md](./ark-repair-authority-v1.md) | Repair is the authority; problem/service pages are projections — rename last |
| [ark-market-authority-v1.md](./ark-market-authority-v1.md) | Market trust — opportunity-first business health (frozen 2026-07-07) |
| `docs/growth/DOCTRINE.md` | Growth instance of this stack |
