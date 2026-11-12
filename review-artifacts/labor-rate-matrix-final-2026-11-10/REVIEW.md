# Labor Rate Matrix — Final Implementation Review

**Core branch:** `settings/labor-matrix-billing-minimums`  
**Date:** 2026-11-10  
**Scope:** Staging-only owner review. Not production.

## Artifacts

| File | Purpose |
|---|---|
| `desktop-labor-matrix.png` | Vertical work-type nav + inline rates |
| `desktop-rules-popover.png` | Billing-class Rules modal |
| `mobile-labor-matrix.png` | Narrow viewport layout |
| `labor-matrix-preview.html` | Local preview shell used for screenshots |

## Test gate (focused)

```bash
php artisan test \
  tests/Feature/Operations/LaborWorkTypeMatrixSettingsTest.php \
  tests/Feature/Operations/ApplyBillingClassMinimumsActionTest.php \
  tests/Feature/Operations/StandardLaborRateSettingsTest.php \
  tests/Feature/Operations/ShopSettingsFinancialTablerTest.php
```

Result: **28 passed** (after custom billing-class concern selection tests).

## Migrations

`database/migrations/2026_11_10_180000_labor_matrix_profile_v2_and_programming_split.php`

- Additive `repair_order_lines.system_line_kind` / `system_line_scope`
- Additive `shop_settings.custom_labor_billing_classes`
- Programming operation class split (copies current diagnostics policies; no historical RO rewrite)
- Backfills durable kind only for existing description-matched minimum fee lines
- Does **not** backfill `repair_orders.labor_pricing_profile`

## Pricing profile

Schema **2** stamps: version, default cents, billing minimums, rates matrix, work-type map, category rules.  
Null profile remains legacy. New ROs stamp at create when `labor_pricing_profile_version >= 1`.

## Demonstrated in tests

- Mechanical × Retail default sync
- Programming ≠ Troubleshooting rates after split
- Add Work Type → operation class + selectable category + policies
- Add Billing Class → policies + concern picker selection + labor pricing
- Scenarios A–D minimums; mixed Retail/Warranty isolation; legacy null RO safety
- Forward-only: old stamped RO keeps prior rates for new labor; new RO gets updated rates
- Autosave JSON endpoints with version concurrency

## Staging pin

Update `docs/engineering/STAGING_LABOR_MATRIX_BILLING_MINIMUMS_PIN.md` after this commit SHA is known.  
**Do not deploy production / LugsNPlugs.** Staging host update requires owner-authorized SaaS pin procedure in that doc.

## Deferred / residual

- Custom billing-class fee/parts matrices still follow the configured `base_posture` (Retail-like / Warranty-like) until a later settings surface maps them independently.
- Null-profile historical Standard-rate reconstruction still uses as-of policy row cents; residual drift risk for pre-activation ROs is documented in the plan.
- Future brackets / vehicle-age / rust / OEM formulas not implemented; profile JSON leaves room.
