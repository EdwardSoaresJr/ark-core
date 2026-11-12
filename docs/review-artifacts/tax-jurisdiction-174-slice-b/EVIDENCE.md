# #174 Slice B — Tax jurisdiction (HOLD MERGE)

## Phase 1 — #189 merge (complete)

| Item | Value |
|---|---|
| Merged PR | https://github.com/EdwardSoaresJr/ark-core/pull/189 |
| Merge commit / main | `018ef6aaf6debfd00e502a0decec25c39477d962` |
| Reviewed head | `a4cffdf064b724414de856947318e4eb29ed6b61` |
| CI at merge | Focused + Regression pest SUCCESS; known-failure baseline preserved |

Integrated surfaces retained on main: Labor matrix, Parts matrix nav, Shop Fees (percent/flat/per_concern + applies_to), Tax Profiles catalog, shared financial tabs.

## Phase 2 — this PR

| Item | Value |
|---|---|
| Branch | `feat/tax-jurisdiction-slice-b` |
| Base | `origin/main` @ `018ef6a` |
| Docs | `ARCHITECTURE.md`, `SURFACE_MATRIX.md`, `PENNY_RECONCILIATION.md` |

### Delivered

- TaxProfile **schema 2** freeze (profile identity, components, associations, presentation, sourcing)
- Schema 1 historical readability preserved
- `TaxJurisdictionMatcher` (suggestive only; never auto-assign)
- County associations on catalog + settings UI
- RO tax jurisdiction confirmation card + `SelectTaxProfileOnRepairOrderAction`
- Post-auth tax increase held under approved-dollar ceiling
- Penny reconciliation regression (stored cents = snapshot display)

### Explicitly deferred

- Component-level independent taxable bases / itemized document lines (Slice C)
- Full builder→email→portal→invoice automated cent matrix
- Customer tax exemption independent of Billing Class
- SaaS automated lookup (never Core)

## Tests (local)

`TaxJurisdictionSliceBTest` + Slice A tax + ShopFeeAppliesTo + PricingEngineSafety: **43 passed**

## Alpha / screenshots (staging only)

| Pin | SHA |
|---|---|
| Alpha Core | `4d5903b7e0dbf9787262b0186eead443d53d0e04` (+ follow-up tip after evidence) |
| Alpha SaaS | `eb859c2159a1821d87e51721698d4608c3bb20c1` |

- Tax settings counties field: `slice-b-tax-settings-desktop.png`
- RO tax jurisdiction card: `slice-b-ro-tax-jurisdiction-desktop.png`
- Mobile 375: `slice-b-ro-tax-jurisdiction-mobile-375.png`

Tax temporarily enabled for UI, then restored off. No tenant migration required (JSON stamp only). No production changes.

**PR:** https://github.com/EdwardSoaresJr/ark-core/pull/190 — **HOLD MERGE**
