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
| PR / Slice A tip (this polish) | `9f4ac42d11149ad346a90d4ad47249b9a43f0e94` |
| Alpha Core (screenshot pin) | `e3f87b8a5beb1c1202e0a7bf6b0fd2ccd1be046a` (UI polish; tip `9f4ac42` is test/assertion only) |
| Alpha SaaS | `eb859c2159a1821d87e51721698d4608c3bb20c1` |

Staging only. No production pin, merge, or tenant migration in this polish (Alpha already had the Slice A additive migration). Temporary tax-on sample restored to tax disabled after screenshots.

## CI root cause on `0fbcf8b` (Tests / focused)

Workflow: https://github.com/EdwardSoaresJr/ark-core/actions/runs/43264768298  

- **Regression job:** skipped — not counted as passed.
- **Focused regressions (2), not in baseline:**
  - `ShopSettingsTest::admin can view operational shop settings defaults` — still asserted legacy copy (`Sales tax rate`, `Tax display name`, `Saved tax setup:`).
  - `ShopSettingsTest::tax and shop fee switches are checkboxes…` — still asserted top-level `taxable_*` checkboxes and `x-data="{ taxEnabled: true }"`.
- Slice A moved taxable flags under `tax_profiles[0][…]` and renamed the tax heading to **Sales tax profiles**.
- Fix: update those assertions; cover `+ Add tax profile` / add-component affordances.
- Local re-run (passed): `ShopTaxProfilesSliceATest`, Tabler financial scrapers, ShopSettings tax/view cases, Parts/Labor forward-only filters.
- Follow-up on tip `9f4ac42`: tax saves redirect to `section=financial&financial-tab=tax`; ShopSettings assertions updated. Focused Tests **passed** on https://github.com/EdwardSoaresJr/ark-core/actions/runs/43271103477 (Regression still skipped — not counted as passed).

## Slice A delivered (including HOLD MERGE polish)

1. `ShopTaxCatalog` — named profiles + components; combined-rate → TaxProfile schema 1 projection.
2. Additive migration `tax_profiles` + `tax_document_presentation` on `shop_settings`.
3. Tax settings UI: shared `ops-financial-master-detail` (~240px) with Labor and Parts Matrix.
4. **Functional** Add tax profile / Add component / Deactivate·Reactivate via sibling POST/PATCH forms (`form=` buttons — not nested forms, not dummy UI). Stable profile keys + component UUIDs survive reload.
5. Labor nav: column-stacked label + short **Default** badge (240px width unchanged) so “Mechanical” no longer wraps beside a long badge.
6. Tests for add/deactivate/validation + updated ShopSettings/Tabler scrapers.

## Layout confirmation (Alpha @ `e3f87b8`)

Measured at 1440×900 desktop:

| Surface | `.ops-financial-master-detail__nav` width | Notes |
|---|---|---|
| Labor | **240px** | `flex-direction: column`; badge text `Default`; label `Mechanical` single line |
| Parts | **240px** | same shared CSS var |
| Tax | **240px** | `+ Add tax profile` / `+ Add component` present |

Mobile: nav stacks above editor; usable at 320 / 375 / 430.

## Core vs SaaS tax-jurisdiction boundary (owner clarification on #174)

**Automated tax-jurisdiction lookup belongs exclusively in ARK SaaS, NOT ARK Core.**

| Owner | Responsibility |
|---|---|
| **Core** | Named tax profiles/components; manual city/ZIP associations; RO jurisdiction selection; frozen tax rules, calculations, exemptions, authorization, invoices, documents; provider-neutral, validated, auditable application of externally supplied jurisdiction data |
| **SaaS** | Colorado SUTS/GIS and other provider integrations; credentials; API requests; address verification; rate freshness; caching; usage metering; automatic jurisdiction suggestions / optional profile updates; SaaS billing/entitlement for automated lookups |

Preserve this boundary in Slice B/C plans. **Do not** put provider-specific code, API credentials, external lookup dependencies, or SaaS metering in Core. Core must remain usable offline/self-hosted with manually configured tax profiles. **Do not implement automated lookup yet.**

## Explicitly deferred (owner architectural decisions — do not implement in Slice A)

- **Slice B:** TaxProfile schema 2 immutable selected-profile/component snapshot; service-location + manual profile selection; pre-auth selection change with audited recalc; post-approval tax **increase** → supplemental auth before extra cents collectible; decreases without extra auth; preserve historical approvals/snapshots/invoices/terminal records; **no separate tax engine**. ZIP may suggest a profile; selection confirms against **service location** (fleet: RO service location, not HQ). Automated lookup suggestions remain SaaS-owned.
- **Slice C:** component-level calc; combined/itemized document rendering; customer tax exemption + evidence independent of Billing Class; jurisdiction exemptions; cent-level reconcile across invoices/approvals/PDFs/portal/reporting.
- Colorado SUTS / paid providers: SaaS only — not in Core Slice A/B/C implementation.

## Concurrent Shop Fees eligibility (on same #189 tip — not Tax Slice A)

Shop Fees **Apply shop fees to** (labor / parts / both) landed on this integrated branch before Slice B. See [integrate-188-189/EVIDENCE.md](../integrate-188-189/EVIDENCE.md). Distinguish fee eligibility commits from Tax Slice A when reviewing.

## Compatibility

| Artifact | Slice A behavior |
|---|---|
| Existing `TaxProfile` schema 1 on ROs | Unchanged; still authoritative for calc |
| Shop legacy tax columns | Synced from default profile combined rate + applicability on save |
| Living null-stamp ROs | Stamped with pre-change rules before settings save |
| Issued/archived documents | Untouched |
| `tax_document_presentation` | Stored only; itemized rendering in Slice C |

## Screenshots (same Alpha Core `e3f87b8`)

Desktop (same browser width 1440):
- `desktop-labor.png`
- `desktop-parts.png`
- `desktop-tax.png` (tax temporarily enabled with Colorado Springs 2.9+3.12 components for UI review, then restored off)

Mobile:
- `mobile-tax-320.png` / `mobile-tax-375.png` / `mobile-tax-430.png`
- `mobile-labor-430.png` / `mobile-parts-430.png`

**Do not merge without owner review. No production deployment.**
