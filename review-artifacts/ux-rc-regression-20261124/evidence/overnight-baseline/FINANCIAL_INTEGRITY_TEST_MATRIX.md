# Financial Integrity Test Matrix

**Audit:** 2026-11-24  
**Core SHA:** `9cc852899b2ab9754185675b7d83d656978b2b33`  
**SaaS SHA / pin:** `df735e4096e94eb8f0440f5124794ea924a88d2a` → Core `9cc85289`  
**Evidence root:** `review-artifacts/overnight-regression-20261124/`

**Overall financial integrity verdict (production baseline `9cc85289`): PASS**  
**Final combined UX candidate (#256+#259+#258+#257): NOT TESTED — PENDING FINAL INTEGRATION TEST**

Invariant: *The system must never bill more than the customer has authorized merely because an estimate changed.*

Legend: **PASS** / **FAIL** / **NOT TESTED**. Amounts asserted in **integer cents** unless noted.

Candidate SHAs (aligned with integration auditor; all still unmerged): #256 `d35acf75`, #259 `b54c4864`, #258 `4d20aa7f`, #257 `9b5bc832` on base `9cc85289`; SaaS remains `df735e40` until a post-merge pin.

---

## Run identity

| Run | Result |
|-----|--------|
| Full Core Pest (includes financial files) | See overnight report; financial key files PASS except known-baseline BalanceAxes query count |
| Financial re-run (17 files) | **247 passed**, 0 failed, 1666 assertions, 207325 ms |
| Disposable MySQL concurrency | **3 passed**, 35 assertions, 32718 ms |
| Payment providers | Mocked / fake only — **no live charges** |

---

## A. Customer authorization

| # | Scenario | Primary tests | Result | Notes |
|---|----------|---------------|--------|-------|
| A1 | Approved line amounts respected | `AuthorizationIntegrityTest`, `ApprovedWorkChangesAfterAuthorizationTest` | PASS | 31 + 16 tests |
| A2 | Price increase needs additional authorization | `ApprovedWork…` raise tests; `AuthorizedCentDriftTest` unit price | PASS | Ceiling held until supplemental approval |
| A3 | Quantity increase cannot silently raise approved charges | `AuthorizedCentDriftTest` quantity increase | PASS | |
| A4 | Unapproved added work not billed | `AuthorizationIntegrityTest` (#66), portal coverage | PASS | |
| A5 | Discounts/fees consistent with approved amounts | `AuthorizationIntegrityTest` tax/fee stamp; `EstimateFinancialAuthorityTest` standing discount | PASS | |
| A6 | Tax on correct billable base | `PartsMatrixAndTaxForwardOnlyTest`; fee/tax EstimateFinancial | PASS | Stamped profiles |
| A7 | Removed work handled | `ApprovedWork…` removing approved line | PASS | History preserved |
| A8 | Historical approval events preserved | `ApprovedWork…` authorization history; exception append-only | PASS | |
| A9 | Customer portal totals agree with invoice | `ApprovedWork…` portal reconcile; estimate PDF separation | PASS | |
| A10 | F-FIN-01 fee-cap cannot shrink/drift authorized cents | `AuthorizedCentDriftTest` (13) | PASS | Includes mixed tax, percent discount, closed RO freeze |

---

## B. Labor Matrix and Billing Classes

| # | Scenario | Primary tests | Result |
|---|----------|---------------|--------|
| B1 | Default labor rates / authority | `LaborAuthorityTest`, `LaborRateAuthorityTest` | PASS (23+8) |
| B2 | Labor class overrides / billing class | `BillingClassSettingsConsistencyTest`, `ConcernBillingRepricingTest`, `CustomerFacingLaborAuthorityBoundaryTest` | PASS |
| B3 | Minimum charges / matrix lifecycle | `LaborMatrixLifecycleTest` | PASS (9) |
| B4 | Rule resolution / pricing floor | `EstimatePricingFloorIntegrityTest`, `PricingEngineSafetyTest` | PASS |
| B5 | Rate changes do not invent higher approved bill | `PartsMatrixAndTaxForwardOnlyTest` billing class change; `CommercialPricingProfileForwardOnlyTest` | PASS |
| B6 | Existing RO snapshots / closed immutability | `CommercialPricing…` closed ROs; `UnifiedBillingWarrantyTermsTest` fingerprint | PASS |
| B7 | Missing/invalid class configurations | `PartsMatrix…` missing matrix fails visibly | PASS |
| B8 | SaaS multi-shop labor rate isolation | `RepairOrderWorkflowTest::test_each_shop_keeps_its_own_labor_rate_setting` | **FAIL** | Validation requires `standard hourly rate` — **P1 settings contract**, not a Core cent-billing failure on sqlite suite |

---

## C. Parts and commercial pricing

| # | Scenario | Primary tests | Result |
|---|----------|---------------|--------|
| C1 | Cost / selling / markup-margin | `EstimateFinancialAuthorityTest` parts matrix markup authority | PASS |
| C2 | Parts matrices + stamps | `PartsMatrixAndTaxForwardOnlyTest` | PASS (13) |
| C3 | Rounding to integer cents | `EstimateFinancialAuthorityTest` line totals round deterministically | PASS |
| C4 | Taxability mixed | `AuthorizedCentDriftTest` mixed taxable parts / nontaxable labor; PartsMatrix taxable/exempt | PASS |
| C5 | Discounts | standing discount reduces fee/tax base (EstimateFinancial) | PASS |
| C6 | Shop fees (flat / percent / per-concern / applies_to) | `ShopFeeFlatDollarTest`, `ShopFeePerLineAllocationTest`, `ShopFeeAppliesToTest` | PASS (8+6+23) |
| C7 | Pricing snapshots / archive | PartsMatrix archive preserves frozen tiers | PASS |
| C8 | Customer-supplied parts | NOT TESTED as dedicated named case in this audit’s selected set | **NOT TESTED** |
| C9 | Parts matrix tune assistant (closed sample) | `PartsMatrixTuneTest` | FAIL (known baseline) | Not a new regression |

---

## D. Invoicing and payments

| # | Scenario | Primary tests | Result |
|---|----------|---------------|--------|
| D1 | Estimate → invoice reconciliation | `FinancialCoreAuthorityTest`, Core 72/74/95 | PASS |
| D2 | Invoice create / issue / PDF | `InvoiceDocumentViewTest`, `IssuedInvoicePdfPreservationTest`, `InvoiceRevisionHistoryTest` | PASS |
| D3 | Partial / multiple payments | `AccountsReceivablePaymentAllocationTest`, capture foundation | PASS |
| D4 | Overpayment prevention / disposition | AR overpayment return-change / keep credit / refund pending | PASS |
| D5 | Refund / reversal (implemented paths) | AR refund confirmation; AR card capture refund idempotency | PASS |
| D6 | Failed / retried capture | `PaymentCaptureFoundationTest`, `FinancialIntegrityCaptureRepairTest` | PASS |
| D7 | Duplicate callback / webhook | fabric duplicate completion idempotent for ledger | PASS |
| D8 | Idempotency / submission intent | `FinancialSubmissionIntentTest` | PASS (14) |
| D9 | Balance due | Financial presenter / position projection | PASS (BalanceAxes query-count FAIL is known baseline, not amount) |
| D10 | Paid vs unpaid / closeout | `CaptureCompletionAfterCloseTest`, closeout PDF | PASS |
| D11 | Closed-order financial immutability | fee/tax/commercial closed RO tests | PASS |
| D12 | Live provider charges | — | **NOT TESTED** (forbidden) |

---

## E. Adversarial scenarios (Phase 3)

| # | Scenario | Asserted in | Result | Evidence |
|---|----------|-------------|--------|----------|
| E1 | Auth then labor-rate / price increase | AuthorizedCentDrift + ApprovedWork raise | PASS | Cents ceiling |
| E2 | Auth then parts-price increase | AuthorizationIntegrity #66 raise | PASS | |
| E3 | Qty increase after auth | AuthorizedCentDrift qty | PASS | |
| E4 | Added work without approval | ApprovedWork / AuthorizationIntegrity | PASS | |
| E5 | Approved line subsequently removed | ApprovedWork remove | PASS | |
| E6 | Discounts + shop fees + tax | EstimateFinancial + CentDrift fee-cap | PASS | |
| E7 | Partial payment then invoice adjustment | FinancialSubmissionIntent + AR allocation + capture after invoice rejected | PASS | Deposit after invoice rejected before capture |
| E8 | Duplicate payment webhook | PaymentCaptureFoundation duplicate completion | PASS | |
| E9 | Closed RO + changed shop settings | PartsMatrix/Commercial/ShopFee closed immutability | PASS | |
| E10 | Concurrent auth/payment updates | `tests/Integration/MySql/ConcurrencyOverlapTest` with `ARK_TEST_MYSQL=1` | PASS | Winner/loser estimate_version; same-key deposit once; store-credit no double-spend |

---

## F. Key suite rollup (from full-suite JUnit)

| File | Pass | Fail | Skip | Status |
|------|-----:|-----:|-----:|--------|
| AuthorizedCentDriftTest.php | 13 | 0 | 0 | PASS |
| ApprovedWorkChangesAfterAuthorizationTest.php | 16 | 0 | 0 | PASS |
| AuthorizationIntegrityTest.php | 31 | 0 | 0 | PASS |
| EstimateFinancialAuthorityTest.php | 24 | 0 | 0 | PASS |
| Core747295FinancialIntegrityTest.php | 3 | 0 | 0 | PASS |
| LaborMatrixLifecycleTest.php | 9 | 0 | 0 | PASS |
| PartsMatrixAndTaxForwardOnlyTest.php | 13 | 0 | 0 | PASS |
| ShopFeeFlatDollarTest.php | 8 | 0 | 0 | PASS |
| ShopFeePerLineAllocationTest.php | 6 | 0 | 0 | PASS |
| ShopFeeAppliesToTest.php | 23 | 0 | 0 | PASS |
| PaymentCaptureFoundationTest.php | 7 | 0 | 0 | PASS |
| FinancialIntegrityCaptureRepairTest.php | 6 | 0 | 0 | PASS |
| CommercialPricingProfileForwardOnlyTest.php | 8 | 0 | 0 | PASS |
| PricingEngineSafetyTest.php | 5 | 0 | 0 | PASS |
| UnifiedBillingWarrantyTermsTest.php | 8 | 0 | 0 | PASS |
| LaborAuthorityTest.php | 23 | 0 | 0 | PASS |
| LaborRateAuthorityTest.php | 8 | 0 | 0 | PASS |
| AccountsReceivablePaymentAllocationTest.php | 14 | 0 | 0 | PASS |
| FinancialCoreAuthorityTest.php | 31 | 0 | 0 | PASS |
| WorksheetContinuityAuthorizationTest.php | 3 | 0 | 0 | PASS |
| WorksheetContinuitySettlementTest.php | 4 | 0 | 0 | PASS |
| FinancialSubmissionIntentTest.php | 14 | 0 | 0 | PASS |
| RepairOrderFinancialPresenterBalanceAxesTest.php | 5 | 1 | 0 | FAIL (known baseline query assertion) |
| ConcurrencyOverlapTest.php (Integration) | 3 | 0 | 0 | PASS (separate run) |

---

## Failed financial-adjacent assertions

| Test | Severity | Classification |
|------|----------|----------------|
| `RepairOrderFinancialPresenterBalanceAxesTest::presenter with preloaded balance projection…` (`0 is less than 0`) | P3 | Known baseline; query-budget, **not** wrong cents |
| SaaS `RepairOrderWorkflowTest::test_each_shop_keeps_its_own_labor_rate_setting` | P1 | Settings form contract / tenant labor configuration path |
| `PartsMatrixTuneTest` (known) | P3 | Known baseline |

No confirmed **P0** authorized-cent overbill on Core `9cc85289`.

---

## Reproduction

```bash
# Core financial re-run
cd /Users/edwardsoares/Herd/ark-core-overnight-reg-20261124
git rev-parse HEAD   # expect 9cc85289…
php -d memory_limit=2048M vendor/bin/pest --no-coverage \
  tests/Feature/Operations/AuthorizedCentDriftTest.php \
  tests/Feature/Operations/ApprovedWorkChangesAfterAuthorizationTest.php \
  # … (full list in overnight report Phase 2)

# Concurrency
export ARK_TEST_MYSQL=1 \
  ARK_TEST_MYSQL_HOST=127.0.0.1 ARK_TEST_MYSQL_PORT=3306 \
  ARK_TEST_MYSQL_USERNAME=ark_test_overnight \
  ARK_TEST_MYSQL_PASSWORD='…' \
  ARK_TEST_MYSQL_DATABASE_PREFIX=ark_test_overnight
php vendor/bin/pest --no-coverage tests/Integration/MySql/ConcurrencyOverlapTest.php
```

---

## Coverage gaps (must stay NOT TESTED if not run)

- Customer-supplied parts dedicated scenarios  
- Live Square/Twilio/VizyPay money movement  
- Combined Job Board PR merge financial re-check  
- Production tenant financial records  

**Stop for owner review.** Companion: [OVERNIGHT_REGRESSION_REPORT.md](./OVERNIGHT_REGRESSION_REPORT.md).
