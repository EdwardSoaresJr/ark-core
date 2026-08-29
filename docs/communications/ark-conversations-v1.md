# ARK Conversations v1

**Status:** Doctrine frozen  
**Product name:** Conversations  
**Engineering milestone (internal only):** Inbox Rewrite  

> **Doctrine frozen.** The next improvement must come from watching advisors use Conversations—not from editing this document.
>
> **Conversations is the operational memory of the repair shop.** Everything else is implementation.

**Companions:** [communications-foundational-doctrine-v1.md](communications-foundational-doctrine-v1.md) · [communications-authority.md](../communications-authority.md) · Attention Queue doctrine (`ark-attention-queue`)

**Do not** name external products in this document. Build from ARK principles only.

---

## Implementation rule

**Do not optimize for beautiful Threads. Optimize for advisors getting back to work in under five seconds.**

If an advisor opens Conversations and, within a few seconds, knows who needs them, why, what happened, and what to do next — H has succeeded, even if visual refinements remain.

---

## Shop stack

```text
RO            → What work are we doing?
Schedule      → When are we doing it?
Conversations → What do we know, and what happens next?
Shop          → What is happening across the operation?
```

---

## Core definitions

**A Thread is the operational memory of a customer relationship, projected from multiple communication authorities.**

Conversations is an operational triage surface — not a messaging product.

Decision tests:

1. Who needs me next?
2. Continue the customer's story?
3. Reduce navigation?
4. Make the operational next action obvious?

**Non-regression:** The advisor sees who needs them — never which channel rang. An inbox row is a waiting relationship, never a transport label (SMS, Call, Portal, Email).

**Protect forever:** Calls & VM remains an **evidence** surface. Workflow (Conversations) and evidence are different surfaces. Do not delete Calls & VM because it appears duplicated.

---

## The Six Ones

Engineering acceptance checklist. H0 is incomplete until every box holds.

```text
┌─────────────────────────────────────┐
│  The Six Ones                       │
│                                     │
│  ✓ One Relationship                 │
│  ✓ One Thread                       │
│  ✓ One Turn                         │
│  ✓ One Identity                     │
│  ✓ One Story                        │
│  ✓ One Next Action                  │
└─────────────────────────────────────┘
```

| One | Meaning |
| --- | --- |
| **One Relationship** | Survives spouses, fleet contacts, phone/email changes, SMS → email → portal |
| **One Thread** | All interactions compose into one Thread projection |
| **One Turn** | Whose move — computed from authority, never advisor-editable |
| **One Identity** | Relationship header before the story (vehicle, RO, estimate, activity, assignee) |
| **One Story** | Every customer-facing interaction in one chronological operational narrative regardless of transport |
| **One Next Action** | Workspace makes the operational next action obvious |

### H0 acceptance (brutal)

**If any of The Six Ones fail during the Sarah saga, H0 fails. Do not begin H1.**

**At no point during the saga may a second Thread, conflicting Turn, duplicated Identity, broken chronology, or ambiguous Next Action appear.**

Saga: text → call → voicemail → portal approval → estimate viewed → advisor reply → payment request → paid → pickup scheduled.

Then: **Floor validation** with a real advisor. No H1 chrome until that gate passes.

---

## Status vs Turn

| Concept | Answers | Values |
| --- | --- |
| **Status** | Is this relationship still operational? | Active · Resolved · Archived |
| **Turn** | Whose move? | Waiting on Shop · Waiting on Customer |

A Resolved Thread can become Waiting on Shop again when the customer replies (Status → Active; Turn recomputed). Do not collapse Status into Turn.

Primary list navigation is by **Turn** (with counts) among Active relationships. Resolved and Archived are Status filters.

Turns are **computed** from authority (last customer event, last shop event, unresolved commitments, pending approvals, unhandled call coverage, etc.). Advisors never flip Turns manually — that would turn truth into tags.

---

## Reasons

Turn = state. Reason = why. The row leads with Reason:

```text
Sarah Johnson
Customer replied to estimate · 2 min ago
2018 F-150 · Brake Estimate
```

**Reasons must be explainable** — derived from authority events. Examples: Customer replied · Missed inbound call · Estimate viewed · Estimate approved · Inspection completed · Payment received.

**Forbidden:** Inferred or generative phrasing such as “Customer may need follow-up.” Reason must support What / Why / Show me.

---

## Story

**One Story means every customer-facing interaction appears in one chronological operational narrative regardless of transport.**

**Story order is determined by operational occurrence, not event arrival.**

Late webhooks, retries, delayed transcription, and delayed payment events must not scramble chronology. Sort by operational occurrence timestamp on authority.

**The Story is chronological. The Story is immutable. Advisors may add to the story, but never rewrite history. The Story may be interpreted, but never edited.**

Story is the projection. Authority is the evidence.

Primary labels are operational (Customer asked… · Customer called… · Estimate approved…). Transport is supporting evidence, not the headline.

### Evidence

Evidence is not a separate panel. It expands from each Story beat:

```text
Story beat: Customer approved estimate
  ▼ Evidence
    SMS · Portal · Recording · Photo · Timestamp
      → Authority rows
```

```text
Story → Evidence → Authority
```

---

## Workspace stack

```text
Identity
  → Story (+ Evidence expand)
  → Shop Context
  → Actions
  → (Summary when earned — not required)
```

Advisor rhythm: Who? → What happened? → What am I looking at? → What do I do?

### Identity

```text
Sarah Johnson
2018 Ford F-150
RO #4821
Brake Estimate
Waiting on Shop
Last activity 3 min ago
Assigned Advisor: Molly    ← when assignment exists
```

### Shop Context

Shop objects only: Vehicle · RO · Estimate · Inspection · Appointments · Warranty · Payment · Outstanding balance.

Never as Conversations core: Lead · Opportunity · Pipeline · Campaign · Tags.

### Actions / Next Action

The workspace makes the **operational** next action obvious (Call · Reply · Record Approval · Take Payment · Schedule). UI may evolve; the obligation is operational clarity, not a fixed widget set.

**Summary** is not part of the H2 contract. Add only after operators repeatedly re-read the same facts from the Story.

---

## Authority vs projection

| Layer | Role |
| --- | --- |
| **Authority** | `Conversation` (contact surface + address), `ConversationMessage`, `CallSession`, `CommunicationEvent`, Customer / Vehicle / RO |
| **Projection** | Thread · Turn · Reason · Identity · Story · Next Action · Conversations list |

**H0 decision:** Win one relationship Thread as a **projection**, not by forcing one database `conversations` row per `customer_id`. Contact-surface uniqueness remains. Do not invent `SmsInbox` or `AttentionItem` authority.

---

## Roadmap

```text
H0   The Six Ones
  ↓
Floor validation   ← required
  ↓
H1   Operational Inbox
H2   Conversation Workspace
H3   Compose Anywhere + Search Everywhere
```

| Slice | Delivers |
| --- | --- |
| **H0** | Prove The Six Ones — no chrome |
| **H1** | Turn nav with counts · Reason-led rows · never transport as row identity |
| **H2** | Identity → Story (+ Evidence) → Shop Context → Actions |
| **H3** | Compose: search → type → send · One search everywhere: customer · plate · VIN · phone · RO · invoice · estimate · appointment |

---

## Floor success

1. Opens Conversations — Turn counts, not channel piles.
2. Row shows relationship + Reason + age — knows why without opening.
3. Opens Thread — Identity, chronological Story, Shop Context, clear Next Action — within five seconds.
4. Finds Sarah / plate / RO without hunting (H3).
5. Calls & VM still available as evidence.

---

## Explicit non-goals

- Building a better SMS app
- Channel-first primary navigation
- Editable Turns
- AI Reasons or AI Stories that rewrite history
- CRM pipeline / campaign / tag theater in Shop Context
- Deleting the Calls & VM evidence library
- H1 polish before H0 + floor validation

---

## Document discipline

This file is frozen. Do not refine wording for elegance.

The next doctrine change must be earned by floor evidence: Did Sarah’s text, voicemail, and estimate approval collapse into one Thread? Did the advisor know what to do next without hunting?
