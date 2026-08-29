# Interaction Pattern Library v1

**Status:** Living — inventory of reusable interaction patterns  
**Not:** Governing doctrine (do not freeze this file like Interaction Craft Doctrine)  
**Parent:** [Interaction Craft vs Product Doctrine v1](ark-interaction-craft-vs-product-doctrine-v1.md) (`20670702`) — frozen; governs *how* reviews are done  
**Companions:** [Workspace Interaction Language](ark-workspace-interaction-language-v1.md) · Review notes under `docs/ecosystem/reviews/` · [Template-pressure notebook](reviews/_template-pressure-notebook.md)

---

## Separation of responsibilities

| Artifact | Role | Lifecycle |
| --- | --- | --- |
| Interaction Craft vs Product Doctrine v1 | Governs how ARK evaluates products | Frozen |
| Interaction Pattern Library v1 | Inventory of reusable interaction patterns | Living |
| Review notes | Evidence from individual products | Append-only |
| Notebook | Pressure against the review framework | Evidence only |
| ARK implementation | Ships validated patterns | Independent cadence |

**Patterns are the authority of this library. Competitors (and other products) are evidence.**

Doctrine evaluates. Library accumulates. Implementation ships. Doctrine never appears as a stage in the pattern lifecycle.

---

## Pattern lifecycle

```text
Review
  ↓
Observation
  ↓
(repeats across reviews?)
  ↓
Pattern Candidate
  ↓
(validated against ARK doctrine + evidence?)
  ↓
Validated Pattern
  ↓
(shipped in ARK?)
  ↓
Implemented Pattern
```

| Status | Meaning |
| --- | --- |
| `observation` | Seen in one review; not yet a library identity |
| `candidate` | Named pattern; awaiting validation or recurrence |
| `validated` | Passed doctrine gate; ready to ship when requested |
| `shipped` | Reimplemented inside ARK on at least one surface |
| `rejected` | Incompatible with ARK doctrine or failed floor test |

**Graduation:** one review → observation in the review note. Independent recurrence → promote/create pattern. Template gaps → notebook only (never edit frozen doctrine from a single awkward review).

---

## Identity rule

Treat patterns as first-class identities.

Wrong:

```text
Wrenchy
 └── Card glance rhythm
```

Right:

```text
Pattern: Card Glance Rhythm
 ├── Sources: Wrenchy · (Tekmetric?) · (Shopmonkey?)
 ├── Evidence count
 ├── Status
 ├── Doctrine gate
 ├── ARK surfaces
 └── Ship history
```

---

## Pattern schema

Every pattern entry uses:

| Field | Purpose |
| --- | --- |
| **Outcome** | What the operator can do faster / see clearer |
| **Sources** | Products that evidenced the craft (not owners) |
| **Evidence count** | Independent product sources that show this craft (running score) |
| **Doctrine gate** | Why this is borrowable under ARK doctrine |
| **Surfaces** | Where ARK may apply it |
| **Status** | Lifecycle state |
| **Convergence** | Other ARK surfaces that gained (or should gain) the same pattern |
| **Ship history** | Dates / PRs when implemented |
| **Review links** | Evidence |

**Library metrics (not doctrine):**

| Metric | Question |
| --- | --- |
| **Evidence count** | How many independent products evidenced this pattern? |
| **Convergence** | Does the same shipped pattern improve more than one ARK surface? |

**Evidence-count reading:**

| Count | Meaning |
| --- | --- |
| 1 | Interesting observation |
| 2–3 | Strong interaction candidate |
| Repeated across unrelated products | Likely durable interaction principle |

After each completed review, increment **Evidence count** and append the product to **Sources** when the same craft appears. Do not invent sources.

---

## Evidence scoreboard

Running totals — update when a review closes.

| Pattern | Evidence count | Sources |
| --- | --- | --- |
| Card Glance Rhythm | 1 | Wrenchy |
| Workboard Scan Density | 1 | Wrenchy |
| Persistent Context | 1 | Wrenchy |
| Card Hover Projection | 1 | Wrenchy |
| Column Decision Counts | 1 | Wrenchy |
| Keyboard-first Operations | 0 | — |
| Inline Approval Summary | 0 | — |
| Timeline Narrative | 0 | — |

Zero-count rows are placeholders for craft that may appear in Tekmetric / AutoLeap / etc. — not anticipatory wishlist items until a review evidences them.

---

## Patterns

### Card Glance Rhythm

| Field | Value |
| --- | --- |
| **Outcome** | Advisor answers Who / Vehicle / Why / Next / Money / Age without opening the RO |
| **Sources** | Wrenchy (worked example) |
| **Evidence count** | 1 |
| **Doctrine gate** | Workboard Card acceptance; board = decisions; junk-drawer rule |
| **Surfaces** | Workboard (primary) |
| **Status** | `shipped` — advisor home board (full Who→Age); triage queue Who→Next→Age |
| **Convergence** | Workboard → later Attention row density if earned |
| **Ship history** | 2026-07-19 — shipped on advisor job board; **reverted same day** — regressed marketed Tekmetric card anatomy (`job-board.png`). Pattern retained in library; do not re-apply to Job Board without floor evidence that beats the screenshot contract. |
| **Review links** | [Doctrine worked example — Wrenchy](ark-interaction-craft-vs-product-doctrine-v1.md#worked-example-wrenchy) (in frozen doctrine) |

### Workboard Scan Density

| Field | Value |
| --- | --- |
| **Outcome** | Dense, calm card rhythm; scan without SaaS whitespace or chaos |
| **Sources** | Wrenchy |
| **Evidence count** | 1 |
| **Doctrine gate** | product doctrine; borrow density, reject workflow-as-home |
| **Surfaces** | Workboard |
| **Status** | `shipped` — denser card padding/gap |
| **Convergence** | Workboard → Attention / Intake lists if earned |
| **Ship history** | 2026-07-19 — shipped with Card Glance Rhythm; density CSS reverted with Job Board restore |
| **Review links** | Wrenchy worked example |

### Card Hover Projection

| Field | Value |
| --- | --- |
| **Outcome** | Richer projection on hover without opening the RO; not another authority |
| **Sources** | Wrenchy |
| **Evidence count** | 1 |
| **Doctrine gate** | Projection Rule — package once; never become truth |
| **Surfaces** | Workboard |
| **Status** | `candidate` — ship after glance/density if floor still needs it |
| **Convergence** | Workboard only until proven elsewhere |
| **Ship history** | — |
| **Review links** | Wrenchy worked example |

### Persistent Context

| Field | Value |
| --- | --- |
| **Outcome** | Information required continuously while performing work stays visible without scroll/nav |
| **Sources** | Wrenchy (sticky money rail as one instance) |
| **Evidence count** | 1 |
| **Doctrine gate** | Persistent Context postures: Financial · Approval · Communication · Workflow — not KPI theater |
| **Surfaces** | RO right rail (primary) |
| **Status** | `shipped` — RO rail posture band |
| **Convergence** | RO right rail → Customer drawer (later) → Technician workspace (later) |
| **Ship history** | 2026-07-19 — Sticky four-posture band (Workflow · Approval · Communication · Financial); existing posture only |
| **Review links** | Wrenchy worked example |

### Column Decision Counts

| Field | Value |
| --- | --- |
| **Outcome** | Column headers show counts that aid triage; never justify infinite piles |
| **Sources** | Wrenchy |
| **Evidence count** | 1 |
| **Doctrine gate** | Junk drawer rule — count yes; infinite Estimates no |
| **Surfaces** | Workboard |
| **Status** | `candidate` |
| **Convergence** | Workboard |
| **Ship history** | — |
| **Review links** | Wrenchy worked example |

---

## Source focus (review queue — not pattern ownership)

Primary learning objective per product. Reviews fill notes; patterns get Sources appended.

| Order | Product | Primary learning objective | Don't steal |
| --- | --- | --- | --- |
| Done (example) | Wrenchy | Scan density, card rhythm, persistent context | Workflow mental model |
| 1 | Shopmonkey | Advisor interaction, estimate UX | Data model |
| 2 | Tekmetric | Operational speed, keyboard flow | RO architecture |
| 3 | AutoLeap | Customer communication flow | CRM-first thinking |
| 4 | Mitchell Manager | Technician execution, labor integration | Legacy navigation |
| 5 | Shop-Ware | Inspection + authorization UX | Flat information hierarchy |
| 6 | Fullbay | Dispatch / work assignment | Fleet assumptions |

### Cross-industry sources (in scope)

Not automotive peers — interaction evidence for ARK surfaces:

| Surface need | Study |
| --- | --- |
| Notifications | Slack |
| Inbox / messaging craft | Superhuman · iMessage · AutoLeap |
| Search / command | Linear · VS Code |
| Onboarding | Stripe |
| Customer status (“what's happening to my thing?”) | Tesla · Rivian · Apple repair · UPS |
| Appointments | Calendly · Google Calendar |
| Reporting | Stripe · GitHub |
| Mobile density | Linear · Notion · Superhuman |

Same Borrow / Reject discipline. Same evidence gate.

---

## Surfaces index

Patterns may list multiple surfaces. Index for navigation only — surfaces do not own patterns.

| Surface | Patterns (current) |
| --- | --- |
| Workboard | Card Glance Rhythm · Workboard Scan Density · Card Hover Projection · Column Decision Counts |
| Repair Order | Persistent Context |
| Communications | — (await AutoLeap / messaging reviews) |
| Customer Portal | — (await tracking-product reviews) |
| Scheduling | — |
| Mobile | — |
| Reporting | — |
| Command palette | — |

---

## Validation hypotheses (library + reviews)

1. **Coverage** — every review fits the frozen template without new sections  
2. **Consistency** — different reviewers reach comparable Borrow / Reject conclusions  
3. **Reuse** — Persistent Context, Workboard Card, Borrow/Reject recur across products  
4. **Convergence** — shipped patterns improve more than one ARK surface over time  

---

## Implementation gate (Workboard and later)

First (and each) pattern-ship PR should be expressible as:

> Implements validated interaction patterns from the Pattern Library. No authority changes. No workflow changes. No new projections.

If any change cannot honestly fit that sentence, it belongs in a later PR.

**Job Board visual contract:** `public/assets/cloud/product/job-board.png` (and live Cloud homepage) — RO left · status right · customer · vehicle · money · promise · age. Do not replace that anatomy without explicit acceptance.

**Persistent Context** (RO rail postures) remains shipped. Card Glance Rhythm is **not** on the Job Board after 2026-07-19 restore.

---

## Discipline

- Library grows from **evidence**, not anticipation.  
- No screenshots / walkthrough → review stays stubbed; no invented craft.  
- Prefer operational surfaces over marketing pages (see [reviews/README.md](reviews/README.md)).  
- Do not edit Interaction Craft Doctrine v1 from review pressure — use the notebook.  
- Ship cadence is independent: validated ≠ automatically scheduled.  
- After each review: update **Evidence count** + **Sources** on matching patterns.
