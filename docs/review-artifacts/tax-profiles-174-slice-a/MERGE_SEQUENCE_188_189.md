# Merge / integration sequence — PR #188 and PR #189

Both branches diverge from `main` @ `2b32d48`. Neither should be force-merged over the other.

## What each PR owns

| PR | Branch | Concern |
|---|---|---|
| [#188](https://github.com/EdwardSoaresJr/ark-core/pull/188) | `feat/shop-fee-per-concern` | Per-concern shop fees, fee calc/auth, fee UI |
| [#189](https://github.com/EdwardSoaresJr/ark-core/pull/189) | `feat/tax-profiles-slice-a` | Tax profiles catalog Slice A, Tax UI, Financial Rules 240px layout |

## Overlapping files (both modified vs `2b32d48`)

- `app/Ark/Operations/Settings/ShopFinancialSettingsController.php`
  - **#188:** `updateFees` — `per_concern` mode, flat/cap preserve rules
  - **#189:** `updateTax` (+ new tax profile/component routes)
  - Conflict risk: **low–medium** (adjacent methods; rebase must keep both)
- `app/Ark/Operations/Settings/ShopSettings.php`
  - **#188:** fee helpers / fillable for flat+per_concern
  - **#189:** `tax_profiles`, `tax_document_presentation`, `taxProfiles()` helpers
  - Conflict risk: **low** (different fields)
- `resources/views/operations/settings/shop.blade.php`
  - **#188:** Shop Fees panel (`per_concern` radio, cap visibility)
  - **#189:** Tax panel include + shared master-detail styles include; Labor/Parts layout wrappers live in partials
  - Conflict risk: **medium** (same financial card; merge both panels)

Non-overlapping (safe either order): fee calculator/auth/tests (#188); `ShopTaxCatalog`, tax migration, tax panel partial, tax tests (#189).

## Safe sequence (recommended)

1. **Land #188 first** (owner-approved fee work) onto `main`.
2. **Rebase #189 onto updated `main`** (or merge `main` into #189).
3. Resolve controller/settings/shop.blade conflicts by **keeping both** fee and tax changes.
4. Re-run:
   - Shop fee suites (`ShopFee*`)
   - Tax Slice A suites (`ShopTaxProfilesSliceATest`, Tabler, ShopSettings tax assertions)
   - Focused CI on the rebased #189 tip
5. Then owner-merge #189.

Alternative (if #189 is accepted first): reverse the order — land #189, rebase #188, keep both fee+tax edits, re-run fee + tax suites.

## Do not

- Merge either PR as part of Slice A polish.
- Squash one branch in a way that drops the other’s controller/settings hunks.
- Assume staging tip equality: Alpha may pin one feature branch at a time for UI acceptance.

## Staging note

Alpha was pinned to #189 for Tax/Labor/Parts screenshots. Re-pin to the approved tip after each acceptance window; do not treat staging as production.
