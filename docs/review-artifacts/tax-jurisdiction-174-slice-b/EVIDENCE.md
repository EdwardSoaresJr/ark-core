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
| Docs | `ARCHITECTURE.md`, `SURFACE_MATRIX.md`, `PENNY_RECONCILIATION.md`, `PRODUCTION_PRESERVATION_GO_NOGO.md` |
| Migrations | **None** in #190 |

### Delivered

- TaxProfile **schema 2** freeze (profile identity, components, associations, presentation, sourcing)
- Schema 1 historical readability preserved
- `TaxJurisdictionMatcher` (suggestive only; never auto-assign)
- County associations on catalog + settings UI
- RO tax jurisdiction confirmation card + `SelectTaxProfileOnRepairOrderAction`
- **Affirmative** `confirm_jurisdiction` (`accepted` + checkbox; missing field rejected)
- Post-auth tax increase held under approved-dollar ceiling; supplemental auth raises ceiling; invoice cannot collect unapproved
- Estimate/invoice snapshots freeze tax labels from RO stamp; invoice embeds `tax_profile`
- Estimate email body prefers document snapshot total cents
- Penny + pricing-release E2E cent reconciliation regressions

### Explicitly deferred

- Component-level independent taxable bases / itemized document lines (Slice C) — safe unused
- Portal invoice-pay cent depth; full async mail isolation suite
- Customer tax exemption independent of Billing Class
- SaaS automated lookup (never Core)

## Tests (local)

Focused financial subset: **98 passed**  
(`TaxJurisdictionSliceB` + Slice A tax + ShopFeeAppliesTo + ShopFeePerConcernAuthorization + PricingEngineSafety + AuthorizationIntegrity + ApprovedWorkChangesAfterAuthorization)

## Alpha / screenshots (staging only)

| Pin | SHA |
|---|---|
| Alpha Core (earlier Slice B UI) | `4d5903b7…` — re-pin to tip after this release-blocker commit if owner wants refreshed evidence |
| Alpha SaaS | `eb859c2159a1821d87e51721698d4608c3bb20c1` |

Prior screenshots remain valid for card/settings chrome. No tenant migration required (JSON stamp only). No production changes.

## Production

See `PRODUCTION_PRESERVATION_GO_NOGO.md` — **NO-GO** for production until #190 merge + fresh prod pin audit + clone rehearsal + explicit owner deploy authorization.

**PR:** https://github.com/EdwardSoaresJr/ark-core/pull/190 — **HOLD MERGE**
