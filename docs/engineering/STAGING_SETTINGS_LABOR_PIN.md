# Staging integration plan — Settings labor clarity (Core PR #172)

**Status:** Ready for owner-authorized **staging-only** SaaS Core pin.  
**Not authorized:** production merge cutover, production pin, or LugsNPlugs deploy.

## Corrected Core SHA

 on  (PR #172).


## What staging gets

- Standard hourly rate + atomic Customer Pay sync (match previous standard; preserve specialty)
- Labor / Labor Policies clarity copy and Edit affordance
- Settings nav Basic / Optional / Advanced
- Station bind auto-prompt **off** (no suitable explicit enablement setting yet; see dependency below)

## Station enablement dependency

`ShopSettings` only has `workstation_idle_lock_minutes` (retired lock). There is **no** existing “stations enabled” / “require browser binding” flag to reuse. Inventing a second system was declined. Safe default remains: **never auto-prompt**. Binding and Stations & Phones management stay available. Explicit Advanced Settings enablement is a follow-on after owner approval of a single shop setting.

## Staging pin procedure (SaaS / ark-stancl)

1. Owner authorizes staging Core pin to .
2. On the staging host only (`demo.staging.arksms.com` / staging droplet), update the Core pin the same way other staging Core pins are applied (`vendor/ark/core` pin / `deploy/deploy.sh <sha>` per `docs/ARK-STANCL.md`). Do **not** run production `deploy.sh` or Landlord shop-release for LugsNPlugs.
3. Run `bin/build-shop-assets` when the pin change requires it (per stancl README).
4. Smoke on `demo.staging.arksms.com` as demo owner: Settings → Financial Rules → Labor (standard rate + effective rate), Labor Policies (Edit cells), Settings nav groups Basic / Optional / Advanced, first entry without station modal.
5. Capture full-page staging screenshots; commit to `review-artifacts` (never merge that branch).

## Rollback

Repin staging to the previous Core SHA that staging was running before this pin.

## Production

Blocked. No merge-to-main requirement is satisfied by this plan alone. No production pin.
