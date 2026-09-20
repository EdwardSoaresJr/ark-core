# ARK Companion: Communications — Mission (Frozen)

**Status:** Frozen product doctrine — architecture stable  
**Internal name:** **ARK Companion: Communications** (advisor)  
**Not:** ARK Mobile · not ARKv2 on a smaller screen · not a general staff app

Future siblings share backend, different missions:

| Product | Audience | Optimizes for |
| --- | --- | --- |
| **Companion: Communications** | Advisor | Calls, texts, inbox, customer context |
| **Companion: Technician** | Technician | Assigned production work (future) |
| **Companion: Customer** | Customer | Portal / app (future) |

---

## Design philosophy (frozen)

> **Every tap should reduce uncertainty for the advisor.**

Not: expose every field. Not: recreate the RO workspace. Not: make them hunt.

Organizing question:

> **What does the advisor need to know before speaking?**

Not: *Which screen should they open?*

### Example — inbound call

**Wrong** (data dump):

Customer · Vehicle · RO · Estimate · Inspection · Messages · Calls · Notes

**Right** (uncertainty reduced):

Waiting for brake approval  
2017 Subaru Outback  
Estimate viewed yesterday  
Advisor: Molly  
**Call**

The advisor immediately knows what matters. OpenPhone cannot do this.

---

## Competitive frame

| Product | Organizes around |
| --- | --- |
| OpenPhone / Quo | Conversations |
| GHL | CRM records |
| Most shop software | Repair orders |
| **ARK Companion** | **Advisor awareness** |

ARK does not win by being a better CRM or a better phone app. It wins by answering *what matters right now* before the advisor acts.

---

## Product relationship (ARKv2 ↔ Companion)

| Surface | Role | Advisor lives here for… |
| --- | --- | --- |
| **Companion** | Relationships | Calls, texts, inbox, interrupt, recovery — throughout the day |
| **ARKv2** | Operations workspace | Planning, editing, production, parts, reporting, owner rhythm |

Same communication authorities. Different primary question. Moving between them should feel like one product — not switching applications.

If you're **answering a customer** → Companion.  
If you're **editing a repair order for two minutes** → desktop.

---

## Mission

**ARK Companion: Communications is the communications command center.**

It optimizes for:

- Calls
- Texts
- Voicemails
- Conversations
- Customer context
- Vehicle context
- Quick operational actions

If a workflow requires **sustained operational work** — editing repair orders, managing parts, reporting, accounting, scheduling — Companion **deep-links to ARKv2** rather than reproducing the desktop experience.

---

## UX benchmark

**OpenPhone / Quo** — not visually identical; **interaction quality should feel comparable.**

| Dimension | Bar |
| --- | --- |
| Inbox density | Scan rhythm comparable to Quo |
| Thread navigation | Fast, habit-forming |
| Swipe actions | Standard comms affordances |
| Live call UI | Clear, interrupt-safe |
| Search | Fast customer switching |
| Voicemail / recording playback | First-class, not buried |
| Push-first workflow | Notification → act without hunting |

**Primary UX benchmark:** OpenPhone / Quo — see [`references/external/quo.md`](references/external/quo.md).

---

## ARK differentiation

Every conversation should surface operational context **without leaving the thread**:

| Context | Source |
| --- | --- |
| Customer | Authority |
| Vehicle | Authority |
| Active RO | Authority |
| Estimate status | Projection |
| Inspection status | Projection |
| Parts pressure | Projection |
| Advisor ownership | Authority |
| Observations | Interpretive truth |
| Advisor Brief | Projection — awareness before action |

**No competitor provides this level of operational context.**

Product principle:

> **Communication comes first. Operations are available within communication.**

Do not force users to leave a conversation to understand the customer's situation.

### Information hierarchy (protect)

```
Identity → Conversation → Advisor Brief → Operational Context
```

Companion informs. ARKv2 operates. Conversation stays primary.

Operational context is progressive disclosure — show only what reduces uncertainty for the advisor at this moment.

---

## Build order (milestones only)

Engineering executes implementation details. Product input stops at milestones:

| # | Milestone | Success signal |
| --- | --- | --- |
| **1** | **Inbox** | Advisor opens app → knows who needs attention |
| **2** | **Conversation thread** | Reply, attachments, timeline — habit-forming |
| **3** | **Calling** | Inbound, outbound, active call, post-call |
| **4** | **Voicemail / recordings** | Playback, mark handled, link to customer |
| **5** | **Advisor awareness** | Brief before responding — one recommendation, promises, suggested replies |
| **6** | **Operational context** | Vehicle · RO · estimate · inspection · parts · appointment · warranty — only when relevant |
| **7** | **Production feel** | Companion disappears — motion, speed, aware notifications, search, haptics, battery, offline |

**Quality bar (every milestone from 4 onward):** If an interaction feels slower, more confusing, or less polished than OpenPhone, stop and improve it before adding features.

**Floor certification:** Each milestone ships when the floor says it is good enough to continue — not when it is perfect. Issues become tickets; the next milestone does not wait on polish debt unless it blocks the workflow.

**Not in scope for milestone guidance:** 40-page specs, pixel-level instructions, screen-by-screen build orders.

Reference material lives in [`screens/`](screens/) and [`references/`](references/) — **inform design; do not dictate sequence.**

---

## Deep-link boundary (sacred)

This rule prevents feature creep. Treat it as non-negotiable unless floor observation proves sustained mobile work is the repeated sentence.

| Stay in Companion | Deep-link to ARKv2 |
| --- | --- |
| Answer · reply · call · listen · read context · quick send (estimate/payment/inspection link) | RO editing · parts · production · reporting · scheduling · owner rhythm · any sustained operational work |

Quick actions **launch** from conversation; sustained work **hands off** to desktop.

**Litmus:** Will the advisor stay in this flow for more than a minute of operational editing? → ARKv2.

---

## Authority (unchanged)

Companion reads ARK V2 authority via `/api/mobile/*`. No parallel stores.

Frozen authorities: Customer · Vehicle · Conversation · ConversationMessage · CallSession · CommunicationEvent · UnifiedOperationalTimeline · Observation.

Full stack: [communications-foundational-doctrine-v1.md](../communications/communications-foundational-doctrine-v1.md)

---

## Floor test

After Milestone 4 (minimum): advisor completes [`companion-sprint-1-run-the-shop.md`](../mobile/companion-sprint-1-run-the-shop.md) comms paths without opening desktop for interrupt/recovery work.

---

## Implementation input model

**Give implementers:**

- This mission
- Design philosophy (*every tap reduces uncertainty*)
- Milestone target
- UX benchmark reference
- Deep-link boundary (sacred)
- Authority constraints

**Do not give implementers:**

- Screen-by-screen build sequences
- Pixel specs as primary instruction
- Architecture essays (architecture is stable)

Implementers fill implementation details once the mission is clear.
