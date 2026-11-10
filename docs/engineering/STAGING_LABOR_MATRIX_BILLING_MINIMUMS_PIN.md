# Staging integration plan — Labor Matrix + Billing Minimums

**Status:** Ready for owner-authorized **staging-only** SaaS Core pin.  
**Not authorized:** production merge cutover, production pin, or LugsNPlugs deploy.

## Corrected Core SHA

Confirm tip before pinning:

```bash
git fetch origin settings/labor-matrix-billing-minimums
git rev-parse origin/settings/labor-matrix-billing-minimums
```

Use the tip SHA returned by that command. Do not merge Core `main` merely to create the staging preview.

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
