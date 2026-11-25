# RO Financial & Lifecycle Overhaul — Staging Report

**Date:** 2026-11-25  
**Production deploy:** NOT authorized / NOT performed

## 1. Deposit/payment root causes

1. **P0:** `RepairOrderDepositRecordingGuard::remainingAllowedDepositCents` hard-capped the first capture at remaining **suggested** deposit, blocking one-shot full authorized advances.
2. **P1:** Final invoice generate required `ready_pickup` only, even though closeout already allowed Completed / Invoiced / Ready Pickup.
3. **P1 UX:** Invoice blocking reason was hidden when status blocked generation; advisors saw a missing button with no explanation.
4. **P1:** `workflowPosture` ignored issued invoices when RO status was not Ready Pickup.

## 2. Financial authority map

| Concern | Authority |
|---|---|
| Owe today / advances | `FinancialPositionProjection` + deposit ledger entries |
| Suggested deposit (default only) | `RepairOrderDefaultDepositCalculator` |
| Deposit/advance hard cap | `RepairOrderDepositRecordingGuard` |
| Settlement / pay invoice | Issued `EstimateDocument` invoice + `BalanceDueCalculator` |
| Ledger writes | `RecordLedgerEntryAction` via deposit/payment recorders |
| Card capture | `InitiatePaymentCaptureAction` → apply result → ledger |
| Final invoice issue | `GenerateInvoiceSnapshotAction` + `RepairOrderFinalInvoiceEligibility` |
| Closeout | `RepairOrderCloseoutAuthority` |
| UI gates / copy | `RepairOrderFinancialPresenter` + payment strip/workspace |

## 3. Guards corrected vs preserved

**Corrected:** suggested-deposit hard cap; invoice status gate (Completed+ReadyPickup+Invoiced); posture/blocking reasons; workspace Collect vs Pay labels.

**Preserved:** payment requires issued invoice; deposits refuse terminal ROs; capture failures do not post; idempotent intents; invoice refresh settlement blocks; Close-Paid with balance due; authorization billing caps; no over-collection beyond allowed.

## 4. Deposit/advance/full-payment behavior

Authorized owe-today is collectable in one deposit/capture while In Progress / Waiting Parts / etc. Suggested amount remains a default, not a ceiling. Pre-approval advances still allowed via suggested remainder when owe-today is 0.

## 5. Invoice settlement

Final invoice may issue from Completed, Invoiced (status without doc), or Ready Pickup. Ready Pickup still auto-issues on transition. Deposits apply at issue; remaining collected as invoice payments.

## 6. Concern-status findings/corrections

Parts clear no longer demotes Waiting Parts → Pending (“Not started”); resumes **In Progress**. Concern production remains independent of RO status / Job Board lanes.

## 7. RO-status inventory & simplification

Persisted 12-value enum unchanged. Forward menus hide **Invoiced** and **Ready for Work**. Paid remains close variant + balance, not an RO status.

## 8. Historical compatibility

No enum deletes, no destructive migrations, no reinterpretation of historical ledger/invoice rows.

## 9. Files changed (Core)

See PR #274. Key: `RepairOrderDepositRecordingGuard`, `RepairOrderFinalInvoiceEligibility`, `GenerateInvoiceSnapshotAction`, `RepairOrderFinancialPresenter`, payment strip/workspace blades, `RepairOrderLifecycleSelectProjection`, `ScopeProductionStatusFromPartsSync`, focused tests + FINDINGS.

## 10. Core PRs / SHAs

| Item | Value |
|---|---|
| Baseline | `c379a2c818a37b2a76eae2c111dbfe08a42aeb44` |
| PR | https://github.com/EdwardSoaresJr/ark-core/pull/274 (merged) |
| Merge SHA | `2a3ab9ef7b11bcb941f05b2fbacb08ac0d8d071f` |
| Feature tip | `e4221161` (included in merge) |

## 11. SaaS PRs / SHAs

| Item | Value |
|---|---|
| Baseline | `2f513445c1afa7c9156ed5d6dadd116810dcc0ba` |
| PR | https://github.com/EdwardSoaresJr/ark-stancl/pull/365 (merged) |
| Merge / staging HEAD | `6e0fc9cf70c181c8b33ae56f1a3e355a192422ca` |
| Pinned Core | `2a3ab9ef7b11bcb941f05b2fbacb08ac0d8d071f` |

## 12. Database migrations

None.

## 13. Automated tests

Local focused suites passed (deposit guard, overhaul matrix, workflow UI, capture integrity, presenter axes, scope production, worksheet continuity, financial core authority, concern controls, status catalog). CI focused/targeted/lint/security/comms-boundary passed on PR #274.

## 14. Staging browser acceptance

| Check | Result |
|---|---|
| Host | `ark-stancl-staging` `157.230.172.143` env=`staging` |
| Deploy | `6e0fc9cf` with Core `2a3ab9ef`; `/up` 200 on staging/demo/alpha |
| Vendor markers | Deposit fix + FinalInvoiceEligibility + workspace labels present under `vendor/ark/core` |
| Alpha RO #21 In Progress | **Collect deposit / advance** shown; invoice blocking reason visible; status menu omits Invoiced/Ready for Work |
| Full advance | Recorded **$165.00 cash** deposit (ledger entry #64); RO remained **`in_progress`**; no invoice issued |
| No real card capture | Cash ledger only |

## 15. Remaining limitations

- Free-form pre-approval deposits with no suggested/estimate anchor: deferred.
- Persisted 12-status enum collapse: deferred (compatibility migration).
- Job Board Parts lane still RO-status based (not concern-derived).
- Composer lock content-hash warning observed during staging deploy (SHA pin string replace); dependencies resolved and Core pin verified.

## 16. Production rollback

Production untouched. Staging rollback: redeploy SaaS `2f513445` on staging host. Production, when later authorized, would redeploy prior pin `2f513445` / Core `c379a2c8` via the normal shop-release path.

## 17. Production-readiness verdict

**READY FOR STAGING ACCEPTANCE / NOT PRODUCTION-READY WITHOUT SEPARATE AUTHORIZATION.**

Financial P0/P1 corrections are merged, pinned, deployed, and browser-verified on staging. Production cutover requires an explicit “Deploy the approved release” naming this artifact and the production target.
