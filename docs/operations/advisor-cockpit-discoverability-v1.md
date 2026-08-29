# Advisor Cockpit & Discoverability v1

**Status:** Active — build priority for Demo Auto Repair  
**Sequence:** Discoverability → Observation → Consolidation → Inspection v1.5  
**Companions:** [Inspection Authority v1.5](../inspection/inspection-authority-v1.5.md) · [Repair Order Discovery Contract](repair-order-discovery-contract.md) (technician scope — separate concern)

---

## The real gap

ARK is optimized for **understanding the shop**, not **seeing the shop**.

| Tekmetric (first glance) | ARK today (first glance) |
|--------------------------|--------------------------|
| How many cars do I have? | What's the constraint? |
| Which ones are waiting? | What's the pipeline? |
| Which ones are expensive? | What's the recommendation? |
| Which ones are angry? | (buried in lanes) |

**Discoverability** is the biggest gap — not intelligence, not architecture, not doctrine. ARK already knows a lot. The next challenge is making the right things **impossible to miss**.

---

## Customer conversation-centric (not RO-centric)

The Molly test is not *find RO #1584*. It is:

> **Customer calls. Everything starts there.**

Today the board thinks:

```
RO → Customer → Vehicle
```

Advisors think:

```
Customer → Vehicle → RO
```

Phase A card hierarchy:

```
LEE WRIGHT                          ← primary scan target

2018 Ram 2500
RO #1584

Waiting Approval                    ← status badge, not column
$9,027
Estimate Viewed

4 days old
Last contact 2 days ago

[ Open ]  [ Call ]  [ Text ]
```

**Not** RO number first. **Not** vehicle without customer. The service counter starts with *who is on the phone*.

---

## Attention zones (not lifecycle lanes)

A completely flat list solves Molly (discoverability) but may fail Edward at 20–30 active cars.

Phase A uses **attention zones** — visual grouping, not Kanban, not lifecycle columns:

```
NEEDS ACTION (7)
  Lee Wright · 2018 Ram 2500 · …
  Mary Jones · …
  Bob Wilson · …

ACTIVE WORK (12)
  …

READY PICKUP (3)
  …
```

| Zone | Edward question | Not |
|------|-----------------|-----|
| **Needs Action** | What customers need me? What can hurt me today? | Lifecycle lane "Awaiting Approval" |
| **Active Work** | What vehicles are active? | Kanban drag column |
| **Ready Pickup** | What's waiting to leave? | Separate page |

At 8:00 you are not asking *show me every car equally*. You are asking *show me the cars that can hurt me today* — then scan the rest.

Zones are **attention-based**, derived from existing triage pressure and observations — not `RepairOrderStatus` columns re-skinned.

---

## Observations become card decorations

`CustomerDecisionPressure`, `EstimateViewed`, `WaitingParts`, `CustomerWaitingResponse` are **not a separate screen anymore**. They are **card decorations**.

```
LEE WRIGHT
2018 Ram 2500

⚠ Estimate viewed 3 times
⚠ $9,027 pending
⚠ 4 days old

Waiting Approval
```

The **card becomes the recommendation**. ARK Manager / Today recommendations stop being a separate artifact the advisor must visit before seeing the problem.

Reuse existing observation vocabulary (`OperationalObservationResolver`, `WorkboardTriageCard` signals, `AdvisorHomeCardSurfaceProjection` chips) — project as explicit decoration lines on the card, not a new authority layer.

---

## The inversion (intelligence explains visible work)

Wrong:

```
Here's the answer.
(before the advisor has seen the problem)
```

Right:

```
Here's the shop.
Here's why these cars matter.
```

```
┌──────────────────────────────────────────────────────────┐
│ Flow · Pipeline · Recommendation · Edward metrics        │  ← context strip
├──────────────────────────────────────────────────────────┤
│ NEEDS ACTION (7)                                         │
│   [ customer-first cards with observation decorations ]  │
│ ACTIVE WORK (12)                                         │
│   …                                                      │
│ READY PICKUP (3)                                         │
│   …                                                      │
└──────────────────────────────────────────────────────────┘
```

Flow, Pipeline, Recommendations, Commitments, and ARK Manager **provide context above the board**. The board remains the **hero surface**.

This does not kill Flow, Pipeline, Recommendations, or ARK Manager. It gives them something to explain.

---

## Hard rules — Phase A

1. **Not lifecycle-first.** Not Kanban. Attention zones only.
2. **Customer-first, vehicle-second.** Primary scan: Customer → Vehicle → Attention. Not lifecycle lane → RO number → status.
3. **Status is a badge.** Attention is the organizing principle.
4. The board must answer without search or page navigation:
   - What customers need me?
   - What vehicles are active?
   - What work is waiting?
5. **Observations decorate cards.** Recommendations live on the card, not in a separate panel.
6. **One cockpit** at `/app`. Stop sending advisors to Today / Work / Index for discovery.
7. Reuse existing projections — new **presentation**, not new authority.

---

## Acceptance tests

### Molly test — phone lookup

Customer calls: *"Checking on my Jeep."* or *"This is Lee Wright."*

No RO #, no phone lookup required, no status.

**Find the customer/RO in under 5 seconds.**

Customer name as hero makes this faster than RO-first layout. Search remains optional acceleration.

### Edward test — 8:00 walk-in (no clicks)

| Question | Where |
|----------|-------|
| How many active cars? | Context strip |
| Largest pending approval? | Context strip or top of Needs Action |
| Oldest open RO? | Context strip or Needs Action decoration |
| Who needs a call today? | Needs Action zone + commitment decorations |

Needs Action zone visible above the fold satisfies *what can hurt me today* without scanning 30 equal rows.

### Stress test — dense shop

20–30 active ROs. No search. No page change. **Still usable.**

Attention zones collapse cognitive load: urgent customers first, active work second, pickup third.

---

## Phase A build map

**Surface:** `/app` → `operations.home`

**Evolve (do not replace):**

| Layer | Role |
|-------|------|
| `WorkboardTriageProjection` | Card authority, pressure, observations |
| `WorkboardTriageCard` | `countsAsNeedsAttention`, signals, age |
| `AdvisorHomeCardSurfaceProjection` | Call / text / open actions, chips |
| `AdvisorHomeCockpitProjection` | Context strip + Edward metrics |
| `AdvisorHomeAttentionBoardProjection` (new) | Zone grouping + customer-first row shape |

**Retire as primary UI:** lifecycle column grid (`home-column.blade.php` lane layout).

**Wire up:** `.ops-advisor-home-cockpit` CSS — done in Phase A.

**Phase A shipped.** Next step is observation, not iteration.

---

**Status:** Phase A shipped — **BUILD: NO · TUNE: NO · OBSERVE: YES**

---

## Phase A is a hypothesis test

The old home answered: **What does ARK think?**

The new home answers: **Who needs me?**

That is a fundamentally better first question for an advisor.

Phase A is not really a UI change. It is a **presentation change** on data that already existed — no new authority, no new observations, no new AI. If it works, the impact is outsized because the hierarchy was wrong, not because the shop lacked intelligence.

**Counter test (5 seconds, eyes closed):** Who needs me? What car? What is waiting? What is expensive? If you cannot answer after five seconds on `/app`, the hierarchy failed — not the data.

---

## Phase B — Observation week (notebook)

Run the shop for a few days. Do not redesign. Do not tune. Do not add intelligence.

### 1. Do you stop opening other screens? (biggest signal)

| Before | After (success) |
|--------|-----------------|
| Today → Workboard → Search → RO | `/app` → RO |

If that path becomes habit, the real front door has been found.

Track weekly:

| Surface | Still opened for discovery? |
|---------|----------------------------|
| Today | |
| Work (`/app/workboard`) | |
| RO Index (active cars) | |

### 2. Does Molly use search less?

The Molly test is not really *find a Jeep in under 5 seconds*. It is: **does she need search at all?**

If advisors start recognizing customers and cars directly from the board, that is a huge win. Note search usage qualitatively this week.

### 3. Which zone gets ignored?

Zones: **Needs Action · Active Work · Ready Pickup**

One may become dead space. **Do not fix yet. Observe.**

- Nobody scrolls to Ready Pickup → useful evidence
- Everyone lives in Needs Action → useful evidence

| Zone | Scrolled into? | Notes |
|------|----------------|-------|
| Needs Action | | |
| Active Work | | |
| Ready Pickup | | |

Also track weekly:

| Question | If yes → |
|----------|----------|
| Advisors still open Today separately? | Collapse |
| Advisors still open Work for discovery? | Home absorbed workboard |
| Advisors still open RO Index for active cars? | Index → historical/search |

### 4. What gets clicked? (instrument later — not now)

When observation closes, simple event counts — not a dashboard:

- Open RO
- Call
- Text
- Search used
- Employee filter used

If search usage drops dramatically, Phase A succeeded even if nobody can articulate why.

---

## Resist during observation

Do **not** add:

- More chips
- More colors
- More recommendations
- More AI
- Six observations + three recommendations + two AI suggestions per card

The whole reason Phase A feels promising is because it **simplified** the hierarchy. Decorating every card back into noise recreates the problem that was just solved.

---

## Two outcomes (decision tree)

### Outcome A — Board becomes home (success)

Today, Work, and RO Index become **secondary** — not removed, but no longer the discovery path.

→ Proceed to surface consolidation (Phase C) and inspection v1.5 (Phase D) when earned.

### Outcome B — Still leaving the board constantly

Do **not** immediately add features. Ask:

> **What information am I leaving the board to get?**

That gap is the missing piece — not more intelligence on `/app`.

---

## Phase C — Portal / website unification

Same discoverability problem in customer clothing.

---

## Phase D — Inspection v1.5

Findings flow into the cockpit advisors already live in.

---

## Non-goals (Phase A)

- Tekmetric dashboard parity theater
- Lifecycle lanes or Kanban columns as organizing principle
- Flat undifferentiated list at 30+ ROs
- Separate recommendation panel before the advisor sees customers
- New observation authority — decorate from existing vocabulary
- AI search as substitute for visible board

---

## ARK sequence

```
Discoverability (Phase A–B)
  → Surface consolidation (Phase C)
  → Inspection workflow (Phase D)
  → Derivation (observation-gated)
```

Inspection, portal, and design system remain **spec ready**. They do not outrank **"I can't find that customer."**
