# Full Regression — eligibility tip `a008275`

**GitHub run:** https://github.com/EdwardSoaresJr/ark-core/actions/runs/43288790782  
**Event:** workflow_dispatch on `feat/tax-profiles-slice-a`  
**Conclusion:** success (0 new regressions vs baseline)

```text
## Full regression

4576 tests, 205 failing; the known-failure baseline lists 207.

### Regressions: failing and not in the baseline (0)

None.

### Known failures still failing (205)

(baseline backlog — communications / mobile / etc.)

### Known failures now passing: remove them from the baseline (2)

(informational only; does not fail the job)
```

Focused Tests on the same tip also passed: https://github.com/EdwardSoaresJr/ark-core/actions/runs/43288811401

Earlier tip `624ce35` failed once on `PricingEngineSafetyTest` expecting shop_fee_schema 2; fixed in `a008275`.
