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

## Safe sequence (current)

1. **#188 landed** on `main` @ `17cd340` (merge commit).
2. **#189 rebased** onto that main (fee + tax coexist).
3. **Shop Fees eligibility** added on the same #189 tip (schema-3 `applies_to`) — keep on #189 so the feature is not lost in a second divergent PR.
4. Owner reviews eligibility + Tax Slice A on **one** Alpha Core pin.
5. Only then owner-merge #189. **Do not start Tax Slice B** before that acceptance.

Alternative if owner wants a focused fees PR: after accepting tax-only #189, cherry-pick eligibility commits onto a new branch from `main`. Prefer keeping them on #189 to avoid re-splitting overlapping `ShopSettings` / `shop.blade.php` / fee controller surfaces.

## Do not

- Merge #189 or any new PR without owner authorization.
- Squash in a way that drops fee eligibility, per-concern fees, or tax catalog hunks.
- Start Tax Slice B or automated jurisdiction lookup while this gate is open.
- Assume staging tip equality: Alpha may pin one feature branch at a time for UI acceptance.

## Staging note

Alpha was pinned to #189 for Tax/Labor/Parts screenshots. Re-pin to the approved tip after each acceptance window; do not treat staging as production.
