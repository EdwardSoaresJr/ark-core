# KPI Gap Matrix — Doctrine → ARK

**Reviewed:** 2026-06-04

Maps industry doctrine metrics to ARK implementation. Update when reports or targets change.

## Executive Pulse (Operational Report)

| Doctrine KPI | Source | ARK metric | Status |
|--------------|--------|------------|--------|
| Sales closed | Cecil daily mgmt | Sales Closed | ✅ Phase 0 |
| Car count | Cecil / Lucas | Car Count | ✅ Phase 0 |
| ARO | Cecil step 3 | ARO | ✅ Phase 0 |
| Approval rate | Shop close ratio | Approval Rate | ✅ Phase 0 |
| Effective labor rate | Cecil step 4 | Effective Labor Rate | ✅ Phase 1 |
| Parts margin % | Cecil step 2 | Parts Margin | ✅ Phase 1 |
| Labor margin % | Cecil loaded labor | Labor Margin | ✅ Phase 1 |
| Parts/labor mix | Cecil 45/55 | Parts/Labor Mix | ✅ Phase 1 |
| Margin Health tab | Cecil bands | Margin Health tab | ✅ Phase 2 |
| Weekly owner checklist | Cecil P&L reminder | ARK Academy weekly-owner-review | ✅ Phase 2 |
| Owner bookend workspace | Lucas bookend | `/app/owner/bookend` | ✅ Phase 3 |
| Daily owner email digest | Lucas daily data | `shop-excellence:owner-digest` | ✅ Phase 3 |
| Advisor growth literacy | Lucas attrition | ARK Academy client-retention-growth | ✅ Phase 3 |
| Advisor financial literacy | Lucas / Cecil | ARK Academy financial-literacy-basics | ✅ Phase 3 |
| Quarterly target review | Owner cadence | ARK Academy quarterly-target-review | ✅ Phase 4 |
| Owner targets settings UI | Owner UX | Settings → Owner Targets | ✅ Phase 5 |
| Break-even pulse | Fixed monthly costs | Margin Health break-even card | ✅ Phase 5 |
| Private doctrine notes | Paid material | `docs/shop-excellence/private/` | ✅ Phase 4 |
| Parts GP dollars | Cecil | Parts GP | ✅ Phase 0 |
| Parts sold | Cecil | Parts Sold | ✅ Phase 0 |
| Labor sold | Cecil | Labor Sold | ✅ Phase 0 |
| Fees sold | Shop fees truth | Fees Sold | ✅ Phase 0 |
| Deferred opportunity | Inspection follow-up | Deferred Opportunity | ✅ Phase 0 |
| Unpaid pickups | Cash collection | Unpaid Pickups | ✅ Phase 0 |

## Production tab

| Doctrine KPI | Source | ARK metric | Status |
|--------------|--------|------------|--------|
| Tech productivity | Cecil daily KPI | Technician efficiency % | ✅ Phase 0 |
| Queue pressure | Lucas bookend | Pressure rows | ✅ Phase 0 |

## Financial tab

| Doctrine KPI | Source | ARK metric | Status |
|--------------|--------|------------|--------|
| Gross margin by category | Cecil | Financial Mix rows | ✅ Phase 0 |
| Labor cost (partial) | Cecil loaded cost | Labor cost in mix | ⚠️ Partial — not full payroll |

## Deferred — Phase 5+

| Doctrine KPI | Blocker |
|--------------|---------|
| Net profit / 20% net | P&L integration |
| True loaded labor cost | Payroll per tech |
| DVI adoption rate | Inspection workflow events |
| Marketing ROI | External spend tracking |

## Target bands

Shop-specific targets: **Settings → Owner Targets** (`shop_settings.shop_excellence_targets`).

Loaded by `App\Ark\Operations\ShopExcellence\ShopExcellenceTargets` for report hint tone (`good` / `warn`).

## ARK Academy Owner articles

| Topic | Blade |
|-------|-------|
| Five steps to margins | `operations.learn.owner.shop-margins-five-steps` |
| Daily KPIs | `operations.learn.owner.daily-kpis` |
| Daily rhythm | `operations.learn.owner.daily-rhythm` |
| Reports guide | `operations.learn.owner.ark-reports-guide` |
| Weekly owner review | `operations.learn.owner.weekly-owner-review` |
| Quarterly target review | `operations.learn.owner.quarterly-target-review` |

## ARK Academy Advisor articles (shop excellence)

| Topic | Blade |
|-------|-------|
| Client attrition / growth | `operations.learn.advisor.client-retention-growth` |
| Financial literacy basics | `operations.learn.advisor.financial-literacy-basics` |
