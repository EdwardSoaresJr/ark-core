# Shop Fees — per-concern + caps (PR #188)

**Branch:** `feat/shop-fee-per-concern`  
**Commit:** `4533010a39810c2c551751b379eee2f997a066dd`  
**Base:** `main` @ `2b32d48` (post #187)

## Policy / schema

- `ShopFeeProfile` schema **2** adds mode `per_concern`.
- Modes: `percent` | `flat` (per RO) | `per_concern`.
- Caps apply only to `percent` and `per_concern`.
- Schema-1 stored profiles never reinterpret `flat` as `per_concern` (unknown/unsafe modes fall back safely).
- No new DB columns (existing `shop_fee_mode` string + `shop_fee_flat_cents` + `shop_fee_cap_cents` + RO `shop_fee_profile` JSON).

## Calculation

- Percentage: unchanged (eligible-line rate sum, then optional RO cap).
- Flat per RO: once per RO across eligible lines; **cap ignored**.
- Per concern: unique eligible concern IDs × flat cents, then optional RO cap; allocated across eligible lines by subtotal share.
- Eligibility preserves posture exemptions and excludes declined/deferred/draft (and pending recommended when approved work exists).

## Forward-only

- Living ROs without a profile are stamped before fee settings save.
- Fee settings save does **not** recalculate living ROs or rewrite open estimate snapshots.
- New ROs stamp the active profile at create.

## Local tests

- `ShopFeePerConcernTest` + `ShopFeeFlatDollarTest` + `ShopFeePerLineAllocationTest` — pass
- `PricingEngineSafetyTest`, `ShopSettingsFinancialTablerTest`, focused Shop Fees persistence — pass
- `FinancialCoreAuthorityTest` — pass

## Alpha staging

| Pin | SHA |
|---|---|
| Core | `4533010a39810c2c551751b379eee2f997a066dd` |
| SaaS | `eb859c2159a1821d87e51721698d4608c3bb20c1` |

### Calc evidence (synthetic RO #41)

Configured temporarily: per-concern `$15`, cap `$40`, then restored shop fees to disabled/percent.

| Eligible concerns | Fee cents |
|---:|---:|
| 1 | 1500 |
| 2 | 3000 |
| 3 | 4000 (capped) |
| 4 (one multi-line) | 4000 (capped) |

Existing open RO #40 fee total unchanged (`0` → `0`); profile unchanged after settings change.

Shop fee settings restored to: enabled=false, mode=percent, rate=0, flat=null, cap=null.

### UI screenshots

- `shop-fees-percent-desktop.png`
- `shop-fees-flat-ro-desktop.png` (cap hidden)
- `shop-fees-per-concern-desktop.png`
- `shop-fees-per-concern-mobile.png`

## Remaining risks

- Billing-class rate overrides remain percentage-oriented (pre-existing); flat/per-concern still honor enable/disable and posture eligibility.
- Authorization ceilings still depend on approved-scope totals; supplemental auth must continue to follow existing document-update rules when a newly authorized concern raises the legitimate fee.
- Staging currently runs the feature branch tip for acceptance; production remains on prior pins / untouched.

**Do not merge without owner review. No production deployment.**
