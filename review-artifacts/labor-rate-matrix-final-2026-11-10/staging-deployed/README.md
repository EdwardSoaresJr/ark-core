# Labor Rate Matrix — staging deployment evidence

Captured from live staging after Core pin deploy. Not mock-ups.

## Deployment

| Item | Value |
|---|---|
| Staging shop (UI matrix) | https://demo.staging.arksms.com |
| Staging shop (writable pricing checks) | https://alpha.staging.arksms.com |
| Deployed Core commit | `39828cb4e3b044aa5aef8e79ed284b49342834e3` |
| SaaS / stancl pin commit | `8210b75803e3264584a968a349887b1a196b22e1` |
| Core branch | `settings/labor-matrix-billing-minimums` |
| SaaS pin branch | `pin/core-39828cb-labor-matrix-final` |
| Staging host | `157.230.172.143` (`demo.staging.arksms.com`) |
| Production | **not touched** |

## Migrations

`deploy/deploy.sh` on staging reported **Nothing to migrate** for central and all opened tenants (migration `2026_11_10_180000_*` already applied on the prior pin). Tenant databases preserved.

## Tests

Focused Pest filter `LaborMatrix|BillingMinimum|LaborPricing|StandardLaborRate|ShopSettingsFinancialTabler`: **16 passed** (191 assertions).

Outbound safety preflight on staging: **APP_ENV=staging**, **Outbound limits on (not production) = limited**.

## Screenshots

| File | What it shows |
|---|---|
| `01-desktop-labor-matrix.png` | Vertical Work Type list, inline hourly rates, Mechanical × Retail default, Ready status |
| `02-desktop-other-work-type.png` | Programming work type selected with its rates |
| `03-desktop-rules-modal.png` | Retail Rules modal: per-RO minimum 1.00 hr, Mechanical × Retail shortfall |
| `04-add-work-type-dialog.png` | Add Work Type dialog |
| `05-add-billing-class-dialog.png` | Add Billing Class dialog |
| `06-autosave-saved.png` | Server-confirmed **Saved** indicator after rate PATCH (alpha writable tenant) |
| `07-mobile-labor-matrix.png` | Mobile Labor Matrix layout |
| `08-ro-minimum-adjustment.png` | RO #16: Retail 0.40 + Warranty 2.00; advisor banner + Billing minimum · Retail 0.60 / $99 |
| `09-ro-minimum-recalculated.png` | RO #15: Retail 0.40 + 0.60; minimum fee removed; Fees $0; Labor $165 |

## Pricing verification (alpha synthetic ROs)

| Case | Result |
|---|---|
| Retail 0.40 hr with 1.00-hour RO minimum | Shortfall fee 0.60 hr / $99 (`billing_minimum`) |
| Add second Retail concern 0.60 hr | Minimum fee removed |
| Retail 0.40 + Warranty 2.00 | Retail still receives 0.60 hr minimum; warranty not pooled |
| Existing RO with `labor_pricing_profile = null` | No minimum fee after settings change (forward-only) |
| Newly stamped RO after settings | Receives current profile minimums |

## Notes / deviations

- `demo.staging.arksms.com` is a locked demonstration shop: settings PATCH returns 403. Matrix UI evidence is from demo; autosave Saved + RO pricing evidence from temporarily reactivated `alpha` staging tenant.
- Modal visibility required `display: block !important` because Tabler `.modal { display: none }` is not overridden by Alpine `x-show` alone.
- Rollback staging: `deploy/deploy.sh refs/pins/core-63296b2-labor-matrix-final` (or prior `436770ee` / Core `ee2753d`).
