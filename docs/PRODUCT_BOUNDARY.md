# ARK product boundary

What this repository is, what a stock Core install does, and what lives elsewhere.

This page matches the public Core code. It is not a roadmap.

## Core

ARK Core is self-hosted shop management software for one repair location.

A stock install (Docker Compose and `/setup`, with no Platform connection) provides:

- Customers, vehicles, repair orders, estimates, and inspections
- Scheduling and shop workflow
- A customer portal for estimates and inspections
- Recording payments taken outside ARK (cash, check, or a card charged on a standalone terminal)
- NHTSA VIN decode
- Shop identity, financial rules, documents, staff, and printing settings
- A first-run installer

One Core installation is one shop. Core is not a shared-database multi-tenant product.

Financial totals stay on the server. The repair-order screen shows those totals. It does not calculate them again in the browser.

## Optional (not required to run the shop)

| Capability | Prerequisite |
| --- | --- |
| Customer email (estimates, invoices, documents) | ARK Platform pairing for ARK Email |
| Customer SMS / texting | ARK Platform pairing for ARK Texting |
| Card capture inside ARK | ARK Platform pairing for ARK Payments |
| Hosted voice / desk phones | ARK Platform Voice |
| Labor-guide data | A licensed CSV you import yourself. The schema ships here. The data does not. |
| AllData / ProDemand buttons | Shop login URLs. They open the vendor site. |
| Label printing (QZ Tray) | A certificate and private key supplied by the shop |
| Example staff such as `admin@ark.test` | Optional development seed data. Not created by first-run setup. |

Without those connections, Core still runs repair orders. Email, texting, and in-app card capture stay unavailable and say so.

## Separate products (not in this repository)

| Product | Relationship to Core |
| --- | --- |
| ARK Platform | Managed services. Separate repository. |
| Hosted ARK | The same Core software, operated for you. Coming soon. Not a separate edition. |
| Shop website | Customer-facing site and editor. Core may keep shop identity and lead records. See [platform/ark-core-website-boundary.md](platform/ark-core-website-boundary.md). |
| Desk, Tech, Companion, and similar clients | Separate apps. Core exposes API contracts (`/api/mobile`, `/api/desk`, `/api/tech`, `/api/station`). The apps are not shipped here. |
| Licensed labor guides | Data you license separately. Not redistributed. |

## Dragon

Dragon ships with Core. Stock Core does not include a hosted model.

Configure your own model provider under Settings, or set `DRAGON_PROVIDER` in the environment. The default is `none`. Tests may use `fake`. Private shop knowledge and training imports are not included.

## Not a supported integration

Leftover Twilio or Square names in code, schema, or tests are not a self-hosted Twilio or Square integration. Outbound email and texting go through ARK Platform. Card charging inside ARK goes through ARK Platform. Recording a payment the shop already took is a Core ledger entry.

## Related

- Install: [installation/README.md](installation/README.md)
- Payments: [platform/ark-payments-boundary-v1.md](platform/ark-payments-boundary-v1.md)
- Website: [platform/ark-core-website-boundary.md](platform/ark-core-website-boundary.md)
