# Shop Fees — per-concern + caps (PR #188)

**Branch:** `feat/shop-fee-per-concern`  
**Base:** `main` @ `2b32d48` (post #187)  
**Status:** Owner review — provisional UI/calc accept; do not merge.

## Final pins (owner-review gate)

| Pin | SHA |
|---|---|
| PR head / Core tip | `0b8ec996ca5b9a8f0b00113c662101baf6ecc8af` |
| Alpha Core (`vendor/ark/core`) | `0b8ec996ca5b9a8f0b00113c662101baf6ecc8af` |
| Alpha SaaS (`/var/www/ark-stancl`) | `eb859c2159a1821d87e51721698d4608c3bb20c1` |

## Policy / schema

- `ShopFeeProfile` schema **2** adds mode `per_concern`.
- Modes: `percent` | `flat` (per RO) | `per_concern`.
- Caps apply only to `percent` and `per_concern`.
- Schema-1 stored profiles never reinterpret `flat` as `per_concern`.
- No new DB columns (`shop_fee_mode`, `shop_fee_flat_cents`, `shop_fee_cap_cents`, RO `shop_fee_profile` JSON).

## Shared flat-dollar setting (intentional)

`shop_fee_flat_cents` is **one stored value** shared by fixed-per-RO and fixed-per-concern.

- Switching **to percent** preserves the stored flat cents (inactive).
- Switching **between flat ↔ per_concern** reuses the same dollars; only the label/meaning changes (`per RO` vs `per eligible concern`).
- So `$25` flat/RO becomes `$25` per concern if the method is switched without editing the amount — intentional shared storage, not a second column.
- Cap cents are likewise preserved when switching away from percent/per-concern.

Locked by `ShopFeePerConcernTest::switching fee methods preserves inactive stored values`.

## Check 1 — percent / flat disposition vs Core main (`2b32d48`)

`perConcernFeeEligibleLines()` runs **only** inside the `isPerConcern()` branch. Percentage and flat-per-RO keep the historical eligible line set (declined/deferred/draft still receive stored fees; estimate totals still omit non-billable dispositions).

| Scenario | Percent | Flat per RO |
|---|---|---|
| Recommended only | unchanged vs main path | unchanged pool |
| Declined mixed | fees still stored on declined lines | flat still pooled across declined+recommended |
| Deferred / draft | fees still stored | n/a (same eligibility) |
| Mixed approved + recommended | fees stored on both; estimate omits recommended once approved exists | same pattern |

**Intentional differences already on this branch (not silent):**

1. Flat-per-RO **ignores** `shop_fee_cap_cents` (caps are percent/per-concern only).
2. Per-concern disposition filtering is **new** and must not apply to percent/flat (regression suite proves it does not).

Suite: `ShopFeeDispositionRegressionTest` — pass.

## Check 2 — supplemental authorization ($15 / concern, cap $40)

| Step | Approved shop fee | Notes |
|---|---:|---|
| Authorize 2 concerns | 3000 | $15 + $15 on each concern’s own lines |
| Add 3rd recommended | 3000 | extra $10 **not** billed; 3rd lines fee `0` |
| Approve 3rd (disposition → Approved) | 4000 | 3rd concern receives cap remainder `$10`; first two keep `$15` |
| 4th at already-reached cap | 4000 | 4th fee `0` |
| Decline one of two authorized | 1500 | remaining concern keeps its own `$15` |
| Multi-line concern | 1500 once | fee once per concern, split across that concern’s lines |
| Issued invoice after supplemental | = approved total | cannot exceed approved amounts |

Product path for supplemental: `UpdateConcernDispositionAction` (Authorization Store does not auto-approve new recommended after an approved scope exists).

Supporting fixes for authorization integrity:

- `RepairOrderReadCache` flushes on `RepairOrderConcern` writes.
- First-authorization batch: one recalculate, then `RecordApprovedWorkScopeAction(..., recalculate: false)`.
- After any scope exists, only **Approved** concerns are fee-eligible (recommended additions cannot raise the fee; flipping to Approved during supplemental counts before the new scope row is written).
- Per-concern allocation is **per concern’s lines** in ascending concern id order, with cap remainder on later concerns (not a single RO-wide pool that dilutes earlier authorized concerns).

Suite: `ShopFeePerConcernAuthorizationTest` — pass.

## Check 3 — CI + broader financial regressions

### Local

```text
ShopFeeDispositionRegressionTest|ShopFeePerConcernAuthorizationTest|ShopFeePerConcernTest|ShopFeeFlatDollarTest
→ 34 passed

filter='ShopFee|PricingEngineSafety|EstimateTotals|Authorization|ApprovedWork|InvoiceSnapshot|FinancialDocument|CommercialPricing'
→ 221 passed, 1919 assertions
```

### GitHub

Prior tip `161f2fc` Tests workflow **success** (focused/targeted/lint/security/communications-boundary). Cancelled run on `4533010` is not counted as a pass.  
Fresh Tests workflow on the owner-review tip must complete successfully after push (recorded below).

## Calculation (final)

- Percentage: eligible-line rate sum, optional RO cap — disposition filter **not** applied.
- Flat per RO: once per RO across eligible lines; **cap ignored**; disposition filter **not** applied.
- Per concern: `$flat` once per eligible concern on **that concern’s lines**; optional RO cap applied in concern-id order; declined/deferred/draft never count; recommended counts only before any `ApprovedWorkScope` exists.

## Forward-only

- Living ROs without a profile are stamped before fee settings save.
- Fee settings save does **not** recalculate living ROs or rewrite open estimate snapshots.
- New ROs stamp the active profile at create.

## Changed files (owner-review gate)

- `app/Ark/Operations/Financial/EstimateTotalsCalculator.php` — per-concern eligibility + per-concern allocation
- `app/Ark/Operations/Approvals/RecordCustomerAuthorizationAction.php` — batch scope pricing
- `app/Ark/Operations/RepairOrders/RecordApprovedWorkScopeAction.php` — `recalculate` flag
- `app/Ark/Operations/RepairOrders/RepairOrderReadCache.php` — flush on Concern
- `tests/Feature/Operations/ShopFeeDispositionRegressionTest.php` _(new)_
- `tests/Feature/Operations/ShopFeePerConcernAuthorizationTest.php` _(new)_
- `docs/review-artifacts/shop-fee-per-concern/EVIDENCE.md`

(Earlier PR commits also include profile/UI/settings/tests/screenshots.)

## Alpha staging

Synthetic fee-auth records kept. Temporary `$15` / `$40` settings restored afterward to:

`enabled=false`, `mode=percent`, `rate=0`, `flat=null`, `cap=null`.

### UI screenshots (unchanged)

- `shop-fees-percent-desktop.png`
- `shop-fees-flat-ro-desktop.png` (cap hidden)
- `shop-fees-per-concern-desktop.png`
- `shop-fees-per-concern-mobile.png`

## Remaining risks

- Billing-class rate overrides remain percentage-oriented (pre-existing).
- Authorization Store still will not pull post-approval recommended work into a later recording; supplemental approval is disposition → Approved (by design).

**Do not merge without owner final approval. No production deployment.**
