# ARK Constitution v1

**Status:** Constitutional — constrains future decisions  
**Not:** A roadmap, architecture diagram, or style guide  
**Purpose:** When ideas arrive, the first answer is often *"Not yet"* — not because the idea is bad, but because it has not earned its place.

---

## Product identity

**ARK exists to reduce the amount of thinking required to operate a shop without reducing the shop's control over business decisions.**

Orientation reduces reconstruction. Stations reduce searching. Operator intent removes configuration. Infrastructure discovery removes installation. Certifications prove trust. The [Operator Notebook](../product/operator-notebook.md) closes the loop: shop → thought → cert → code → shop.

ARK is an **operating system for an automotive shop**. Phones, Asterisk, Twilio, Flutter, and workstations are implementation — not the product.

**Product identity (frozen):** [ark-product-identity-v1.md](ark-product-identity-v1.md) — ARKv2 = operations workspace · Companion = communications workspace · same authorities, neither becomes the other.

---

## Constitutional principle

**Coherence over capability.**

Whenever there is tension between adding another capability and keeping the product internally consistent, **coherence wins**.

Recent decisions that follow this principle:

- No separate Finding table — evidence lives on the inspection item
- Conversations instead of SMS authority
- RO Workspace instead of more screens
- Settings instead of hardcoded behavior
- Push as transport, not authority
- Asterisk as transport, not workflow
- Mobile shaped from projections, not duplicated logic
- No desktop parity until the phone proves the workflow
- Notebook before instrumentation
- Repeated sentences before AI

ARK should become more capable by becoming **more coherent**, not by accumulating features.

---

## Four grammars

| Grammar | Governs | Protecting question |
| --- | --- | --- |
| **Authority** | Truth | What is true? |
| **Projection** | Audience | Who needs to see it? |
| **Workspace** | Interaction | Which operator question does this answer? |
| **Evolution** | Product improvement | Which repeated sentence earned this? |

There is no fifth grammar. **Coherence over capability** sits above them as constitutional principle — not another layer.

Full interaction language: [ark-workspace-interaction-language-v1.md](./ark-workspace-interaction-language-v1.md)

## Truth stack

How ARK stores, summarizes, explains, and justifies operational reality:

```
Events are truth.
Projections summarize truth.
Narratives explain truth.
Evidence justifies truth.
```

Full doctrine: [ark-truth-stack-v1.md](./ark-truth-stack-v1.md)

**Projection summarizes truth. It never becomes truth.** Every projection is disposable and rebuildable from authority. Narratives (Briefing, Journey story) compose projections — they do not invent truth. If evidence cannot be shown, a claim is not operational truth.

See also: doctrine `ark-projection-rule.mdc` · doctrine `ark-explainability-doctrine.mdc`

---

## Hierarchy of evidence

What kind of argument carries weight when reshaping the product:

```
1. Operational truth (Authority)
2. Observed behavior (Notebook)
3. Repeated sentences (Evolution)
4. Instrumentation (Confirmation)
5. Opinion
```

Opinion is last — not worthless, but it must be grounded by the layers above before it changes truth, interaction, or intelligence.

Instrumentation **confirms** notebook observations. It does not replace them.

---

## Product purpose

> **The purpose of ARK is not to digitize shop operations. It is to reduce hesitation while preserving momentum, using operational truth that remains explainable at every step.**

Every meaningful addition should:

- Preserve operational truth
- Answer a **repeated** operator question
- Reduce hesitation
- Preserve momentum
- Remain explainable

When those goals conflict with shipping more capability, **coherence takes precedence**.

---

## The evolution loop

```
Authority → Projection → Workspace
                ↓
      Operator behavior → Repeated sentences → Tightening → Workspace
```

**Behavior never rewrites truth.** Interaction improves. Authority earns trust first.

> Every workspace should earn its shape through repeated operator behavior, not anticipated complexity.

---

## Earned Intelligence

**Anti-pattern:** Premature Intelligence — answering a question nobody is asking yet.

**Answer to "We should add AI here":**

> **"What repeated sentence earned it?"**

If there is no good answer yet, the idea goes into the **notebook**, not into `main`.

That does not kill innovation. It **channels** it.

See doctrine `ark-earned-intelligence.mdc.`

---

## Earned Authority

**Anti-pattern:** Publishing knowledge the shop has not earned — SEO filler, competitor mimicry, fabricated repair counts, AI copy without operational trace.

**Answer to "We should publish / say / email this":**

> **"What operational truth stands behind it?"**

If the claim cannot trace to a verified repair, repeated customer question, documented procedure, measured data, or explicit shop policy — it does not leave the system.

Earned Authority is the **gatekeeper** between internal truth and every outbound surface (website, Companion, AI, ARKademy, reports, social, email).

See [ark-earned-authority-v1.md](./ark-earned-authority-v1.md) · doctrine `ark-earned-authority.mdc.`

---

## Significant change review

Every significant PR answers four questions. If it cannot, it is probably not ready.

| Grammar | Question |
| --- | --- |
| Authority | What truth changed? |
| Projection | Who sees this differently? |
| Workspace | Which operator question does this answer? |
| Evolution | Which repeated sentence earned this? |

See doctrine `ark-pr-doctrine-review.mdc.`

---

## Milestones: operator outcomes

Not "Mobile Phase 3." Outcomes the team can understand and measure in the notebook:

| Outcome |
| --- |
| Technician never leaves the RO workspace |
| Advisor never searches for an active vehicle |
| Customer never wonders whether the shop responded |
| Manager never asks who needs attention |

---

## The question going forward

Stop asking **"What's next?"**

Ask **"What has earned the right to exist?"**

For almost every new idea, the first answer should be **"Not yet."** That is a sign of a maturing platform.

When someone says *"We should build…"* the next response is not yes or no:

> **"What evidence would earn that?"**

That is the habit behind the constitution — more important than the document itself.

---

## Guardrail, not gate

A constitution can become either:

| | Guardrail | Gate |
| --- | --- | --- |
| **Effect** | Keeps you from driving off the road | Keeps you from going anywhere |
| **ARK must be** | The first | Never the second |

### Constrain implementation, not observation

**Everything is allowed to be observed. Almost nothing is allowed to be shipped.**

If Landon says *"I wish the camera stayed open"* — that does **not** mean build camera persistence. It means **write it down**.

The notebook is the **safest place in ARK**. Ideas accumulate there freely because they have not earned implementation yet. That keeps the constitution from suppressing curiosity.

### Vision vs evidence

The hierarchy of evidence governs **workflow and product tightening**. It does not reopen settled **architecture**.

**Vision may outrank evidence at the architectural level** — once. Examples that were architectural, not sentence-driven:

- Authority over JSON blobs
- Projection instead of duplicated UI logic
- Transport replacing provider-specific workflows
- Workspace instead of mobile vs desktop

After architecture exists, **workflow is earned through observation**. Innovation at the foundation; discipline at the surface.

---

## Three eras

| Era | Question | Examples |
| --- | --- | --- |
| **1 — Model reality** | Can we model reality? | Authority, truth, relationships, storage |
| **2 — Understand reality** | Can we help people understand reality? | Observations, boards, projections, conversations, workspaces |
| **3 — Improve from reality** | Can reality improve the product? | Notebook, repeated sentences, evolution, constitution |

ARK is in Era 3. The inventing phase ends here.

Six months from now, the biggest wins may sound boring:

- *"I don't even think about the app anymore."* — Landon
- *"I just open ARK."* — Molly
- *"We didn't build that because nobody ever asked for it twice."* — You

That is what mature software sounds like.

---

## Notebook: Interesting, Not Earned

Keep one page that is **never allowed to become a ticket**:

**Interesting, Not Earned**

A home for ambitious ideas that have not earned implementation:

See [workspace-evolution-notebook.md](../operations/workspace-evolution-notebook.md) for the operational notebook: **Repeated Sentences**, **Silent Successes**, **Interesting, Not Earned**, Landon's three questions, and the Era 3 rhythm.

---

## Closing

> **ARK should become more capable by becoming more coherent, not by accumulating features. Every meaningful addition should preserve operational truth, answer a repeated operator question, reduce hesitation, preserve momentum, and remain explainable. When those goals conflict, coherence takes precedence over capability.**

Read this before merging a major feature.

Customers will not read the doctrine. They will feel the **consistency** every time they use ARK. That consistency — earned coherence — is among ARK's strongest competitive advantages as the platform grows.

---

## Companion documents

| Document | Role |
| --- | --- |
| [ark-workspace-interaction-language-v1.md](./ark-workspace-interaction-language-v1.md) | Interaction grammar, design tests, evolution loop |
| ark-pressure-first.mdc | Visibility before enforcement |
| ark-authority-adoption.mdc | Observe adoption before automate |
| ark-earned-intelligence.mdc | Intelligence after repeated sentences |
| [ark-earned-authority-v1.md](./ark-earned-authority-v1.md) | Exit gate — when knowledge may leave the shop |
| ark-pr-doctrine-review.mdc | Four-question PR lint |
| [workspace-evolution-notebook.md](../operations/workspace-evolution-notebook.md) | Era 3 notebook — observe, record, ship reluctantly |
