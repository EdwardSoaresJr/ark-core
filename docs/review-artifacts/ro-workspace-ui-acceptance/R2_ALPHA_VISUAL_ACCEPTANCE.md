# RO workspace UI corrections R2 — Alpha visual acceptance

**Status:** STOP FOR OWNER VISUAL ACCEPTANCE  
**Production:** ON HOLD (do not merge/deploy until owner sign-off)

| Layer | SHA |
| --- | --- |
| Core (functional / Alpha pin) | `c66c8ee9a547927d3645742247180488b8136703` |
| SaaS (Alpha pin) | `2674b980410593994c61fde9aef36ca9d1d157fb` |
| Core PR tip (docs only) | `6cb1d6d5fa1d56948e1b64e29a71047e0f029f39` |
| Demo | https://alpha.staging.arksms.com/app/repair-orders/40 |
| Core PR | https://github.com/EdwardSoaresJr/ark-core/pull/193 |
| SaaS PR | https://github.com/EdwardSoaresJr/ark-stancl/pull/223 |

## False-positive root cause (footer Print)

Prior Alpine footer used `@click="open = !open"` on the button and `@click.outside="open = false"` on the menu. The toggle click originated outside the menu, so Alpine immediately closed it. A11y snapshots still listed `menuitem` nodes because they remained in the DOM (`display:none`), producing a false PASS.

**Fix:** Bootstrap `dropup` + `data-bs-toggle="dropdown"` + `data-bs-display="static"` (same projection items as header).

## Real browser proof (deployed Alpha)

| Evidence | Result |
| --- | --- |
| `r2-desktop-footer-print-real-click.png` | Footer Print **expanded** after pointer click; menu opens upward with Key Tag / Oil / Check In / Tech / Estimate PDF |
| CDP after click | `aria-expanded=true`, `.show`, `opensUpward=true`, `display=block` |
| `r2-mobile-375-footer-print-real-click.png` | Same menu open after real click at 375px |
| `r2-desktop-tax-modal-layered.png` | Modal above backdrop; tooltip “Click to view or change tax jurisdiction.”; dotted underline on jurisdiction |
| CDP stacking | backdrop z=1050, dialog z=1055, `elementFromPoint` hits `LABEL.form-label` inside `.modal-content` (not backdrop); teleported to `body` |

## Other corrections on Alpha

1. Sales Tax row uses same `py-1` row geometry; dotted underline on jurisdiction only.
2. Concern headers: reduced padding via `.ops-concern-header-accent`.
3. PartsTech Quote large card removed; host + **Linked quote** action + Cart/Parts entry points remain.
4. Estimate toolbar Import/Preview/Customer View removed; Import via footer; Customer View/Preview via context/portal.

## Migrations / financial engine

**None.** Presentation/UI only. Prior pricing RC financial preservation still applies.

## Checks

- Focused Core Pest for this change: 25 passed; 1 pre-existing phone shop-fee assertion also fails on `main` without this branch.
- Core PR #193 CI: pending owner review after visual accept.
- **Do not merge Core/SaaS or deploy production until owner accepts Alpha UI.**
