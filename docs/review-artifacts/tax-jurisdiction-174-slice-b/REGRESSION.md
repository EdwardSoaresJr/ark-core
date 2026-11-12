# Full Regression — #190 tip `bf011171`

**GitHub run:** https://github.com/EdwardSoaresJr/ark-core/actions/runs/43309820708  
**Event:** `workflow_dispatch` on `feat/tax-jurisdiction-slice-b`  
**Conclusion:** success (**0 new regressions** vs known-failure baseline)

```text
## Full regression

4589 tests, 205 failing; the known-failure baseline lists 207.

### Regressions: failing and not in the baseline (0)

None.

### Known failures still failing (205)

(baseline backlog — communications / mobile / etc.)

### Known failures now passing: remove them from the baseline (2)

- `tests/Feature/Mobile/MobileApiTest.php::advisor can create appointment from mobile`
- `tests/Feature/Operations/ScheduleWeekAllocationTest.php::week technician allocation keeps unassigned visible and groups by technician`

(informational only; does not fail the job — not reclassified as green product claims here)
```

Required PR Checks on the same tip also passed (focused, lint, security, targeted, communications-boundary, gitleaks).

No baseline entries were added or reclassified for this tip.
