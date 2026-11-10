# Staging integration plan — Labor Matrix + Billing Minimums

**Status:** Staging pin live for owner visual review.  
**Not authorized:** production merge cutover, production pin, or LugsNPlugs deploy.

## Pinned SHAs (staging only)

| Layer | SHA | Branch |
|---|---|---|
| Core | `ee2753d4435bdf5793c6a0e313a2b635188b03f6` | `settings/labor-matrix-billing-minimums` |
| SaaS | `436770ee358b61a34dc97a4ec02a7381ddd37f65` | `pin/core-ee2753d-labor-matrix` |
| Screenshots | `8b767c6c979799028125401af0aec5cd054a1e25` | `review-artifacts` |

Confirm tip before any re-pin:

```bash
git fetch origin settings/labor-matrix-billing-minimums
git rev-parse origin/settings/labor-matrix-billing-minimums
```

Do not merge Core `main` merely to create the staging preview.

## What staging gets

- Compact work-type × billing-class labor rate matrix (Mechanical, Advanced Mechanical, Troubleshooting, Programming × Retail, Warranty, Fleet, RepairPal)
- Per-billing-class minimums: none / per labor line / per repair order + hours
- Shortfall priced at configurable rate defaulting to Retail Mechanical (`standard_mechanical`)
- Forward-only `repair_orders.labor_pricing_profile` stamp (null = legacy; never backfilled)
- Billing minimum Fee lines that preserve original labor hours and tech production hours
- Non-blocking advisor notice when an applied RO minimum adjustment changes

## Migration safety

- Existing ROs keep `labor_pricing_profile = null` after migration → no billing-class minimums
- New lines on null-profile ROs stay legacy even if shop later activates minimums
- New ROs created after profile version ≥ 1 receive an immutable snapshot of minimums + shortfall cents

## Staging pin procedure (SaaS / ark-stancl)

1. Owner authorizes staging Core pin to the tip SHA from the command above.
2. On a separately pinned ark-stancl branch (do not merge stancl `main`), set `composer.json` / `composer.lock` `ark/core` reference to that SHA.
3. On the staging host only (`demo.staging.arksms.com` / staging droplet), deploy that stancl branch SHA with `deploy/deploy.sh <sha>`. Do **not** run production `deploy.sh` or Landlord shop-release for LugsNPlugs.
4. Run `bin/build-shop-assets` when the pin change requires it.
5. Smoke on `demo.staging.arksms.com` as demo owner: Settings → Financial Rules → Labor.
6. Capture staging screenshots + pricing proofs; commit to `review-artifacts` (never merge that branch).

## Rollback

Repin staging to the previous Core SHA / stancl deploy SHA that staging was running before this pin.

## Production

Blocked. Owner approval required before any further release.
