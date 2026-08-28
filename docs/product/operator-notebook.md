# Operator Notebook

*Thoughts ARK missed.*

**For the shop floor** — not a feature backlog, not a roadmap committee. Engineering internally calls each fixable entry a **cognitive bug**. Same artifact, different audience.

**Mission:** Every unnecessary thought an operator has is a product bug — one ARK could eliminate without taking away the business decision.

| Thought | ARK should know? |
|---------|------------------|
| Which bay is this vehicle in? | Yes |
| Did the customer view the estimate? | Yes |
| Should we replace both wheel bearings? | **No** — advisor still decides |

---

## Product learning cycle

The shop is the product research lab. No surveys. No feature voting.

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

**Roadmap:** [operator-steps-removed.md](../engineering/operator-steps-removed.md) — disappearing thoughts, not feature pillars.

**Standup:** What thought disappeared yesterday? Can we prove it on the floor?

Protect this loop. It cannot be replicated from analytics alone.

---

## Protect this notebook

Every entry is a real operator thought that escaped ARK — the highest-value product work in the project. Capture on the floor: notebook, voice memo, or a line in the table below.

**Do not** turn entries into a Jira backlog. Tie shipped fixes to [operational certifications](./operational-certifications.md).

---

## How to log

When you catch *"It would be nice if…"* or *"I wonder if…"* — write the **thought**, not the feature.

| Column | Meaning |
|--------|---------|
| **Thought I had** | Verbatim — what escaped ARK |
| **Frequency** | **Rare** · **Daily** · **Hourly** — prioritize Daily/Hourly first; no scoring algorithm |
| **ARK should?** | Yes / No (business judgment = No) |
| **Certification** | Which cert turns greener when fixed |
| **Status** | open · fixed · wont-fix |

---

## Log

| Date | Thought I had | Frequency | ARK should? | Certification | Status |
|------|---------------|-----------|-------------|---------------|--------|
| | Did Sarah view the estimate? | Daily | Yes | Orientation | open |
| | Which bay has this truck? | Daily | Yes | Operations | open |
| | Did Molly already answer this text? | Daily | Yes | Portable Station | open |
| | Which phone is ringing? | Hourly | Yes | Front Counter | open |
| | Should I recommend both bearings? | Rare | No | — | human judgment |

*(Append rows. Do not delete — mark fixed with date and [certification proof](./certifications/).)*

---

## Idea gate (before we build)

Collapse every proposal to one sentence: **What thought disappears if we build this?**

If the answer is **none**, it probably isn't the right thing this week.

| Question | If we can't answer… |
| --- | --- |
| What operator thought are we eliminating? | Push back |
| Which certification does it move? | Push back |
| Can we prove it on the floor? | Push back |

**Could ARK have anticipated this?**

- **No** → leave it with the human (business judgment).
- **Yes** → Where does the truth already exist? Which orientation should surface it? Which operator step disappears? Which certification moves?

Examples:

- Not *"Should Portable Station have another tab?"* → *"What thought is Edward having that Portable Station should eliminate?"*
- Not *"Should we build BLF now?"* → *"Does BLF eliminate a daily operator thought today, or is another certification more valuable?"*
- Not *"Should Orientation show another widget?"* → *"Does this stop someone from reconstructing the situation?"*

## Technical debt (ARK)

Not old code · missing tests · legacy architecture.

**Highest-interest debt:** any place where the operator **repeatedly thinks something ARK could already know.**

---

## Thoughts removed (outcome)

One fix may remove clicks, screens, training, and support calls — measure the **outcome**, not the mechanism.

| Fixed | Operator no longer thinks about… |
|-------|----------------------------------|
| ⬜ | MAC addresses |
| ⬜ | SIP credentials |
| ⬜ | Where to provision the phone |
| ⬜ | Reconstructing RO story before acting |
| ⬜ | Which conversation needs a reply |

---

## Compass questions

**Does ARK know this before the operator has to?**

**What did ARK fail to anticipate?** (Cleaner than "what thought escaped" — log as escaped cognition; close the notebook when professional judgment belongs to the operator.)

When commit history reads like *Front Counter Operationally Certified* and *Push opens directly into context* — not architecture essays — the platform is learning from the work itself.
