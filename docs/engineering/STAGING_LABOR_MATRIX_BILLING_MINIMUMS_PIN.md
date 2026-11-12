# Staging integration plan — Labor Matrix + Billing Minimums

**Status:** Final vertical matrix **deployed to staging** for owner review. Production still blocked.  
Prior live staging pin remains the interim grid (`ee2753d` / SaaS `436770ee`) until re-pin.  
**Not authorized:** production merge cutover, production pin, or LugsNPlugs deploy.

## Current Core tip (ready for staging pin)

Confirm before re-pin:

```bash
git fetch origin settings/labor-matrix-billing-minimums
git rev-parse origin/settings/labor-matrix-billing-minimums
```

Record the tip SHA in the table below after push + owner authorization.

| Layer | SHA | Branch / notes |
|---|---|---|
| Core (prior live staging) | `ee2753d4435bdf5793c6a0e313a2b635188b03f6` | interim grid still on staging until re-pin |
| SaaS (prior live staging) | `436770ee358b61a34dc97a4ec02a7381ddd37f65` | `pin/core-ee2753d-labor-matrix` |
| Core (this implementation) | `39828cb4e3b044aa5aef8e79ed284b49342834e3` | `settings/labor-matrix-billing-minimums` tip |
| SaaS (live staging pin) | `8210b75803e3264584a968a349887b1a196b22e1` | `pin/core-39828cb-labor-matrix-final` |
| Review artifacts | `review-artifacts/labor-rate-matrix-final-2026-11-10/` | local desktop/mobile + Rules screenshots |

Do not merge Core `main` merely to create the staging preview.

## What the final pin gets

- Owner-approved vertical work-type nav + inline hourly rates + Rules modal (autosave, no Save / Standard box)
- Mechanical × Retail as the visible shop default
- Add Work Type / Add Billing Class through authority paths
- Per-billing-class minimums with durable `system_line_kind` fee identity
- Pricing profile schema 2 (frozen rates + minimums) for new ROs only
- Programming split from Troubleshooting (`programming` operation class)
- Persistent concern minimum explanation

## Migration safety

- `2026_11_10_180000_labor_matrix_profile_v2_and_programming_split.php` is additive / forward-only
- Existing ROs keep `labor_pricing_profile = null` → legacy (no billing-class minimums; as-of `opened_at` rates)
- No historical RO profile backfill; no LugsNPlugs / production touches

## Staging pin procedure (SaaS / ark-stancl)

1. Owner authorizes staging Core pin to the tip SHA from the command above.
2. On a separately pinned ark-stancl branch (do not merge stancl `main`), set `composer.json` / `composer.lock` `ark/core` reference to that SHA.
3. On the staging host only (`demo.staging.arksms.com` / staging droplet), deploy that stancl branch SHA with `deploy/deploy.sh <sha>`. Do **not** run production `deploy.sh` or Landlord shop-release for LugsNPlugs.
4. Run `bin/build-shop-assets` when the pin change requires it.
5. Smoke on `demo.staging.arksms.com` as demo owner: Settings → Financial Rules → Labor.
6. Capture live staging screenshots into `review-artifacts` if they differ from the committed local set.

## Rollback

Repin staging to SaaS `436770ee` / Core `ee2753d` (prior interim pin).

## Production

Blocked. Owner approval required before any further release.
