# Automated regressions — Campaign 3

Run against local worktree (Core tip `0c6603e` + DEF-G0-05 keypad patch). Staging runtime remains deployed `e2aa732`.

## Broader Financial suite

```
tests/Feature/Operations/Financial --parallel
129 passed / 131 tests / 901 assertions / 2 failed
```

### Failures (precise)

1. `FinancialCoreAuthorityTest` · `issued invoice snapshot stays frozen when estimate changes after partial payment`  
   - File: `tests/Feature/Operations/Financial/FinancialCoreAuthorityTest.php:149`  
   - Assertion: `feesCents()` expected `1500`, got `0` after enabling shop fee 10% and recalculating.  
   - Impact: fee application / approved totals path under shop-fee-enabled settings — **not** introduced by keypad JS patch.

2. `RepairOrderFinancialPresenterBalanceAxesTest` · `presenter with preloaded balance projection does not add BalanceDueCalculator queries`  
   - File: `tests/Feature/Operations/Financial/RepairOrderFinancialPresenterBalanceAxesTest.php:223`  
   - Assertion: `0 is less than 0` (query-count baseline).  
   - Likely harness/flake or zero-query baseline; no assertion weakening applied.

## Targeted ShopFeeFlatDollar

```
tests/Feature/Operations/ShopFeeFlatDollarTest.php
8/8 passed (70 assertions)
```

## Sticky/fee focused (Campaign 2 prior 13/13)

Not re-isolated as a named 13-file bundle in this stop; covered partially by Financial suite + ShopFeeFlatDollar. No assertions silenced.
