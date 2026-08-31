# Operational Flow Projection v1

**Status:** Design contract  
**Sequence:** Today → Pipeline → **Flow** → Commitments → Outcomes → ARK Manager  
**Doctrine:** Flow is a projection, not authority. Same pattern as `TodayPipelineProjection`, `AdvisorTodayRecommendationEngine`, `CustomerDecisionPressure`.

---

## Purpose

**Operational Flow** answers the shop's daily rhythm question:

> Where is work accumulating, and which stage is the primary limiter of throughput and cash conversion right now?

This is not a dashboard. It is a **first-class projection** consumed by Today, ARK Manager, future reports, and future coaching — without re-deriving truth at each surface.

Flow explains revenue swings better than commitments alone. Commitments answer *which promises are at risk*. Flow answers *why a month was $38k vs $15k* — work got trapped somewhere in the cycle.

### The four morning questions (Today acceptance test)

An owner or advisor opens Today and, within **10 seconds**, can answer:

| Question | Surface |
|----------|---------|
| Where is money stuck? | **Pipeline** (cash buckets) |
| Where is work stuck? | **Flow** (stage accumulation) |
| What is the current operational constraint? | **Flow** (derived constraint) |
| What should we do next? | **Recommendations** (constraint-aware actions) |

Commitments and Outcomes extend the rhythm but do not replace Flow.

---

## What Flow is not

| Anti-pattern | Why |
|--------------|-----|
| New `flow_stages` authority table | Stages are derived from RO lifecycle + triage |
| Dashboard widget with inline queries | Violates projection rule; fan-out at render |
| Hardcoded "constraint = waiting approval" | Constraint must be **scored**, explainable, and shop-specific today |
| Report-only SQL | Flow must power Today first; reports consume the same projection |
| AI inference of bottleneck | Manager narrates constraint; Flow **measures** it |

---

## Relationship to existing surfaces

Today already has adjacent pieces. Flow **unifies** them; it does not duplicate them.

| Existing | Role | Flow relationship |
|----------|------|-------------------|
| `TodayPipelineProjection` | Money buckets (awaiting approval, approved-not-started, ready pickup) | **Pipeline = money lens.** Flow = work lens. Overlap is intentional; labels differ. |
| `AdvisorTodayShopRadarBuilder` | Count + oldest age + revenue per workboard queue | **Proto-flow rows.** Flow extends coverage to full lifecycle and adds constraint. |
| `WorkboardTriageProjection` | Queue membership, pressure signals | **Stage assignment input** for signal-weight |
| `CustomerDecisionPressure` | Decision dollars, age, estimate-not-sent | **Approval-stage pressure input** |
| `AdvisorTodayRecommendationEngine` | Ranked next actions | **Consumer** — recommendations should reference constraint stage when present |
| `OperationalIntelligence` (reports) | Legacy bucket counts | **Do not extend.** New code consumes `OperationalFlowProjection` instead. |

After Flow ships, **Shop radar on Today may fold into Flow** or remain as a drill-down alias. Do not maintain two competing stage models.

---

## Authority inputs (read-only)

Flow composes from truth layers already in ARK:

| Source | Flow uses |
|--------|-----------|
| `RepairOrder.status` + `RepairOrderStatusCatalog` | Primary stage placement |
| `WorkboardSwimlaneCatalog` | Canonical stage keys and status slug mapping |
| `WorkboardTriageProjection` | Cross-cutting signals (customer waiting, unassigned, parts pressure) |
| `CustomerDecisionPressure` | Approval-stage dollars, age, estimate-ready-not-sent |
| `EstimateTotalsCalculator` | Revenue at stage (estimate total vs approved-work total — same rules as Pipeline) |
| `BalanceDueCalculator` | Paid vs unpaid at pickup stage |
| `OperationalEvent` (`RepairOrderLifecycleChanged`) | **Preferred** time-in-stage (status entered at) |
| `repair_orders.opened_at` / `updated_at` | **v1 fallback** only when lifecycle event missing |

No new writes on GET. Flow is computed once per Today render and passed explicitly to views.

---

## Stage model

Stages represent **operational accumulation points** in the shop cycle — not every lifecycle slug gets its own row.

| `stage_key` | Label | Primary assignment |
|-------------|-------|-------------------|
| `work_arrives` | Work Arrives | Intake pressure: draft ROs with no estimate lines, or explicit intake queue (v1: `draft` + `estimate` with zero lines — see rules below) |
| `needs_diagnosis` | Needs Diagnosis | `draft` with lines/concerns, not yet estimate-ready |
| `building_estimate` | Building Estimate | `estimate` status |
| `waiting_approval` | Waiting Approval | `waiting_approval` status |
| `waiting_parts` | Waiting Parts | `waiting_parts` status |
| `in_repair` | In Repair | `approved`, `ready_for_work`, `in_progress` |
| `quality_check` | Quality Check | `quality_check` status |
| `ready_pickup` | Ready Pickup | `completed`, `invoiced`, `ready_pickup` — work complete, cash conversion may still be pending (Pipeline owns unpaid-at-pickup dollars) |

**Paid is excluded from Flow v1.** Paid belongs to Pipeline / Day Review. Flow stops at Ready Pickup — work and cash conversion still in motion.

**Closed ROs are excluded** from Flow.

### Assignment rules (deterministic)

1. Each open RO maps to **exactly one** stage (first match wins — document order in implementation).
2. Stage keys align with `WorkboardSwimlaneCatalog` lane keys where possible (`needs_diagnosis`, `building_estimate`, `waiting_approval`, `waiting_parts`, `shop_floor` → `in_repair`, `quality_check`, `ready_pickup`).
3. `work_arrives` is **pre-production intake only** — leads, scheduled intake, draft ROs not yet checked in or diagnosed. It is not a junk drawer for stale leads or every open lead forever. ROs with diagnostic/estimate activity belong in `needs_diagnosis` or later stages.
4. Triage overlays (customer waiting, unassigned tech) contribute **signal weight** to the RO's current stage; they do not create parallel stage rows.

---

## Per-stage metrics

Each stage produces a `FlowStageProjection`:

| Field | Type | Meaning |
|-------|------|---------|
| `stage_key` | string | Stable key (table above) |
| `label` | string | Display label |
| `count` | int | ROs in stage |
| `oldest_age_minutes` | int | Max time-in-stage among members |
| `oldest_age_label` | string | Human label (`2d`, `4h`) — computed once |
| `median_age_minutes` | int | Median time-in-stage |
| `median_age_label` | string | Human label |
| `revenue_cents` | int | Sum of authoritative totals for ROs in stage |
| `revenue_label` | string | `$11,400` — computed once |
| `pressure_score` | int | Weighted score for constraint ranking (see below) |
| `inventory_url` | string | Drill-down (workboard queue or RO index filter) |
| `signal_summary` | ?string | Optional one-line explainability (`3 over 72h`, `2 estimate not sent`) |

### Time-in-stage (v1)

**Preferred:** last `RepairOrderLifecycleChanged` event where `to_status` matches current status → `occurred_at`.

**Fallback:** `repair_orders.updated_at` (document as approximate; improve in v2 with materialized status-entered column only if measurement proves fallback is wrong).

Age is computed **once per RO** during projection build, then aggregated per stage.

### Revenue rules (match Pipeline discipline)

| Stage group | Totals source |
|-------------|---------------|
| Pre-approval (`building_estimate`, `waiting_approval`, intake/diagnosis) | `EstimateTotalsCalculator::totalsFor()` |
| Post-approval production | `EstimateTotalsCalculator::totalsForApprovedWork()` |
| Pickup | Approved-work total; flag unpaid via `BalanceDueCalculator` |

---

## Constraint

The **constraint** is the stage with the highest `pressure_score` among stages with `count > 0`.

Flow must be able to say:

> **Constraint → Waiting Approval**  
> Waiting Approval is currently the largest limiter of cash conversion — 9 ROs, $11,400, oldest 4 days.

Not merely "Waiting Approval = 9".

### Pressure score (v1 formula)

Normalize each component to 0–100 within the **current open RO population**, then combine:

```
pressure_score =
    (volume_weight   × volume_norm)   +
    (age_weight      × age_norm)      +
    (revenue_weight  × revenue_norm)  +
    (signal_weight   × signal_norm)
```

**Default weights (v1 — fixed for initial measurement; tunable per shop only after observation proves misfire):**

| Component | Weight | `volume_norm` | `age_norm` | `revenue_norm` | `signal_norm` |
|-----------|--------|---------------|------------|----------------|---------------|
| Volume | **0.25** | stage count ÷ max count | — | — | — |
| Age | **0.30** | — | median age ÷ max median age | — | — |
| Revenue | **0.30** | — | — | stage revenue ÷ max revenue | — |
| Signal | **0.15** | — | — | — | triage + decision pressure severity |

**Signal_norm sources (additive caps at 100):**

- RO in `CustomerDecisionPressure` buckets → +weight
- `WorkboardTriageCard` alert/warn tone → +weight
- Estimate viewed / multiple messages on waiting approval → +weight
- Parts pressure on waiting parts / in repair → +weight

Constraint output includes **explainability**:

```php
FlowConstraintProjection {
    stage_key: 'waiting_approval',
    label: 'Waiting Approval',
    pressure_score: 87,
    headline: 'Largest limiter of cash conversion',
    reasons: [
        'Highest revenue trapped ($11,400)',
        'Oldest median age (4.2 days)',
        '9 ROs — highest volume stage',
    ],
}
```

---

## Output DTOs

Namespace: `App\Ark\Operations\Flow\`

### `FlowStageProjection` (readonly)

One row per stage. All display strings computed in projection — views render fields only.

### `FlowConstraintProjection` (readonly)

Current constraint + explainability list. Nullable when no open ROs.

### `OperationalFlowProjection` (readonly)

```php
final readonly class OperationalFlowProjection
{
    /** @param list<FlowStageProjection> $stages */
    public function __construct(
        public array $stages,
        public ?FlowConstraintProjection $constraint,
        public Carbon $generated_at,
    ) {}
}
```

### Builder

```php
final class OperationalFlowProjectionBuilder
{
    /** @param Collection<int, RepairOrder> $openRepairOrders — same cohort as Today/workboard advisor query */
    public function build(Collection $openRepairOrders): OperationalFlowProjection;
}
```

Single entry point. No static helpers called from Blade.

---

## Consumers

| Consumer | Usage |
|----------|-------|
| **Today** | Flow section at top of overview (above or replacing shop radar). Constraint headline visible without scrolling on desktop. |
| **Recommendations** | Engine receives optional `FlowConstraintProjection`; boost cards whose `ruleKey` aligns with constraint stage. |
| **ARK Manager** | Narrates constraint + reasons — never inventing bottleneck from LLM when Flow projection is present. |
| **Operational Report** (later) | Weekly constraint history — feeds Outcomes. |
| **Owner Day Review** (later) | End-of-day constraint snapshot comparison. |

---

## Outcomes (mandatory companion — separate projection)

**Flow without Outcomes is dashboard theater.**

`OperationalFlowOutcomeProjection` (v2 in sequence, designed now):

| Field | Meaning |
|-------|---------|
| `constraint_stage_key` | Last week's constraint |
| `constraint_median_age_days` | Then |
| `current_median_age_days` | Now |
| `improvement_percent` | e.g. 36% |
| `cohort_window` | `7d` / `30d` |

**v1 storage:** append-only shop notebook table or weekly snapshot row — not required for Flow v1 ship, but **schema intent** documented:

```
operational_flow_snapshots (
  id, captured_at, constraint_stage_key,
  stage_metrics_json, -- full stage array for diff
  created_at
)
```

Today v1 may show a single line when snapshots exist:

> Waiting Approval median age: 4.2d → 2.7d (−36% vs last week)

Outcomes ship immediately after Flow, before ARK Manager.

---

## UI contract (Today)

```
FLOW                          Constraint → Waiting Approval

Needs Diagnosis          5     oldest 2d
Waiting Approval         9     $11,400 · median 4.2d
Waiting Parts            2     $4,900
In Repair                6     —
Quality Check            1     —
Ready Pickup             4     $3,800
```

- One column scan: **stage · count · age/revenue**
- Constraint row highlighted — not a separate dashboard
- Every row links to inventory (workboard or filtered RO index)
- Pipeline remains adjacent — **money** vs **work** side by side

---

## Implementation sequence (minimal)

### Phase 1 — Projection only (no UI change) ✅ shipped

1. `FlowStageKey` enum — eight stages, **no paid**
2. `FlowStageProjection`, `FlowConstraintProjection`, `OperationalFlowProjection`
3. `FlowStageResolver` + `OperationalFlowProjectionBuilder` with tests against fixture RO cohorts
4. Query composition budget test: one advisor Today load, stable truth + projection query counts *(budget test deferred to Phase 2 when wired to Today)*

**Namespace:** `App\Ark\Operations\Flow\`

### Phase 2 — Today surface ✅ shipped

1. Wire into `AdvisorTodayProjection` (same advisor cohort as recommendations)
2. Blade partial `operations/today/partials/flow.blade.php` — full width above Pipeline
3. Constraint headline + active stage rows + **Why?** explainability block
4. Feature test: constraint and reasons render on Today

**Deferred:** Shop radar fold, Flow History, Outcomes snapshots, recommendation constraint boost.

### Phase 3 — Recommendation alignment

1. Pass constraint into `AdvisorTodayRecommendationEngine`
2. Modest rank boost for cards matching constraint stage — still deterministic, still explainable in `whyReasons`

### Phase 4 — Outcomes snapshot

1. Daily snapshot job (shop timezone midnight or first Today visit — **measure before automating**)
2. Outcomes line on Today

### Phase 5 — ARK Manager

1. Manager panel consumes `OperationalFlowProjection` + Outcomes — narrative only, no new math

---

## Acceptance criteria

1. **10-second test:** Owner opens Today, identifies constraint and top trapped stage without opening an RO.
2. **Explainability:** Constraint row lists at least two reasons derived from metrics, not copywriting.
3. **Projection rule:** Zero domain method calls from Blade; one builder invocation per render.
4. **Read/write rule:** GET Today performs zero mutations.
5. **Determinism:** Same RO cohort → same constraint (no LLM, no randomness).
6. **Drill-down:** Every stage row links to actionable inventory.
7. **Tests:** Fixture cohort where constraint is unambiguous (e.g. waiting approval wins on revenue + age); fixture where production stage wins on volume.

---

## Open questions (resolve during Phase 1 build)

| Question | Default until measured |
|----------|------------------------|
| `work_arrives` vs `needs_diagnosis` boundary | Use line count + status slug; refine with floor observation |
| Include `paid` bucket on Today? | **No — excluded from Flow v1.** Pipeline / Day Review own paid truth. |
| Snapshot trigger: cron vs first visit | Notebook manual week one; automate after observation |
| Weight tuning | Ship v1 defaults; shop settings only if LNP proves misfire |

---

## Roadmap alignment (confirmed)

```
✓ Today
→ Pipeline        (money — finish polish + drill-down)
→ Flow            (work — this document)
→ Commitments     (promises at risk)
→ Outcomes        (prove constraint improved)
→ ARK Manager     (narrative on measured constraint)
→ AI Drafting / Coaching / Predictive
```

Pipeline and Flow together answer the two questions that explain revenue:

- **Pipeline:** Where is the money? (collected / money buckets)
- **Flow:** Where is work stuck? (operational stages through ready pickup)
- **Day Review:** Historical paid truth (end-of-day closeout — not Flow)

Commitments matter. They do not explain April vs May. Flow might.

---

## References

- `.cursor/rules/ark-projection-rule.mdc`
- `.cursor/rules/ark-pressure-first.mdc` — observe → surface → measure before enforce
- `app/Ark/Operations/Today/TodayPipelineProjection.php`
- `app/Ark/Operations/Today/AdvisorTodayShopRadarBuilder.php`
- `app/Ark/Operations/Workboard/WorkboardSwimlaneCatalog.php`
- `app/Ark/Operations/Attention/CustomerDecisionPressure.php`
