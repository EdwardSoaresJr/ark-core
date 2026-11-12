# Shop Fees — per-concern + caps (PR #188)

**Branch:** `feat/shop-fee-per-concern`  
**Base:** `main` @ `2b32d48` (post #187)  
**Status:** Owner review — provisional UI/calc accept; do not merge.

## Final pins (owner-review gate)

| Pin | SHA |
|---|---|
| Code tip (auth/calc fixes) | `3e7cd6f3df87481b79f4fe7f0d697b55576577ff` |
| Alpha Core (`vendor/ark/core`) | `ed4d7b80164587b168afeb0d7579ffd496b02180` (includes code tip `3e7cd6f`) |
| Alpha SaaS (`/var/www/ark-stancl`) | `eb859c2159a1821d87e51721698d4608c3bb20c1` |
| GitHub Tests on `ed4d7b8` | **success** — https://github.com/EdwardSoaresJr/ark-core/actions/runs/43157415114 |

Synthetic staging ROs kept (`#43` fee-auth path). Temporary `$15`/`$40` settings restored to `enabled=false`, `mode=percent`, `rate=0`, `flat=null`, `cap=null`.

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

**Intentional differences (not silent):**

1. Flat-per-RO **ignores** `shop_fee_cap_cents` (caps are percent/per-concern only).
2. Per-concern disposition filtering is **new** and must not apply to percent/flat (regression suite proves it does not).

Suite: `ShopFeeDispositionRegressionTest` — **pass**.

## Check 2 — supplemental authorization ($15 / concern, cap $40)

### Local Pest

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

### Alpha staging (tenant `alpha`, synthetic RO `#43`)

| Metric | Result |
|---|---:|
| fee after 2 (live) | 3000 |
| approved after 2 | 3000 |
| approved with recommended 3rd | 3000 |
| live with recommended 3rd | 3000 |
| 3rd fee while recommended | 0 |
| approved after authorizing 3rd | 4000 |
| fees after 3rd | one=1500, two=1500, three=1000 |
| settings restored | enabled=false, percent, rate=0, flat/cap null |

Supporting fixes:

- `RepairOrderReadCache` flushes on `RepairOrderConcern` writes.
- First-authorization batch: one recalculate, then scopes with `recalculate: false`.
- After any scope exists, only **Approved** concerns are fee-eligible.
- Per-concern allocation is **per concern’s lines** in ascending concern id order, with cap remainder on later concerns.

Suite: `ShopFeePerConcernAuthorizationTest` — **pass**.

## Check 3 — CI + broader financial regressions

### Local

```text
ShopFeeDispositionRegressionTest|ShopFeePerConcernAuthorizationTest|ShopFeePerConcernTest|ShopFeeFlatDollarTest
→ 34 passed

filter='ShopFee|PricingEngineSafety|EstimateTotals|Authorization|ApprovedWork|InvoiceSnapshot|FinancialDocument|CommercialPricing'
→ 221 passed, 1919 assertions
```

### GitHub

- Prior tip `161f2fc` Tests workflow **success** (cancelled `4533010` run not counted).
- Owner-review tip Tests workflow: must complete **success** on the PR head after this package (URL in PR comment). `pest` under Regression may remain skipped by workflow rules; the **Tests** workflow is the required gate.

## Calculation (final)

- Percentage: eligible-line rate sum, optional RO cap — disposition filter **not** applied.
- Flat per RO: once per RO across eligible lines; **cap ignored**; disposition filter **not** applied.
- Per concern: `$flat` once per eligible concern on **that concern’s lines**; optional RO cap applied in concern-id order; declined/deferred/draft never count; recommended counts only before any `ApprovedWorkScope` exists.

## Forward-only

- Living ROs without a profile are stamped before fee settings save.
- Fee settings save does **not** recalculate living ROs or rewrite open estimate snapshots.
- New ROs stamp the active profile at create.

## Changed files (owner-review gate)

- `app/Ark/Operations/Financial/EstimateTotalsCalculator.php`
- `app/Ark/Operations/Approvals/RecordCustomerAuthorizationAction.php`
- `app/Ark/Operations/RepairOrders/RecordApprovedWorkScopeAction.php`
- `app/Ark/Operations/RepairOrders/RepairOrderReadCache.php`
- `tests/Feature/Operations/ShopFeeDispositionRegressionTest.php` _(new)_
- `tests/Feature/Operations/ShopFeePerConcernAuthorizationTest.php` _(new)_
- `docs/review-artifacts/shop-fee-per-concern/EVIDENCE.md`

(Earlier PR commits also include profile/UI/settings/tests/screenshots.)

## UI screenshots

- `shop-fees-percent-desktop.png`
- `shop-fees-flat-ro-desktop.png` (cap hidden)
- `shop-fees-per-concern-desktop.png`
- `shop-fees-per-concern-mobile.png`

## Remaining risks

- Billing-class rate overrides remain percentage-oriented (pre-existing).
- Authorization Store still will not pull post-approval recommended work into a later recording; supplemental approval is disposition → Approved (by design).

**Do not merge without owner final approval. No production deployment.**
