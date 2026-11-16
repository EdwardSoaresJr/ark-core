# FINDINGS — Overnight Financial & Communications Integrity

Tested SHA: Core `12adc0b4` · SaaS pin `677b929b` (prod currently serving) · Staging tip `7a5ca33b` (#271, not activated).  
Campaign folder: `review-artifacts/overnight-financial-comms-integrity-2026-11/`.

## P0 — Critical

None confirmed that cause customer over-billing, duplicate live charges, cross-tenant exposure, or credential leak on the hosted path.

## P1 — High

### F-FIN-01 — Authorized/sold read-model drifts after unapproved price increase (fee recomputation)
- **Repo:** ark-core  
- **Workflow:** Approve concern → raise line price → read `approvedTotalsForRead` / invoice basis  
- **Expected:** Authorized cents remain exactly the approved snapshot (e.g. $900.00).  
- **Observed (pre-fix):** $900.00 → $898.95 after first increase; → $898.05 after second (`evidence/reconciliation-s1-s3-owner-case.txt`). Invoice followed the drifted authorized figure (still **below** original approval, not above).  
- **Impact:** Customer is not over-billed, but authorization is not penny-immutable when shop fees reallocate against living lines.  
- **Prod:** Potentially affected on any RO with percent shop fees + post-approval price edits until this fix ships.  
- **Root cause:** After an unapproved raise, capped percent shop-fee re-proration changed living fee/tax on still-covered sibling lines. `approvedInvoiceableLines` only froze raised lines, so fee-drifted siblings billed live (slightly under the approved snapshot). `workOutside` summed living deltas and could overstate the pending increase by the same drift. Intentional priced-work cuts (qty/price/discount) remain allowed to bill below the prior authorization while living total stays at/under the ceiling.  
- **Fix (branch `fix/f-fin-01-authorized-cent-drift`):** Apply recorded approved amounts for every covered line; freeze when living total exceeds the ceiling or when only fee/tax drifted; bill live only for intentional priced-work reductions at/under the ceiling. `workOutsideApprovedSalesCents` = living approved-concern total − authorized ceiling.  
- **Regression:** `tests/Feature/Operations/AuthorizedCentDriftTest.php` (owner $900.00 case: authorized stays **90000¢**, outside **8981¢**, living A **98981¢**).  
- **Status:** **FIXED IN PR** — not yet merged/deployed. Does not rewrite historical ApprovalEvent rows; open ROs re-read correctly after deploy. Core #74 / #72 / #96 remain separate.

### F-RPT-01 — Core #74 still live: pre-invoice sold reports sum full approved-concern lines
- **Repo:** ark-core (`OperationalReportTotals::soldLineQuery` / `soldTotalCentsByRepairOrderId`)  
- **Evidence:** `evidence/issue-74-sold-fallback.json`, `evidence/issues-72-74-95-96.json` — authorized $165 / living $495 / report $495; another probe authorized $898.95 vs report $989.81.  
- **Impact:** Owner/sales dashboards can treat unapproved increases as sold before invoice. Native issued invoices still fall back to line sums unless a legacy invoice snapshot id exists.  
- **Prod:** Affected for pre-invoice / non-legacy reporting.  
- **Fix:** Cap report sold totals at approved authority (or require posted invoice snapshot).

### F-RPT-02 — Core #72 still live: UnresolvedAuthorizationReport omits raised approved lines
- **Repo:** ark-core (`UnresolvedAuthorizationReport` uses `lineCovered`, which stays true for raised approved lines)  
- **Evidence:** `evidence/cross-system-auth-increase.json`, `evidence/issues-72-74-95-96.json`  
- **Impact:** Performed work with unapproved dollar increases can be invisible to the unresolved-auth report (added uncovered lines still appear).  
- **Fix:** Include `linesRequiringAuthorization` / increase deltas in the report.

### F-DOC-01 — Core #96 still live: issued invoice carries estimate-approval wording
- **Repo:** ark-core document footer / snapshot  
- **Evidence:** `issues-72-74-95-96.json` (`By approving this estimate…` present on invoice HTML + snapshot).  
- **Impact:** Customer-facing legal/UX mislabeling of invoices.  
- **Fix:** Invoice-specific footer copy; stop reusing estimate authorization paragraphs.

### F-COM-01 — Stock Core customerless composer does not enforce STOP (hosted OK)
- **Repo:** ark-core (`SendOutboundContactMessageAction`); hosted mitigated by SaaS `GovernedOutboundSmsTransport`  
- **Evidence:** `COMMUNICATIONS_ACCEPTANCE.md`, `evidence/phase3-tests/Phase3CommsIntegrityTest.php.txt`  
- **Impact:** Self-hosted / non-governed Core can SMS an opted-out number via customerless compose. Hosted shops refuse via central opt-out table. STOP from unknown numbers is not persisted in Core alone.  
- **Prod hosted:** Not proven bypass (governed path). **Stock Core:** live defect.  
- **Fix:** Consent-by-phone in contact send + persist STOP by phone for unknown callers.

### F-PAY-01 — Wallet auto top-up can double-charge on provider timeout
- **Repo:** ark-stancl (`WalletRecharges::recharge`)  
- **Evidence:** static architecture audit ([Payments architecture](72da143b-4ecd-457c-8a71-6df212295b09)); `evidence/F-PAY-EXPOSURE.md`; SaaS PR #273 (`fbd8a3c0`).  
- **Expected:** Timeout/5xx after possible capture keeps one idempotency key and reconciles.  
- **Observed (code on prod `677b929b`):** exception marks payment `failed`, retry uses `count+1` key → Square will not dedupe; later webhook may credit the first payment.  
- **Impact:** Shop (subscriber) could be charged twice for wallet top-up. Distinct from shop-customer ARK Pay.  
- **Prod exposure (2026-11-16, read-only):** **ACTIVE PATH** — LugsNPlugs wallet #1 autopay active + card on file; scheduler every 5m; not currently below threshold; **0** historical duplicate `provider_payment_id` / idempotency keys. Amaya/mvworkshop not on autopay.  
- **Containment (not applied — needs owner auth):** pause wallet #1 `autopay_status`, or ship the fix. Do not disable broader Square billing.  
- **Fix (PR open, not merged):** durable attempt + `provider_unconfirmed`; reuse Square idempotency key; exclusive claim across Square call; reconcile by provider id then `reference_id` list; no POST after 24h unconfirmed window; credit once. PR scoped to wallet files only (pin/deploy commits removed). Tests: timeout/retry/late webhook/claim/reference/stale + suite (25).  
- **Status:** **DEPLOYED** — production SaaS `7a421b61` / Core `465d2713` (2026-11-16). #274 merged (`0131bbd3` on main). Wallet #1 autopay remains **active**. See `F-PAY-PRODUCTION-DEPLOY.md`.

### F-PAY-02 — Voiding a card ledger entry does not refund the processor
- **Repo:** ark-core (`VoidLedgerEntryAction`)  
- **Evidence:** static audit ([Payments architecture](72da143b-4ecd-457c-8a71-6df212295b09)); `evidence/F-PAY-EXPOSURE.md`; Core PR #212 (`f57578a4`).  
- **Impact:** Balance due returns while the customer card remains charged; no Square refund API call exists in either repo.  
- **Prod exposure:** code defect confirmed; impact only if staff void processor-posted card entries (not an automated charge path).  
- **Fix (PR open, not merged):** refuse void for succeeded captures, in-flight/ambiguous statuses, missing provider refs, and legacy gateway links; cash / manual external card without a processor attempt still voidable.  
- **Unresolved:** full processor-confirmed refund workflow + Square→ledger sync remain F-PAY-03.  
- **Status:** **DEPLOYED** via SaaS Core pin `465d2713` on production (with #274). See `F-PAY-PRODUCTION-DEPLOY.md`.

## P2 — Medium

### F-UI-01 — Core #95 partial: APPROVED concern with increase is marked, but line card clarity incomplete
- Line card shows “Needs authorization” and raised price; approved ceiling amount not shown on the line card itself (concern region is clearer).  
- **Evidence:** `issues-72-74-95-96.json`.

### F-COM-02 — Hermetic SMS tests fail without Twilio/platform env (“Shop messaging is disabled”)
- Masks real consent failures in CI without `TWILIO_*` or `enableHostedPlatformSendWithoutCoreMirror()`.  
- Product path OK when messaging enabled.

### F-COM-03 — Estimate send creates portal token before consent gate
- STOP still blocks delivery; orphan token/short-link may remain (P3–P2).

### F-ACCT-01 — Twilio HQ COGS omits unknown-cost events (looks like zero)
- Settle path does **not** store unknown as zero; report layer understates COGS.  
- See `PROVIDER_ACCOUNTING_AUDIT.md`.

### F-ACCT-02 — Possible cross-ledger double-count risk (usage + distinct expense)
- Unproven; query required before #271 activation.

### F-ACCT-03 — ProviderCostReconciler can normalize unparseable price to 0
- Reconciler only; settle path rejects ≤0.

### F-PAY-03 — Shop-customer refunds/voids are ledger-only; Square refunds do not update Core ledger
- Webhooks raise findings; ledger and processor can diverge.

### F-PAY-04 — SaaS subscription lifecycle not production-safe as documented
- Simulated billing is default; live Square subscriptions gated. Spec gaps (failed renewals, suspended not acting) remain per docs / #89 / #224. VizyPay for ARK Pay and SaaS recurring is **planned only**.

### F-FIN-02 — Dual balance formulas / transitional invoice refresh
- `BalanceDueCalculator` and `FinancialPositionCalculator` duplicate formulas; `RefreshCustomerInvoiceAction` / living snapshot refresh still exist against Financial Authority v2 immutability doctrine (architecture map). Supplemental approval can leave invoice stale until explicit refresh (policy F2 in reconciliation).

### F-MAT-01 — Parts import validates against live matrices, not RO freeze
- PartsTech/dealer import `Rule::in(live matrices)` can reject an archived-but-frozen key that worksheet pricing still allows (#183 residual G10).

## P3 — Low

- GET compose with `phone[]` array → 500 (no write).  
- Concurrent compose may 500 on unique index (no duplicate row).  
- SMS consent suite hermeticity / messaging-disabled ordering.

## Already corrected (do not reopen as live product bugs)

| Issue | Status on 12adc0b4 |
|---|---|
| #67 authorization / bill only authorized | MERGED; invoice/sold authority capped in engine (with F-FIN-01 fee drift caveat) |
| #119 / #211 customerless compose | CLOSED; GET does not create; POST opens; no customer creation |
| #183 parts matrix freeze | CLOSED; RO A keeps frozen matrix; newer matrices refused (`PartsMatrixAndTaxForwardOnlyTest` + S5 recon PASS) |
| Labor matrix freeze | S6 recon PASS |
| Flat shop fee × concerns | S4 recon PASS |
| Hosted RO estimate SMS vs STOP | PASS when messaging enabled (`evidence/optout-estimate-sms.json`) |
