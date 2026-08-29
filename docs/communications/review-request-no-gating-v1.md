# Review Request — No Gating v1

**Status:** Frozen · Binding  
**Companions:** [review-schema-solicitation-compliance-v1.md](../growth/review-schema-solicitation-compliance-v1.md) · [ark-earned-authority-v1.md](../ecosystem/ark-earned-authority-v1.md)

## Principle

**ARK never gates public reviews.**

Every eligible customer receives the **same** opportunity to leave an honest review. Private feedback and public reviews are **independent actions**, not conditional branches.

## Allowed

- Advisor chooses whether and how to **send** a review request (Text / Email / both / Not now)
- One outbound message that always includes:
  - Honest feedback framing
  - The shop Google review link (from settings)
  - An equal **Contact Us** path (shop contact page / phone)
- ConversationMessage authority (`metadata.kind = review_request`)
- Later: separate customer satisfaction survey **days after pickup**, never before or instead of the public review link

## Forbidden

- Star rating first, then route by score
- “Were you happy?” before showing Google
- Happy → Google / unhappy → private-only
- Blocking, hiding, or delaying the Google link based on sentiment
- Incentives for Google reviews
- Asking for five stars or suggested review wording
- Building review gating “just this once” for reputation management

## Customer message shape (v1)

```text
We'd love your honest feedback.
[ Leave a Google Review ]   ← same for everyone
Having an issue with your repair?
[ Contact Us ]              ← same for everyone
```

No sentiment branch. The customer chooses.

## CSAT (future — not Review Request)

Private shop feedback (how did we do?) may exist as its own Conversation Intent later.

Rules when earned:

- Not tied to Google
- Not shown before the review opportunity
- Not a gate that decides who gets the Google link
- Improves the shop; does not filter public reputation

## Why

Fits Google’s expectations for business review solicitation, protects the Google Business Profile, and matches Demo Auto Repair: **Accurate Diagnostics. Honest Repairs.** — ask for an honest review, not a managed score.
