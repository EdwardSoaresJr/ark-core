# Workstream A — Core #74 / #72 / #95

Branch: `fix/core-74-72-95-financial-integrity`  
Base: `origin/main` @ `8e5ec0b028d0cc202eb30a30049ff564c4d82a37`  
Date: 2026-11-21

## Before (overnight baseline)

From `review-artifacts/overnight-financial-comms-integrity-2026-11/evidence/issues-72-74-95-96.md` on SHA `12adc0b4`:

| Issue | Before |
|---|---|
| #74 | `soldTotalCentsByRepairOrderId` = **43000** (raw lines) vs approved **28000** |
| #72 | `UnresolvedAuthorizationReport` rows = only added uncovered part; raised installed/completed omitted |
| #95 | Line badge generic “Needs authorization”; no approved ceiling / +increase next to the line |

## After (this branch)

Scenario: part approved $80 → installed → raised $120; labor approved $200 → completed → raised $260; uncovered part $50 installed.

| Issue | After |
|---|---|
| #74 | `soldTotalCentsByRepairOrderId` = **28000**; `laborCentsByRepairOrderId` = **20000** (via `approvedTotalsForRead`) |
| #72 | Report includes raised part (**4000¢**), raised labor (**6000¢**), and added part (**5000¢**) |
| #95 | Line shows `Increase needs authorization`, `Approved at $…`, `+$… pending authorization` next to the line; approved ceiling unchanged |

## Tests

```bash
./vendor/bin/pest \
  tests/Feature/Operations/Financial/Core747295FinancialIntegrityTest.php \
  tests/Feature/Operations/ApprovedWorkChangesAfterAuthorizationTest.php \
  tests/Feature/Operations/AuthorizationIntegrityTest.php \
  tests/Feature/Operations/AuthorizedCentDriftTest.php \
  tests/Unit/Operations/OperationalReportPostedSalesTest.php
```

Result: **67 passed**, 0 failed (727 assertions). F-FIN-01 authorized-cent drift suite included and green.

## Out of scope

- Work gating unchanged (install/complete still allowed with pending increase).
- Issue #96 (invoice footer estimate wording) not in this workstream.
