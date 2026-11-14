# RO Workspace Owner Review (Alpha)

**Date:** 2026-11-14  
**Surface:** Integrated Core shop on Alpha — `https://alpha.staging.arksms.com/app/repair-orders/41`  
**Alpha baseline SHAs (pre-fix):**

| Layer | SHA |
| --- | --- |
| SaaS (ark-stancl) | `54532a9bdbc3c45bc391af5fd95221dd6968c5db` |
| Core (ark/core pin) | `cb669eaaf9f858ba8d589b7ceb852abfce686965` |

Labor Matrix / Billing Classes UI and #85 inbox acceptance are out of scope for this pass.

## Confirmed defect (fixed in PR)

### Sticky action dock overlays estimate content

**Repro**

1. Open RO #41 (or any multi-concern estimate) on Alpha as advisor.
2. Desktop 1440 / tablet 768 / phone 320 — scroll until concern add-row or Estimate Total sits behind the bottom dock.
3. Observe `.page-body` `padding-bottom: 0` while `[data-ro-orientation-header]` stays sticky at the bottom.

**Cause**

Dock reserve CSS only padded legacy `.ops-estimate-workspace`. Tabler RO pages use `.page-body`, so the sticky dock covered the last lines of labor/parts controls and the stacked Estimate Total card.

**Fix**

- Reserve `--ops-ro-dock-reserve` on Tabler `.page-body` + `scroll-padding-bottom`.
- Measure live dock height into that variable from the orientation-header init and the rail fit script.
- Tests: `TablerStickyHeaderTest`, `RepairOrderRailScrollTest`.

**Screenshots:** `screenshots/ro41-*-dock-*-before.png` vs `*-after-preview.png` (after = CSS injected in-browser before Alpha deploy).

## Also observed (no code change this PR)

| Item | Notes | Owner decision |
| --- | --- | --- |
| Duplicate Print | Top bar + dock both expose Print | Keep both for reach, or dock-only on RO? |
| Duplicate PartsTech | Concern-level link + dock PartsTech/Cart | Keep concern shortcut, or dock-only? |
| Dual top banners | “customers need you” + “Finish setting up your shop” eat mobile height | Dismiss/priority rules after onboarding is fully done? |
| Leftover `.modal.d-block` nodes | Many RO modals stay in DOM; clutter for a11y/focus | Cleanup pass later if focus traps show up |
| Dense Customer / Vehicle / Visit stack @ 320 | Usable, but tall before Estimate | Collapse-by-default later? |
| Labor Matrix UI | Staged; presentation still needs owner polish | Separate review — do not touch pricing math |

## Boundaries honored

- No payment / pricing architecture changes.
- No Labor Matrix calculation changes.
- No production deploy.
- Workflows and authorization unchanged.

## Fix PR

- Core: https://github.com/EdwardSoaresJr/ark-core/pull/200 (`186edd70232feb3d5218c252cb23fd730ba2008f`)

## Next

1. Merge Core #200; SaaS pin + Alpha deploy only (no production).
2. Owner re-check RO #41 at 1440 / 768 / 320.
3. Then Labor Matrix presentation review (rates/rules/classes UI only).
