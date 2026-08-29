# Product Roadmap — Operator Steps Removed

**This is the roadmap.** Not Jira. Not GitHub Projects. Not milestone theater.

A sequence of **disappearing thoughts** — not Voice, Mobile, Orientation as feature pillars.

**Unit of progress:** permanent reductions in operator cognition that produce an operational capability the shop trusts.

Not features shipped · tickets closed · story points · velocity.

**Heuristic:** Don't make ARK more capable today. Make the shop require one less thought.

**Standup (first line):** What thought disappeared yesterday?

Follow-up: **Can we prove it on the floor?** If yes → flip the certification row green. If nobody can answer what disappeared, the software may have grown without the shop getting meaningfully better.

**Evening question:** What thought did we eliminate from someone's day today?

- If **one** → the platform got better.
- If **none** → the shop probably didn't, regardless of how much code shipped.

Notebook + loop: [operator-notebook.md](../product/operator-notebook.md) · Litmus: ark-pr-doctrine-review.mdc

## The loop

```text
Escaped operator thought
        ↓
Operator Notebook
        ↓
Anticipation failure (what ARK should have known)
        ↓
Implementation
        ↓
Operational Certification
        ↓
Operator never has that thought again
```

Good software reduces clicks. **Great software reduces training.**

**ARK learns by operating the shop** — not from support tickets alone. That is the evolution loop competitors rarely copy.

## Certification status

Every certification ends with one sentence:

> **What no longer requires training because ARK now anticipates it?**

| Certification | Status | Means | No longer need to teach |
| --- | --- | --- | --- |
| **Front Counter Operationally Certified** | 🟢 | Factory-reset phone → station ready → PSTN inbound/outbound on VVX; SMS on Twilio | SIP server, extensions, provisioning URLs, MAC entry |
| **Portable Station Phase 1 Engineering Certified** | 🔴 | Opens to orientation, not an inbox; push lands in context | "Let me check messages," which RO, did someone already answer |
| **Orientation Platform v1 Operationally Certified** | 🔴 | Unlock updates presence everywhere | What happened, who's waiting, what's blocking, who owns the next step |

Turn green on the **floor**, not in a checklist doc. Success six months out: *"Huh… I don't even think about that anymore."*

## Thoughts to remove (by certification)

**Front Counter** — until the station simply works:

- How do I provision this?
- Which extension?
- Why isn't this phone working?
- Who is signed in?

**Portable Station** — until Edward simply works:

- Let me check messages.
- Which RO was this?
- Did someone already answer?
- What deserves attention?

**Orientation** — until the operation simply works:

- What happened?
- Who's waiting?
- What's blocking this?
- Who owns the next step?

## Roadmap (scoreboard)

| Operator step removed | Certification advanced | Status |
| --- | --- | --- |
| No MAC entry | Front Counter | ✅ 2026-06-27 |
| No SIP UI | Front Counter | ✅ 2026-06-27 |
| No provisioning URL | Front Counter | ✅ 2026-06-27 |
| No "assign device" language | Front Counter | ✅ 2026-06-27 |
| No station selection (single-station shops) | Front Counter | 🎯 target |
| No manual phone login | Front Counter / Portable Station | ✅ 2026-06-27 |
| Push opens directly into context | Portable Station | 🎯 target |
| Unlock updates presence everywhere | Orientation | 🎯 target |

Add a row when a step is **gone for operators**, not when code ships. Master-admin escape hatches do not count.

## Next week — stories, not features

Success is experiential. Not "we implemented Asterisk." Not "Flutter has push."

| Day | Story |
| --- | --- |
| **Monday** | A factory-reset phone becomes Front Counter. |
| **Tuesday** | Edward walks away from the desk without leaving ARK. |
| **Wednesday** | A customer text interrupts with context, not an inbox. |
| **Thursday** | Molly never thinks about SIP again. |
| **Friday** | One real customer call answered entirely through ARK. |

Stories become certifications. Certifications become trust.

## What people will say (experiential advantages)

- *"Everything just seems to be where I need it when I need it."* → Orientation
- *"Setting up a new phone took two minutes."* → Operator intent + infrastructure discovery
- *"I never have to remember what I was doing before I answered the phone."* → Orientation Platform

Harder to copy than feature checklists.

## How to log progress

1. Verify on the floor.
2. Add a row to the roadmap table (step removed + certification).
3. When a certification is fully earned, flip 🔴 → 🟢 in the status table.
4. Optional one line in [IMPLEMENTATION_LOG.md](./IMPLEMENTATION_LOG.md).
