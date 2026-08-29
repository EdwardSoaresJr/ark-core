# Communications Foundational Doctrine v1

**Status:** Locked — post Twilio-native transport reset (2026-07-05)  
**Supersedes:** transport-specific mental models (Asterisk PBX, shop SIP bridge, legacy third-party voice routing)  
**Companions:** [ark-conversations-v1.md](ark-conversations-v1.md) · [communications-authority.md](../communications-authority.md) · [communications-bounded-context-v1.md](communications-bounded-context-v1.md) · [ADR-0005](../engineering/adr/ADR-0005-twilio-native-voice-transport.md)

> **Product surface:** Conversations doctrine is frozen in [ark-conversations-v1.md](ark-conversations-v1.md). Do not refine that document for wording — next changes come from floor validation of The Six Ones.

---

## Communications authority

### Core authorities (frozen)

The communications domain is composed of the following authorities:

| Authority | Role |
| --- | --- |
| **Customer** | Relationship identity |
| **Vehicle** | Vehicle identity in comms context |
| **Conversation** | Customer-relationship thread — the conversation is the authority |
| **ConversationMessage** | Human communication acts (SMS, MMS, advisor notes, outbound links) |
| **CallSession** | Telephony operational truth (ring, active, missed, recording metadata) |
| **CommunicationEvent** | Workflow facts from communication activity |
| **UnifiedOperationalTimeline** | Composed read of messages, calls, and events for a relationship or RO |
| **Observation** | Interpretive truth — what happened and why it matters |

These authorities define communications within ARK.

**Do not introduce parallel authorities for transport-specific concepts** — no SMS inbox store, no voicemail table, no channel-first timeline, no provider-owned call history.

---

## Transport doctrine

Voice, SMS, MMS, voicemail, recordings, and future channels are **transports**, not products.

**Twilio is the current transport implementation.**

The communications domain must not depend on transport-specific behavior.

Replacing Twilio with another provider must require changes **only to the transport adapter**, not to the communications model.

> **Twilio is infrastructure. Communications is the product.**

If Twilio becomes the new Asterisk — leaking into product vocabulary, settings mental models, or parallel stores — the boundary has failed.

---

## Product doctrine

The advisor does not interact with a phone system.

The advisor interacts with a **customer conversation**.

Every transport contributes events to the same conversation.

**The conversation is the authority.**

Design and review question:

> *What does the advisor need to communicate effectively right now?*

Not:

> *How does the telephony system work?*

---

## Advisor ownership (post transport reset)

Communications belong to **advisors**, not stations.

```text
Conversation → Advisor
```

Station and desk phone are **optional metadata** — where the interaction happened — not an ownership chain.

| Required | Optional |
| --- | --- |
| Advisor · Device · Conversation | Station |

If Edward answers from Companion, desktop, or desk phone, **Edward** owns the thread. Front Counter is context.

**Freeze (Companion phase):** Do not extend workstation automation, IP inference, lock screens, or browser binding as comms ownership. Revisit only after Companion reaches production quality.

Doctrine: doctrine `ark-advisor-communications-identity.mdc`

---

## Companion doctrine

**Internal name:** **ARK Companion: Communications** — advisor product. Future siblings (Technician, Customer) share backend, different missions. See [companion-v1/MISSION.md](../companion-v1/MISSION.md).

**ARK Companion is not ARKv2 on a smaller screen.**

**ARK Companion is the advisor's communications command center.**

The first screen is the **inbox**.

Every design decision begins with the product doctrine question above — optimized for *who needs me right now*, not for reproducing desktop operational surfaces.

Companion shares authorities with ARKv2 (conversations, calls, repair orders, observations) but is optimized for **interrupt and recovery**, not for running the full shop floor.

**UX benchmark:** OpenPhone / Quo interaction quality — inbox density, thread navigation, live call, push-first workflow.

**ARK differentiation:** Every conversation surfaces customer, vehicle, active RO, estimate/inspection status, parts pressure, advisor ownership, and observations **inline** — communication first; operations available within communication.

**Design philosophy:** Every tap should reduce uncertainty for the advisor — *what do they need to know before speaking?* — not a dump of every authority field.

**Deep-link boundary (sacred):** Answering a customer → Companion. Sustained operational work (RO editing, parts, production, reporting, scheduling) → ARKv2.

**Competitive frame:** OpenPhone organizes conversations; GHL organizes CRM records; shop software organizes repair orders. ARK Companion organizes **advisor awareness**.

---

## Product split (ARKv2 ↔ Companion)

For months, ARKv2 tried to answer two questions with one interface:

| Question | Natural home |
| --- | --- |
| *What work needs to be done?* | **ARKv2** — operational workspace |
| *Who needs my attention right now?* | **ARK Companion** — communications workspace |

Those are different modes. Separating them is not arbitrary feature splitting — it is splitting by **user intent at that moment**.

| Product | Optimized for |
| --- | --- |
| **ARKv2 (desktop)** | Workboard, RO lifecycle, estimates, production, owner rhythm |
| **ARK Companion (mobile)** | Inbox, live call, thread, voicemail, push, search — communications command center |

They share the same authorities, the same conversations, the same repair orders, the same observations.

They must not share the same navigation hierarchy or primary question.

---

## PR litmus

Before any Communications or Companion change:

1. Does it introduce a new authority or duplicate an frozen one?
2. Does it encode Twilio-specific behavior above the transport adapter?
3. Does it ask the advisor to think in phone-system terms?
4. Does it assign comms ownership to a **station** instead of an **advisor**?
5. Does it collapse ARKv2 work mode and Companion attention mode into one surface?

If yes to any — stop and redesign.
