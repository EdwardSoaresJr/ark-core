# ARK Growth Doctrine

Growth exists to answer one question:

**How did this repair order come into existence?**

Traditional analytics stop at traffic.

ARK Growth continues through:

```
Visitor
→ Lead
→ Conversation
→ Appointment
→ Repair Order
→ Invoice
→ Revenue
```

Every feature in Growth must increase observability of customer acquisition.

## Product boundaries

| Product | Owns |
| --- | --- |
| **Growth** | Acquisition |
| **Operations** | Execution |
| **Voice** | Communication |
| **ARKademy** | Knowledge |
| **Arkify** | Infrastructure |

Products communicate through **events and contracts** — never direct coupling into another product's write paths.

## Measurement principle

**Growth measures completed work — not clicks.**

Page views, scroll depth, and CTA taps are signals on the path to revenue. They are not success metrics by themselves. A page that gets traffic but never produces work is a visibility problem. A page that produces high-value repeat customers is a strategic asset.

## Observer posture

Growth is an **observer**, not a gatekeeper.

- Growth must never block Operations workflows (close, post, invoice, collect).
- Growth must never mutate Operations authority (customers, repair orders, leads, conversations).
- Growth events and attribution records are **append-only**. Attribution is an accounting ledger — immutable once recorded.
- External integrations (Search Console, GA4, Google Business Profile, Bing) are optional adapters. The product must degrade gracefully when they are not connected.

## Revenue Explorer

The flagship surface is not "page analytics." It is the owner decision engine:

- Which pages produced real revenue?
- Which search terms correlate with high average repair orders?
- Which content converts vs. which content only attracts traffic?

That is business intelligence for auto repair — not an SEO dashboard.

## Phase sequence

```
Observe acquisition → Attribute closed work → Surface pressure → Recommend (when earned)
```

Intelligence and automation follow the same earned sequence as the rest of ARK. Do not skip to AI recommendations before attribution is trusted on the floor.

## Operational Journey (explainable narrative)

**Question:** How did this repair order come into existence?

Growth's flagship instance of the platform [Truth Stack](../ecosystem/ark-truth-stack-v1.md):

| Layer | Growth implementation |
| --- | --- |
| Product surface | **Operational Journey** |
| Projection | `OperationalJourneyProjection` |
| Evidence | `JourneyEvidenceItem[]` |
| Identity | Identity confidence — score + signals + facts |

Do not duplicate Truth Stack doctrine here. Growth-specific acquisition rules stay below; projection / explainability / briefing grammar live in ecosystem docs.

**Earned Authority:** Growth publishes only what the shop has earned. Public problem pages, outbound copy, and future shop-experience blocks must trace to operational truth. See [ark-earned-authority-v1.md](../ecosystem/ark-earned-authority-v1.md). Public marketing v1 closed 2026-07-06 — homepage frozen; new authority pages earn evolution.

## Next surfaces (sequence)

1. ~~Journey Evidence~~ — expandable milestones (shipped)
2. **Operations Briefing** — morning narrative for owners (*not* a dashboard)
3. **Operations grid** — dense living signals when data matures

Do not build a Growth Command Center dashboard. Build an **Operations Briefing** that tells the owner what deserves attention yesterday.
