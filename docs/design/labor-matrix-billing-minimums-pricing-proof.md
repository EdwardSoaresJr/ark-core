# Labor Matrix + Billing Minimums — Pricing Proof (focused tests)

**Branch:** `settings/labor-matrix-billing-minimums`  
**Design contract:** `docs/design/labor-matrix-and-billing-minimums-staging.md`  
**Trace:** `docs/design/labor-matrix-billing-minimums-trace.md`

## Verification run

```text
php artisan test \
  tests/Feature/Operations/ApplyBillingClassMinimumsActionTest.php \
  tests/Feature/Operations/LaborWorkTypeMatrixSettingsTest.php \
  tests/Feature/Operations/StandardLaborRateSettingsTest.php \
  tests/Feature/Operations/ShopSettingsFinancialTablerTest.php
→ 22 passed
```

## Documented scenarios

| Scenario | Proof |
|---|---|
| **A** One Retail concern 0.40 hr under 1.00 hr RO minimum | Fee `Billing minimum · Retail` qty 0.60 @ $165 → $99; original labor stays 0.40 |
| **B** Second Retail concern 0.60 hr added | Fee removed; notice `0.60 → 0.00` |
| **C** Retail 0.40 + Warranty 2.00 | Retail shortfall remains 0.60; Warranty hours ignored |
| **D** Per-line mode 1.00 hr | Two fees (0.60 and 0.40); originals unchanged |
| **Forward-only** null profile RO | No fees even when shop activates minimums afterward |
| **Stamp** new RO after activation | `labor_pricing_profile.version = 1` with frozen minimums |

## Shortfall rate decision (owner lean, implemented)

Remaining RO-level shortfall uses the billing class’s configured shortfall rate, defaulting to **Retail Mechanical** (`shortfall_rate_source = standard_mechanical`). Mixed Mechanical + Advanced Mechanical on one Retail RO does **not** silently pick the higher rate.

## Settings UI acceptance

Labor Settings shows one matrix + one minimums configuration. No competing Labor Categories / Advanced billing rates editors on the primary Labor panel.
