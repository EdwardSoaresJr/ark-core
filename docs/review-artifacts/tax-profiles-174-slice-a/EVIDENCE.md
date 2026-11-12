# Core #174 — Slice A evidence (HOLD MERGE polish)

**Branch:** `feat/tax-profiles-slice-a`  
**Base:** `origin/main` @ `2b32d48`  
**Issue:** https://github.com/EdwardSoaresJr/ark-core/issues/174  
**PR:** https://github.com/EdwardSoaresJr/ark-core/pull/189  
**Merge sequence vs #188:** [MERGE_SEQUENCE_188_189.md](./MERGE_SEQUENCE_188_189.md)

## Pins

| Pin | SHA |
|---|---|
| Main (audit base) | `2b32d48b6bf2a01f867f1cd9e256b3392169db4f` |
| Failed CI tip (owner review) | `0fbcf8bca32a956a5e4a3e5a5283cfcbfb38f88e` |
| PR / Slice A tip (this polish) | _(set after push)_ |
| Alpha Core | _(re-pin after push for screenshots)_ |
| Alpha SaaS | `eb859c2159a1821d87e51721698d4608c3bb20c1` |

Staging only. No production pin, merge, or tenant migration in this pass.

## CI root cause on `0fbcf8b` (Tests / focused)

Workflow: https://github.com/EdwardSoaresJr/ark-core/actions/runs/43264768298  

- **Regression job:** skipped — not counted as passed.
- **Focused regressions (2), not in baseline:**
  - `ShopSettingsTest::admin can view operational shop settings defaults` — still asserted legacy copy (`Sales tax rate`, `Tax display name`, `Saved tax setup:`).
  - `ShopSettingsTest::tax and shop fee switches are checkboxes…` — still asserted top-level `taxable_*` checkboxes and `x-data="{ taxEnabled: true }"`.
- Slice A moved taxable flags under `tax_profiles[0][…]` and renamed the tax heading to **Sales tax profiles**.
- Fix: update those assertions; add coverage for `+ Add tax profile` / add-component affordances.
- Local re-run (passed): `ShopTaxProfilesSliceATest|ShopSettingsFinancialTablerTest|ShopSettingsTest` tax/view cases|`PartsMatrixAndTaxForwardOnlyTest` (and related labor/parts settings filters).

## Slice A delivered (including HOLD MERGE polish)

1. `ShopTaxCatalog` — named profiles + components; combined-rate → TaxProfile schema 1 projection.
2. Additive migration `tax_profiles` + `tax_document_presentation` on `shop_settings`.
3. Tax settings UI: shared `ops-financial-master-detail` (~240px) with Labor and Parts Matrix.
4. **Functional** Add tax profile / Add component / Deactivate·Reactivate via sibling POST/PATCH forms (`form=` buttons — not nested forms, not dummy UI). Stable profile keys + component UUIDs survive reload.
5. Labor nav: column-stacked label + short **Default** badge (240px width unchanged) so “Mechanical” no longer wraps beside a long badge.
6. Tests for add/deactivate/validation + updated ShopSettings/Tabler scrapers.

## Layout confirmation

- Desktop: Labor / Parts / Tax share `--ops-financial-nav-width: 240px` and the same flex master-detail shell.
- Mobile: nav stacks above editor (`d-lg-none` helper copy on Tax); screenshots at 320 / 375 / 430.
- Fresh same-width desktop + mobile shots re-captured on the polish tip (see files below).

## Explicitly deferred

- **Slice B (owner):** TaxProfile schema 2 immutable selected-profile/component snapshot; service-location + manual profile selection; pre-auth selection change with audited recalc; post-approval tax **increase** → supplemental auth before extra cents collectible; decreases without extra auth; preserve historical approvals/snapshots/invoices/terminal records; **no separate tax engine**. ZIP may suggest a profile; selection confirms against **service location** (fleet: RO service location, not HQ).
- **Slice C (owner):** component-level calc; combined/itemized document rendering; customer tax exemption + evidence independent of Billing Class; jurisdiction exemptions; cent-level reconcile across invoices/approvals/PDFs/portal/reporting.
- Colorado SUTS / paid providers: not in Slice A.

## Compatibility

| Artifact | Slice A behavior |
|---|---|
| Existing `TaxProfile` schema 1 on ROs | Unchanged; still authoritative for calc |
| Shop legacy tax columns | Synced from default profile combined rate + applicability on save |
| Living null-stamp ROs | Stamped with pre-change rules before settings save |
| Issued/archived documents | Untouched |
| `tax_document_presentation` | Stored only; itemized rendering in Slice C |

## Screenshots

Desktop (same browser width):
- `desktop-labor.png`
- `desktop-parts.png`
- `desktop-tax.png`

Mobile:
- `mobile-tax-320.png` / `mobile-tax-375.png` / `mobile-tax-430.png`
- `mobile-labor-430.png` / `mobile-parts-430.png`

**Do not merge without owner review. No production deployment.**
