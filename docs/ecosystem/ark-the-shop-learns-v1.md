# The Shop Learns v1

**Status:** Product doctrine v1  
**Not:** A website doctrine, SEO playbook, AI strategy, or knowledge-management product pitch  
**Applies to:** Every ARK capability — operations, diagnostics, website, portal, ARKademy, training, reports, marketing surfaces, future benchmarking

Many systems talk about knowledge management, knowledge graphs, or organizational learning. ARK is not claiming novelty in those categories. What is distinctive is **discipline**: how knowledge is allowed to exist, when it may compound, when it may evolve or retire, and when it may leave the shop.

This doctrine should still be correct if ARK had zero AI features tomorrow. If every sentence remains true without AI, the doctrine is fundamental — not a snapshot of today's tooling.

---

## North star

**ARK does not create knowledge.**

**ARK accumulates knowledge.**

Knowledge is not created when someone clicks Publish, when a paragraph is drafted, or when a repair order closes. Knowledge **accumulates** as the shop repeatedly observes reality — and only later becomes something the shop contributes, curates, and projects.

**Shop Knowledge** (the capability) is the **accumulated operational knowledge owned by the shop** — distinct from reference material imported from outside. The shop learns; ARK captures and projects without inventing.

> **Product framing:** ARK is an **operational knowledge system** — not "shop learning software." The software does not learn; the shop learns.

---

## Invariant

> Work is the only author.

Not AI. Not the advisor. Not marketing. Not engineering.

Everyone else **observes**, **contributes**, **reviews**, and **projects**.

This single sentence answers design questions about CMS, publishing, documentation, automation, and outbound copy: if the claim did not come from accumulated operational truth at this shop, it is not shop knowledge.

---

## Two foundational ideas

### 1. Knowledge has a lifecycle

Most systems assume knowledge is **authored**.

ARK assumes knowledge is **earned**.

Knowledge is not an object stored in a table. It is a **state** that evidence moves through:

```
Work
    ↓
Evidence
    ↓
Observation
    ↓
Accumulation
    ↓
Contribution
    ↓
Published Knowledge
    ↓
Audience
```

| Stage | Meaning |
| --- | --- |
| **Work** | Repair, inspection, conversation, estimate — operational activity on the floor |
| **Evidence** | Immutable facts from that work (finding, measurement, outcome, photo) |
| **Observation** | Interpretive truth — what a pattern means (*this matters*) |
| **Accumulation** | The **process** of repeated observations compounding — not yet "knowledge" as a product noun |
| **Contribution** | A human says *this belongs to the shop now* — curation, not publication |
| **Published Knowledge** | Reviewed shop truth projected to a specific audience |
| **Audience** | Website, portal, ARKademy, training, technician search, reports, GBP, social, benchmarking — **Google is one audience, not the destination** |

Evidence becomes observation. Observations accumulate. Accumulation, when sufficient, becomes knowledge **as a result** — not as something authored at the start.

### 2. Shop knowledge and reference knowledge

Not all knowledge in the shop is shop knowledge.

| Type | Origin | Examples |
| --- | --- | --- |
| **Reference knowledge** | External | Torque specs, OEM procedures, TSBs, recall notices, industry diagnostics |
| **Shop knowledge** | Accumulated operational truth | Cause distributions from closed jobs, shop diagnostic sequences, contributed case patterns, verified "what we see here" |

Reference knowledge has value. ARK may store and surface it. It is **not** shop truth — and must not be published as if the shop earned it through work.

If a claim did not come from accumulated operational truth at this shop, treat it as reference material, not shop knowledge.

---

## Accumulation, contribution, publication — one job each

| Step | Responsibility |
| --- | --- |
| **Accumulation** | Learning — patterns compound from repeated work |
| **Contribution** | Curation — *this belongs to the shop now* |
| **Publication** | Projection — choose audience and shape |

**Shop knowledge accumulates through repeated operational truth. Public knowledge is a reviewed projection of that accumulated knowledge.**

Contribution is **not** publishing. Contribution is **not** approval to go live. Contribution is the operator (or owner) saying a learning event belongs to shared shop knowledge. It may sit internally for months before any audience sees it. That is healthy.

Publication happens later — when accumulated, contributed knowledge is ready for a specific audience.

---

## Confidence

Between **accumulation** and **contribution**, ARK may track **confidence** — not to auto-publish, but to keep the system honest:

| Signal | Example confidence | ARK behavior |
| --- | --- | --- |
| One repair | Low | Evidence only — do not suggest contribution |
| Five similar repairs | Medium | Emerging observation — may surface internally |
| Fifty similar repairs | High | Accumulation may warrant contribution suggestion |

Not every repeated event becomes knowledge. Some are coincidences, anomalies, or trends that disappear. Confidence determines whether ARK **suggests** contribution — never whether it publishes without human review.

Companion: ark-pressure-first.mdc · ark-observations.mdc

---

## Knowledge is revisable

Shop knowledge is accumulated through operational truth. It remains valid **only while operational truth continues to support it**.

Knowledge should not be permanent. Doctrine must not become dogma. ARK has always resisted dogma.

**Example:** For years the shop observes coolant reservoirs on a specific vehicle line crack constantly. The manufacturer redesigns the part. The old observation becomes obsolete.

Contradictory evidence does not immediately erase accumulated knowledge. Instead it **lowers confidence** until new observations establish a replacement understanding.

Shop knowledge may therefore:

- **accumulate**
- **strengthen**
- **weaken**
- **split** (one pattern becomes two)
- **retire** (no longer supported by operational truth)

Knowledge is never immutable. Revision follows the same discipline as creation: clusters of evidence, outcomes, and human review — not one contrary data point or one opinion.

Companion: ark-doctrine-lifecycle.mdc

---

## Contribution as capability

**Contribution** is a first-class platform capability — not a single button on a closed RO.

Any authority object may **contribute** to shop knowledge:

- Repair order  
- Inspection  
- Conversation  
- Estimate  
- Warranty claim  
- Advisor note  

Contribution says: *this work taught the shop something worth keeping.*

Publication is a **downstream projection** — one contribution may eventually feed many audiences:

- Common Problem update  
- Featured media  
- Case study block  
- GBP draft  
- Social draft  
- Training library  
- ARKademy  

**Compute once (contributed truth); render many (audiences).** Companion: ark-projection-rule.mdc

Operator-facing language prefers **Contribute to Shop Knowledge** over *Promote to Public* — the shop is not "publishing"; it is contributing truth the platform may later project.

---

## A repair order is a learning event

A repair order is neither "closed and archived" nor automatically "knowledge graph input."

Sometimes it teaches nothing. Sometimes it changes how the entire shop thinks about a failure.

Significance thresholds (illustrative — tune per domain):

```
One unusual repair     → evidence
Five similar repairs   → emerging observation
Fifty similar repairs  → accumulation (high confidence)
Advisor contribution   → shop knowledge (curated)
Publication            → projection to an audience
```

Empty projection blocks on public surfaces (e.g. shop experience counts on Common Problem pages) are **earned software** — honest until accumulation warrants display. Empty is better than fabricated.

---

## Where features live

For years the question was: *Where should this feature live?*

Answer:

> **Where does the truth belong?**

Everything else is a projection.

Website, training, reports, and marketing are not separate systems. They are different **views of the same accumulated truth**.

---

## Litmus tests

Before shipping any feature that touches knowledge:

1. **Does this help the shop learn, or merely help it remember?**  
   Remembering is storage. Learning is accumulation.

2. **Does this strengthen operational truth, or merely create more information?**  
   Information is not automatically truth.

Before publishing any outbound claim, see [ark-earned-authority-v1.md](./ark-earned-authority-v1.md).

---

## Relationship to Earned Authority

| Document | Question |
| --- | --- |
| **The Shop Learns** (this doc) | How does knowledge **accumulate**, evolve, and retire inside the shop? |
| [Earned Authority v1](./ark-earned-authority-v1.md) | When may knowledge **leave** the shop? |

The Shop Learns describes the full lifecycle through Audience. Earned Authority is the **exit gate** for public and outbound surfaces — publication only after contribution and review.

---

## Companion doctrines

| Doctrine | Relationship |
| --- | --- |
| [ark-truth-stack-v1.md](./ark-truth-stack-v1.md) | Events → projections → narratives → evidence |
| [ark-earned-authority-v1.md](./ark-earned-authority-v1.md) | Publication exit gate; traceable outbound claims |
| ark-observations.mdc | Observation vocabulary before accumulation |
| ark-pressure-first.mdc | Observe → surface → measure before enforce |
| ark-earned-intelligence.mdc | Intelligence only after repeated sentences |
| ark-projection-rule.mdc | Project once; never become truth |
| ark-explainability-doctrine.mdc | What / Why / Show me for operational claims |
| ark-doctrine-lifecycle.mdc | Doctrine and shop knowledge revision when reality produces evidence |
| ark-operator-intent.mdc | Contribution grammar — intent in, implementation hidden |

---

## What this doc deliberately omits

Laravel, MySQL, Blade, CMS, website stack, Google — implementation details. This doctrine describes **how a repair shop becomes wiser over time**. That behavior should survive every technology change.

---

## Closing

**The shop learns; ARK captures and projects without inventing.**

Read this before designing Contribution workflows, before wiring aggregate projections, and before treating any surface as a place to *write* knowledge rather than *project* knowledge the shop has already earned.
