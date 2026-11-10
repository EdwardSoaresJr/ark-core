# Staging integration plan — Consolidated Labor Settings (Core PR #172 continuation)

**Status:** Ready for owner-authorized **staging-only** SaaS Core pin.  
**Not authorized:** production merge cutover, production pin, or LugsNPlugs deploy.

## Exact staging SHAs (2026-11-10)

- **Core:** `8f73efefae46b36ad563447c0c68a37351518192` (`settings/consolidated-labor-settings`)
- **SaaS / ark-stancl:** `4b74b6e3e949039ca9ee0d896d19f5606cd700b6` (`pin/core-8f73efe-labor-settings`)
- **Screenshots:** `aba35bb6dc7d29bcc6af772309988645c11f2ac1` on `review-artifacts`
- **Review URL:** https://demo.staging.arksms.com/app/settings/shop?section=financial&financial-tab=labor

## Corrected Core SHA

Confirm tip before pinning:

```bash
git fetch origin settings/consolidated-labor-settings
git rev-parse origin/settings/consolidated-labor-settings
```

Use the tip SHA returned by the command above. Do not merge Core `main` merely to create the staging preview.

## What staging gets

- One Labor Settings area: Standard hourly rate, Labor categories, Advanced billing rates
- Explicit inheritance via `LaborRateType::Standard` (Use Standard / Custom / Intentional Zero)
- Migration marks Customer Pay policies that already matched the shop default as `standard`
- Specialty operation-class rates preserved; historical line snapshots unchanged
- Category Archive / Restore (no hard delete; Add remains unavailable without Operations catalog)
- Station bind auto-prompt remains off

## Pricing behavior (honest)

- **Authoritative for new lines:** `LaborPolicyResolver` → policy `rate_type` + (for Standard) `ShopSettings::defaultLaborRateCents()`
- **Labor Categories** still store display/default rates, min hours, rounding, modifiers — they are not interchangeable with Operation Classes
- Existing shops keep effective prices until the owner intentionally changes a mode or rate
- Specialty matrix cells save separately from the main Labor Settings form (same Settings page)

## Staging pin procedure (SaaS / ark-stancl)

1. Owner authorizes staging Core pin to the tip SHA from the command above.
2. On a **separately pinned** ark-stancl branch (do not merge stancl `main`), set `composer.json` / `composer.lock` `ark/core` reference to that SHA.
3. On the staging host only (`demo.staging.arksms.com` / staging droplet), deploy that stancl branch SHA with `deploy/deploy.sh <sha>`. Do **not** run production `deploy.sh` or Landlord shop-release for LugsNPlugs.
4. Run `bin/build-shop-assets` when the pin change requires it (per stancl README).
5. Smoke on `demo.staging.arksms.com` as demo owner: Settings → Financial Rules → Labor.
6. Capture full-page staging screenshots (desktop + mobile); commit to `review-artifacts` (never merge that branch).

## Rollback

Repin staging to the previous Core SHA / stancl deploy SHA that staging was running before this pin.

## Production

Blocked. No merge-to-main requirement is satisfied by this plan alone. No production pin.
