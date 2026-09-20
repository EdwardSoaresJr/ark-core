# Customer Continuity Workspace Rules (Binding)

**Status:** Binding engineering guardrail  
**Audience:** PR reviewers / implementers  
**Not:** Frozen Conversations product doctrine  
**Filename note:** Path stays `communications-workspace-rules.md` for continuity with existing links; the workspace itself is **customer continuity**, not a messaging product.

| Layer | Document | Role |
| --- | --- | --- |
| **Architecture (frozen)** | [ark-conversations-v1.md](../communications/ark-conversations-v1.md) · [communications-foundational-doctrine-v1.md](../communications/communications-foundational-doctrine-v1.md) | What the product is |
| **This document** | Engineering workspace rules | UI + implementation constraints so PRs stay aligned |

Do **not** edit frozen Conversations doctrine for wording polish. Enforce alignment here and in companion doctrine.

**Doctrine: ** doctrine `ark-communications-workspace-guardrail.mdc`

---

## Design bar (read first)

**An advisor should be able to answer a customer's phone call without opening the Repair Order.**

If they answer and immediately know who they are, phone and email, vehicle, repair stage, what's waiting, and what they can do next — this is a true advisor workspace.

If they only see messages — it is a unified inbox. Reject that outcome.

That sentence is the design bar for every PR that touches this workspace.

---

## What this workspace is

The bounded context may still be named **Communications** in code and domain docs.

The **workspace** is not about communications anymore. It is about **customer continuity**.

Transports (SMS, phone, email, Messenger, portal, …) are **one input** into relationship state — not the product center.

**Messenger is not a peer product.** After platform/shop Meta separation, Messenger must land in the same Communications continuity workspace and Needs Attention evaluation as SMS — chronological relationship story, transport icon only. Do not ship a Messenger inbox, Messenger tab world, or advisor path that asks “where did that land?”

| Prefer (engineering / UI intent) | Avoid |
| --- | --- |
| Customer continuity workspace | “Communications app” / messaging product |
| Customer Conversation · Conversation | Conversation Thread · Thread (implementation language) |
| Relationship list | Inbox (architecture) · SMS/email/call thread lists |
| Conversations (frozen product name where already used) | Channel inboxes |

---

## Primary Workspace Rule

When an advisor opens a conversation, the primary question is **not**:

> What messages are here?

It is:

> What is the state of this customer relationship?

The timeline is **only one panel**.

The workspace must always answer, at a glance:

```text
Sarah Johnson
719-555-0100
sarah@email.com

2019 Honda CR-V

RO #1583 · Waiting Approval
Advisor: Edward

────────────────────────────
Next Actions
  Call · Reply · Schedule · Payment · Directions
────────────────────────────

Conversation
  (chronological timeline — below operational context)
```

**Operational context and next actions sit above the conversation.**

The conversation is below the operational context, not above it.

That is the biggest difference between ARK and OpenPhone: OpenPhone centers the message stream; ARK centers relationship + work state, with conversation as supporting story.

---

## Identity Never Collapses

No matter how narrow the viewport gets, identity must not reduce to a bare name.

| Viewport | Minimum identity |
| --- | --- |
| **Desktop** | Name · phone · email (when available) |
| **Tablet** | Name · phone |
| **Phone** | Name · phone |
| **Unknown** | Phone prominent · “Unknown Customer” |

**The phone number is part of the identity, not hidden metadata.**

### Identity is never sacrificed for aesthetics

If displaying only a name hides critical information, show the phone number, email, or both. Advisors must never open a conversation solely to learn who they are talking to or how to reach them.

List rows (left list) must still show enough to act without opening: name or Unknown, **phone always**, email when available, vehicle/RO when applicable, preview, time, attention/work state.

---

## Transport is decoration

Order of meaning:

1. **Workspace** — *why* (relationship + work state + next actions)
2. **Timeline** — *when* (chronology)
3. **Icon** — *how* (transport evidence)

That order matters. Do not let transport grouping, channel chrome, or message density displace operational context.

---

## Authority

**The Customer Conversation is the authority.**

It contains every customer-facing communication regardless of transport.

Supported transports include (current and future):

- Phone Calls
- Voicemail
- SMS
- MMS
- Email
- Facebook Messenger
- Customer Portal
- Mobile App Chat
- Google Business Messages
- RCS
- WhatsApp
- Internal Notes (with appropriate visibility)

**Adding a new transport must never create a new inbox, thread type, or authority.**

Channels are evidence within the conversation. They are not products.

```text
Customer
    └── Conversation
            ├── SMS · MMS · Phone · Voicemail
            ├── Email · Facebook Messenger
            ├── Portal · Mobile App Chat
            └── Internal Notes (visibility-scoped)
```

Notice what must **not** exist as authority or primary UX:

- SMS Inbox
- Email Inbox
- Facebook Inbox
- Call Log (as the relationship product — Calls & VM remains an **evidence** library)

---

## Repair Orders

**Repair Orders do not own communications.**

Repair Orders **project** the portions of the customer conversation that are linked to that repair.

The Customer Conversation remains the single source of truth.

```text
Customer Conversation
          │
          ├── linked to RO #1583
          ├── linked to RO #1590
          └── general customer history
```

No duplicated conversations. No copied messages. No synchronization between RO and customer stores.

---

## Surface rules (same renderer)

| Surface | Rule |
| --- | --- |
| **Continuity workspace** | Relationship state + next actions + full chronological Customer Conversation |
| **Repair Order Workspace** | **Exact same** conversation renderer. Filter to items linked to that RO. Never duplicate messages. |
| **Customer Workspace** | **Exact same** renderer. Customer's complete communication history. |

Same renderer. Different filter. Chronology organizes the story panel. Transport is decoration.

---

## Left list is not a CRM

Borrow interaction patterns from products like OpenPhone / GHL when useful. **Do not inherit CRM concepts.**

The left list remains a **customer relationship list**:

```text
Sarah Johnson
Waiting Approval
Honda CR-V
2m ago
```

**Reject** list chrome that turns this into a CRM:

- Lead
- Pipeline
- Tags
- Campaign
- Owner (as CRM ownership fields)

Advisor assignment / active advisor for the relationship may appear when operational — that is floor coverage, not CRM “Owner.”

---

## Inbox vocabulary rule

**Avoid the term Inbox in engineering.**

| Prefer | Avoid (engineering) |
| --- | --- |
| Customer continuity · Conversations | Inbox (as product/architecture name) |
| Customer relationship list | SMS threads / email threads / call logs as list models |
| Attention / Needs attention (filters) | Parallel inbox authorities |
| Conversation · Customer Conversation | Thread · Conversation Thread |

Operator-facing filter labels may stay familiar when floor-validated; engineering must not invent `SmsInbox`, channel thread authorities, or Inbox-as-architecture.

---

## One timeline

Every transport renders into the **same** chronological timeline.

Example beats (not separate panels):

SMS → Phone Call → Voicemail → Email → Portal Message → Facebook Messenger → Estimate Viewed → Estimate Approved · Photo Uploaded

Chronology organizes the story. Transport is an icon / evidence expand — not a group header that splits the story.

---

## Completion rule

Advisors never decide:

- which inbox
- which communication channel
- which thread

**They choose a customer. ARK resolves the rest.**

---

## PR rejection criteria

Reject any PR that introduces:

- SMS inbox · Email inbox · Messenger inbox
- Per-channel thread authorities
- RO-owned communication storage
- Duplicate conversation histories
- Different renderers for Customer vs RO conversations
- Identity that collapses to name-only (phone/email require opening the conversation)
- Message stream above / without operational context (fails the design bar)
- CRM list concepts: Lead · Pipeline · Tags · Campaign · CRM Owner
- “Thread” as product vocabulary in new UI copy or engineering models

---

## Companions

| Document / rule | Relationship |
| --- | --- |
| [ark-conversations-v1.md](../communications/ark-conversations-v1.md) | Frozen product doctrine (The Six Ones) — do not reword for this guardrail |
| [communications-foundational-doctrine-v1.md](../communications/communications-foundational-doctrine-v1.md) | Frozen authorities + transport doctrine (bounded context name may remain Communications) |
| ark-attention-queue.mdc | Attention is recovery triage — not a channel inbox |
| ark-comms-call-surfaces-lock.mdc | Calls & VM evidence library stays discoverable |
| ark-projection-rule.mdc | RO / customer surfaces project conversation — never become authority |
| ark-advisor-communications-identity.mdc | Advisor owns the conversation; station is optional metadata |
