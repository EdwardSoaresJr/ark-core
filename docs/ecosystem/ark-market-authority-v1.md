# Market Authority v1

**Status:** Frozen — 2026-07-07 · **Do not extend v1 Growth Pressure code until realigned to this model**  
**Not:** Marketing doctrine · SEO playbook · review management · a score or ranking  
**Applies to:** Business health projections · Owner Today · Growth Pressure · any surface that interprets market trust

---

## Naming — why "Market Authority"

ARK already uses **authority** in the DDD sense: `RepairOrder` owns approved work, `Vehicle` owns vehicle truth, `Appointment` owns scheduling.

**Market Authority** is different: the trust the **market** grants the business. A separate word prevents collision with authority everywhere else in the codebase and in conversation.

**Earned Authority** ([ark-earned-authority-v1.md](./ark-earned-authority-v1.md)) governs **publication** — when knowledge may leave the shop.  
**Market Authority** governs **business health** — whether the business is becoming more trusted.

Same family. Different job.

---

## The three frozen statements

### Law

**Operational excellence creates market authority opportunities. Markets determine whether those opportunities become market authority.**

### Corollary

**Businesses control opportunities and investments. Markets control outcomes.**

| Agency | Examples |
| --- | --- |
| **Business (opportunities & investments)** | Excellent repair · honest advice · review invitation · referral mention · follow-up · community involvement · ads · coupons |
| **Market (outcomes)** | Review received · referral · repeat visit · fleet renewal · brand search · community recommendation |

Ads and coupons are **investments**, not sins. They rent attention. Sometimes attention becomes market authority; sometimes it does not. ARK must not imply rented attention is "bad" — only **different economics** from compounding investments.

### Meta-principle — Becoming

**Every projection exists to answer one question: What is becoming true?**

Not a module. Not a nav item. A meta-principle across Operations, Communications, Growth, Market Authority, inventory, staffing, and profitability.

| Traditional SMS | ARK |
| --- | --- |
| What happened? | What is becoming true? |
| Five repair orders closed | Market authority opportunities are not consistently becoming witnessed trust |
| 847 impressions | Selection pressure is rising while capture at close is flat |

Customer Decision Pressure is **becoming**. Market authority growth is **becoming**. Technician reliability, advisor effectiveness, inventory instability — same hidden question, different vocabulary.

---

## Deeper law

**Businesses cannot grant market authority to themselves.**

You cannot declare "we're the best mechanic." You cannot buy "trusted." You cannot code "respected." Only the market grants those things.

Until an external witness observes satisfaction, the business holds **potential**, not market authority. That explains why reviews, referrals, repeat customers, and reputation matter — without privileging any single witness (Google, Apple Maps, CarRepair411, ARK Network, etc.).

---

## Causal chain — non-negotiable

```
Operational excellence (repair completed successfully)
        ↓
Market Authority Opportunity Created     ← shop-side; NOT created by asking for a review
        ↓
Possible investments                     ← shop chooses
  · Invite customer to share experience
  · Mention referral program
  · Follow-up call
  · Community relationship
  · Ads / coupons (rented attention)
        ↓
Possible market witnesses                ← market reports
  · Google review · Referral · Repeat customer
  · Fleet renewal · Community mention · …
        ↓
Market Authority Observations              ← interpretive truth (ARK infers)
        ↓
Business Health Projections                ← Owner Today, Growth Pressure, …
```

### Critical invariant

**`MarketAuthorityOpportunityCreated` must not depend on asking for a review.**

The opportunity exists because **work was completed successfully** — not because the advisor clicked a button.

Otherwise ARK accidentally teaches: *asking for reviews creates opportunities.* It doesn't. **Excellent work creates opportunities.** The review invitation is one **investment** against an opportunity that already exists.

If a customer declines a review, the opportunity was still created. The investment was extended; the outcome was "not yet witnessed." That is observation, not failure.

---

## Three event domains

Matches ARK everywhere else: facts → observations → projections.

| Domain | Who owns it | Examples |
| --- | --- | --- |
| **Internal events** | Business knows | RO closed (paid) · estimate approved · vehicle picked up · invitation extended |
| **Market events** | Business does not control | Review received · referral · repeat visit · fleet renewal · Facebook recommendation |
| **Interpretations** | ARK infers | Market authority compounding · review capture leaking · selection pressure emerging |

**Market authority itself is never a table.** It is inferred — like Customer Decision Pressure. Not a column. Not a score. An interpretation of events over time.

Target event vocabulary (when earned — not v1 schema mandate):

| Event | Domain |
| --- | --- |
| `MarketAuthorityOpportunityCreated` | Internal — on successful paid close (or equivalent satisfaction signal) |
| `MarketAuthorityInvestmentMade` | Internal — invitation extended, referral mentioned, … |
| `MarketWitnessObserved` | Market — review, referral, return, … (references opportunity) |

Witnesses reference the opportunity they observe. One parent truth; many child outcomes.

---

## Doctrine vs interface

**Doctrine is for the model. Interfaces are for humans.**

Advisors must not learn "market authority opportunity" at the counter.

| Surface | Language |
| --- | --- |
| **Advisor (paid close)** | "Did you invite the customer to share their experience?" — Yes · Customer declined · Not appropriate · I'll do it later |
| **Model / events** | `MarketAuthorityOpportunityCreated` · `MarketAuthorityInvestmentMade` |
| **Owner / projections** | Market pressure · becoming · missed opportunities · advisor breakdown |

---

## Witnesses — not channels

Google is a **witness**, not market authority itself.

Witnesses report evidence. They do not create trust. Examples today and someday:

- Google · Apple Maps · Referrals · Repeat customers · Fleet renewals
- Community recommendations · CarRepair411 · ARK Network reputation

Growth Pressure should eventually ask: **did market authority increase?** — with witnesses as evidence, not as the thing being scored.

---

## Companion principle (measurement order)

**ARK should never measure an outcome until it has identified the opportunity that produced it.**

Other systems start with "count reviews." ARK starts with **"when was trust possible?"**

Opportunities belong to the business. Outcomes belong to the market. That separation keeps ARK causal — not another analytics dashboard.

Sequence: **Observe → identify opportunity → measure outcome → interpret becoming.** Same as Pressure First.

---

## What not to build (until observation earns it)

- Selection Pressure categories ("went elsewhere", "too expensive") — wait for repeated advisor sentences on the floor
- Market authority score, ranking, or gamification
- Nags, forced checkboxes, or 100% capture targets as goals
- Ledger totals treated as truth (v1 ledger is disposable projection)
- "Authority" without "Market" prefix in product copy where market trust is meant
- `OpportunityCreated` gated on review-request checkbox

**The metric reveals pressure. It is not the goal.** More trusted businesses — not 100% invitation logging.

---

## v1 implementation note (2026-07-07)

Shipped before this freeze:

- Review request fields on paid close
- `GrowthPressureProjection` · Owner Today market pressure panel
- Effort vs earned ledger counts

**Known misalignment with frozen doctrine:**

- Opportunity is implied at close but not emitted as `MarketAuthorityOpportunityCreated` independent of invitation
- Ledger counts mix projection convenience with causal model
- Vocabulary still says "review request" in places

**Do not extend ledger or add scores.** Next code pass should realign to opportunity-first events and advisor-facing invitation language — not deepen v1 shortcuts.

---

## Companion doctrines

| Doctrine | Relationship |
| --- | --- |
| [ark-earned-authority-v1.md](./ark-earned-authority-v1.md) | Publication exit gate — orthogonal; names "Earned Authority" not market trust |
| [ark-truth-stack-v1.md](./ark-truth-stack-v1.md) | Events → observations → projections |
| ark-observations.mdc | Interpretive truth — Market Authority observations are observations |
| ark-pressure-first.mdc | Observe before enforce; measure outcomes after opportunities |
| ark-projection-rule.mdc | Projections summarize; market authority is never stored as projection-only truth |
| ark-explainability-doctrine.mdc | What / Why / Show me for every becoming claim |
| [ark-constitution-v1.md](./ark-constitution-v1.md) | Coherence over capability |

---

## Closing

> **Operational excellence creates market authority opportunities. Markets determine whether those opportunities become market authority.**

That sentence survives Google, Apple, ARK Network, and whatever comes next. Freeze it before writing the next line of code.
