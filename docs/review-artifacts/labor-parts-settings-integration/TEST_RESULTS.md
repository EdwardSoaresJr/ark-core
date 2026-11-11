# Integration regression results

**Branch:** `integrate/labor-and-parts-settings`  
**Date:** 2026-11-11

## Suites (local)

| Suite | Result |
|---|---|
| `LaborPolicyResolverTest` + `LaborPolicySettingsTest` | **15 passed** |
| Financial settings / forward-only / auth / snapshots / Parts matrix stamp / ShopSettings | **115 passed** |

Commands:

```bash
php artisan test --compact \
  tests/Unit/Operations/LaborPolicyResolverTest.php \
  tests/Feature/Operations/LaborPolicySettingsTest.php

php artisan test --compact \
  tests/Feature/Operations/ShopSettingsFinancialTablerTest.php \
  tests/Feature/Operations/StandardLaborRateSettingsTest.php \
  tests/Feature/Operations/LaborWorkTypeMatrixSettingsTest.php \
  tests/Feature/Operations/CommercialPricingProfileForwardOnlyTest.php \
  tests/Feature/Operations/BillingClassSettingsConsistencyTest.php \
  tests/Feature/Operations/ApprovedWorkChangesAfterAuthorizationTest.php \
  tests/Feature/Operations/PricingSnapshotImmutabilityTest.php \
  tests/Feature/Operations/PartsMatrixAndTaxForwardOnlyTest.php \
  tests/Unit/Operations/LaborRateTypeShopCompatibilityTest.php \
  tests/Feature/Operations/ShopSettingsTest.php
```

## Test hygiene applied during integration

- Obsolete `labor_categories` form posts no longer mutate categories via `updateLabor` — assertions retargeted.
- `LaborPolicyResolver` Standard cells keep stamped cents (Labor redesign forward-only); null cents still follow shop default.
- Shop Settings / Labor Policies view assertions retargeted to Work Types matrix + Manage Billing Classes (retired tabs absent).
- Shop fee Alpine assertion accepts `shopFeeMode` alongside `shopFeeEnabled`.

## Noted outside this gate

- `PartsMatrixTuneTest::parts matrix tune assistant simulates markup changes on closed sample` failed once with `insufficient_data` unexpectedly true — owner matrix-tune tool, not Financial Rules nav. Revisit separately if it reproduces on main.
