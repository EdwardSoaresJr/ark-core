# Fail B — Presenter query-count `0 < 0`

**Classification:** Test harness invalidation by request-scoped `RepairOrderReadCache`.  
**Not classified as:** Presenter data-missing defect or intentional performance regression.  
**Severity:** Low product risk; medium test-signal noise (assertion no longer measures what it claims).

## Symptom

`RepairOrderFinancialPresenterBalanceAxesTest` →  
`presenter with preloaded balance projection does not add BalanceDueCalculator queries`

Assertion:

```php
->and($preloadInvoice)->toBeLessThan($freshInvoice)
```

Observed: `preloadInvoice = 0`, `freshInvoice = 0` → `0 < 0` fails.

## What the test intended

Prove that passing a precomputed `BalanceDue` projection into `RepairOrderFinancialPresenter::for(...)` avoids repeating BalanceDueCalculator’s invoice (+ ledger) SQL relative to calling `for(..., null)` which must load balance fresh.

## Why both counters are zero

1. Setup calls `BalanceDueCalculator::projectForRepairOrder($repairOrder)` **before** `Event::listen(QueryExecuted::class, ...)`.
2. `BalanceDueCalculator::queryIssuedInvoice` / ledger reads go through `RepairOrderReadCache::remember(...)`.
3. That warms the request-scoped cache.
4. During measurement, **both** presenter paths hit cache → **0** `estimate_documents` queries counted.
5. `toBeLessThan` therefore compares `0 < 0`.

Presenter still returns matching settlement fields (`settlementBalanceDueCents`, `isPaid`, `oweTodayCents`) for both paths — data is loaded/rendered correctly via cache, not skipped.

## Proposed better regression assertion (not applied here)

Preserve the performance requirement without relying on a cold-cache accident:

1. **Flush before fresh measurement:**  
   `app(RepairOrderReadCache::class)->flush()` immediately before the without-preload `for(...)`, then assert fresh path issues ≥1 invoice query and preload path issues fewer; **or**
2. **Cold both paths:** flush before preload measurement and again before fresh measurement; assert preload invoice queries `<` fresh invoice queries; **or**
3. **Spy / collaboration test:** assert presenter uses the injected projection and does not call `BalanceDueCalculator::projectForRepairOrder` when projection is non-null (direct unit of the optimization), plus a separate integration that cold-cache without-preload still loads issued invoice + ledger.

Do **not** delete the test or soften to `<=` without a replacement that still proves preload avoids BalanceDue work.

## Related code

- `app/Ark/Operations/RepairOrders/RepairOrderReadCache.php`
- `app/Ark/Operations/Financial/BalanceDueCalculator.php` (`queryIssuedInvoice` / active ledger via `remember`)
- `tests/Feature/Operations/Financial/RepairOrderFinancialPresenterBalanceAxesTest.php` L184–224
