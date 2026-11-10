# G0 Campaign 1 — Financial integrity & RO lifecycle

Supervised staging campaign. **Not a production release gate pass.**

## Environment

| Field | Value |
| --- | --- |
| Campaign start Core pin | `53e6d88c2706b0f9cc554b9541887c93743dc1c8` |
| Campaign SaaS pin | `8dc38a139999d4ad116c3669d2c2bf76b8a61b3e` |
| Test-repair commit | see branch tip (stale financial tests retargeted) |
| Host | `157.230.172.143` (`APP_ENV=staging`) |
| Tenant | `alpha.staging.arksms.com` |
| Capture date | 2026-11-10 |
| Production | Untouched / unauthorized |

## Evidence index

| File | Demonstrates |
| --- | --- |
| [screenshots/g0-b01-ro20-opened.png](./screenshots/g0-b01-ro20-opened.png) | Synthetic customer/vehicle Check In → **RO #20** opened (Retail, diagnostic visit reason) |
| [screenshots/g0-d01-mobile-work-types.png](./screenshots/g0-d01-mobile-work-types.png) | Mobile Labor: Default Labor Rate + work-type stack |
| [screenshots/g0-d02-mobile-billing-rows.png](./screenshots/g0-d02-mobile-billing-rows.png) | Mobile Labor: Troubleshooting rows, rate modes, Rules + Class Settings |
| [screenshots/g0-d03-mobile-rules-modal.png](./screenshots/g0-d03-mobile-rules-modal.png) | Mobile Retail Rules modal (compact; Cancel/Save reachable) |
| [screenshots/g0-d04-mobile-class-settings.png](./screenshots/g0-d04-mobile-class-settings.png) | Mobile Retail Class Settings groups |
| [screenshots/g0-d05-mobile-manage-billing-classes.png](./screenshots/g0-d05-mobile-manage-billing-classes.png) | Mobile Class Settings after Manage entry (Retail editor) |
| [screenshots/g0-d06-mobile-validation-error.png](./screenshots/g0-d06-mobile-validation-error.png) | Mobile validation: blank billing class name → “The name field is required.” |

Scenario ledger: [SCENARIO_LEDGER.md](./SCENARIO_LEDGER.md)

## Privacy

Synthetic records only (`GZero CampaignOne`, `5550199`, `2019 Toyota Corolla`). No payment tokens or production data.

## Campaign outcome (summary)

**G0 status: BLOCKED** — automated financial suite green after test repair; hostile RO lifecycle incomplete; Square card path unavailable on alpha; Shop Fees open-estimate document sync remains an open safety finding.
