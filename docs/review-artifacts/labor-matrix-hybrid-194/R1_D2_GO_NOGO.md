# GO/NO-GO — Labor Matrix R1 + D2 (#194)

## Decision requested

**Alpha acceptance only.** Production remains untouched until separately authorized.

## Candidate (after Alpha deploy of this commit)

| Layer | SHA |
| --- | --- |
| Core branch | `fix/labor-matrix-hybrid-ux` |
| Core commit | *(record after push)* |
| Stancl staging | `staging/labor-matrix-hybrid-194` |

## What landed

### R1 — Independent Billing Class rounding
- `shop_settings.labor_rounding` JSON matrix (WT × BC)
- Migration backfills from `labor_categories[].rounding_rule` (no behavior change at cutover)
- `LaborAuthority` resolves rounding from RO profile schema ≥3 cell, else shop matrix, else category fallback
- `LaborPricingProfile::CURRENT_SCHEMA = 3` stamps `rounding_rule` on each rate cell
- Matrix Round To saves per row via `PATCH …/labor-matrix/rounding`

### D2 — Explicit hourly rates
- Standard open policies frozen to Hourly/Zero with stored cents (migration)
- `SyncStandardCustomerPayLaborRatesAction` retired to no-op
- Matrix continues explicit custom/zero saves; no Default Labor Rate / Rate Type UI
- Legacy financial form updates Mechanical × Retail as Hourly only (no cascade)

### Adjust Labor
- Editable per-category `allows_modifiers` no longer gates Mechanical/etc.
- RepairPal + Warranty-Other remain commercially locked
- PricingAuthority still required for rate overrides
- Manual corrections / audit fields preserved

### Presentation
- Shared Min Hrs labeled “Shared · all work types”
- Round To labeled “This work type × class”
- Dark-mode-friendly `.labor-matrix-th` header contrast + row hover

## Migrations

| Migration | Impact |
| --- | --- |
| `2026_11_13_040000_labor_rounding_matrix_and_explicit_rates` | Additive JSON column + Standard→Hourly freeze |

No tax, payment, or RO reprice of stored line money. Open ROs keep schema ≤2 behavior until new stamps.

## Rollback

Redeploy prior Stancl SHA. Leaving `labor_rounding` in place is safe (ignored by older code). Do not reverse Standard→Hourly freeze in place.

## Alpha proof checklist

- [ ] Retail Round To ≠ RepairPal Round To for Mechanical; both persist
- [ ] Hourly rate edit on one class does not cascade other work types
- [ ] Open RO billed hours unchanged after settings edit
- [ ] Desktop + mobile screenshots vs mockup
