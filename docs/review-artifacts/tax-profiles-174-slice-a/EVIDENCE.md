# Core #174 — Slice A evidence

**Branch:** `feat/tax-profiles-slice-a`  
**Base:** `origin/main` @ `2b32d48`  
**Issue:** https://github.com/EdwardSoaresJr/ark-core/issues/174  

## Pins

| Pin | SHA |
|---|---|
| Main (audit base) | `2b32d48b6bf2a01f867f1cd9e256b3392169db4f` |
| PR / Slice A tip | `e57f7a7cb1b012708a5d56125de2c44acbf43193` |
| Alpha Core | `e57f7a7cb1b012708a5d56125de2c44acbf43193` |
| Alpha SaaS | `eb859c2159a1821d87e51721698d4608c3bb20c1` |

Note: Alpha was previously on `#188` shop-fee tip; Slice A staging acceptance re-pinned Core to this branch only (staging, not production). Temporary tax-on sample restored to tax disabled afterward.

## Audit findings (main)

- Single shop-wide tax → RO `TaxProfile` schema **1** (enabled/label/rate/taxable_*).
- Forward-only Tax settings save stamps null living ROs with pre-change rules; no living recalc.
- No multi-component, multi-profile, RO service-location nexus, or billing-class tax exemption.
- Booking/customer addresses exist but are unwired to tax.
- Do **not** base work on obsolete branches; Slice A branched from `origin/main`.

## Slice A delivered

1. Updated #174 with full multi-jurisdiction requirements and slice plan.
2. `ShopTaxCatalog` — named profiles + components, combined-rate projection, legacy single-rate compatibility.
3. Migration `tax_profiles` + `tax_document_presentation` on `shop_settings` (additive; no RO rewrite).
4. Tax settings UI: left-tab profiles, one editor, components table, combined/itemized shop-wide preference (separated).
5. Shared `ops-financial-master-detail` (~240px nav) for Labor, Parts Matrix, and Tax.
6. Existing single-rate `TaxProfile` schema 1 path preserved for new RO stamps via projected legacy columns.
7. Tests: `ShopTaxProfilesSliceATest`, Tabler/forward-only updates.

## Explicitly deferred (owner decisions / later slices)

- **Slice B:** RO tax-profile selector, service address, suggestion vs confirm, authorization contract if changing tax on existing ROs after approval.
- **Slice C:** Component-level line tax math, itemized documents/PDF/portal, reporting by component.
- **Slice D:** Full staging acceptance matrix on synthetic ROs.
- UI “Add profile / Add component” buttons (catalog + payload already support multiple; editor edits stored profiles).
- Colorado SUTS GIS API: future adapter only. Single-address API exists (SUTS registration + API key); bulk address lookup paused Sep 29 2026 → planned managed return Jan 1 2027. No integration in Slice A.
- Paid tax providers: not authorized.

## Compatibility plan

| Artifact | Slice A behavior |
|---|---|
| Existing `TaxProfile` schema 1 on ROs | Unchanged; still authoritative for calc |
| Shop legacy tax columns | Synced from default profile combined rate + applicability on save |
| Living null-stamp ROs | Still stamped with pre-change rules before settings save |
| Issued/archived documents | Untouched |
| `tax_document_presentation` | Stored only; itemized rendering in Slice C |

## Remaining owner decisions before Slice B

1. Confirm RO tax-profile change after authorization requires supplemental auth when tax cents rise (recommended: yes).
2. Whether billing-class tax exemption is in scope for B or a later issue.
3. Whether to bump RO stamp to schema 2 (embed components) in B or only in C.

## Tests

```text
ShopTaxProfilesSliceATest|PartsMatrixAndTaxForwardOnlyTest|ShopSettingsFinancialTablerTest
→ pass
```

## Screenshots (same Alpha Core `e57f7a7`)

Desktop (same browser width):
- `desktop-labor.png`
- `desktop-parts.png`
- `desktop-tax.png` (tax temporarily enabled with Colorado Springs 2.9+3.12 components for UI review, then restored off)

Mobile:
- `mobile-tax-320.png` / `mobile-tax-375.png` / `mobile-tax-430.png`
- `mobile-labor-430.png` / `mobile-parts-430.png`

Nav width: Labor/Parts/Tax use shared `ops-financial-master-detail` (~240px). Parts no longer uses `col-xxl-2`.

**Do not merge without owner review. No production deployment.**
