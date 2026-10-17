# ARK product boundary

What this repository is, what a stock Core install does, and what lives in ARK Shop.

This page is the current public product split. It is not a roadmap and it does not publish prices. It is the policy for changes in ARK Core, ARK Shop, and ARK Landlord. Do not keep a second copy.

## Rule for changes

Public Core runs the shop. Private ARK Shop sells the extras. Private Landlord runs the infrastructure. Only the project owner authorizes an exception.

A change belongs in this repository when a shop needs it to operate with no ARK Shop account. Optional texting, voice, commercial AI, parts catalogs, card capture, subscriptions, and provider accounts do not. If that is unclear, stop and ask the project owner.

Do not add provider credential screens, setup wizards, turnkey adapters, or a plugin system to make those services easier to wire up. A developer may write an integration in their own Laravel code. The maintained version is ARK Shop.

ARK Shop consumes a pinned Core release. Core does not depend on ARK Shop. Landlord provisions hosts and is not on a shop's request path.

Shop screens that already offer a text call `OutboundSmsTransport`. A stock install binds that port to a sender that is not configured, so the shop keeps running and no message is sent. The port stays in Core because those screens already depend on it, and because ARK Shop replaces the binding when texting is installed. It is not a supported integration contract. Do not add credentials, webhooks, provider selection, or media retrieval to it. It cannot move into ARK Shop: Core must not import ARK Shop, and deleting the port would stop those screens from compiling.

## Core

ARK Core is the free, open-source shop-management product for one repair location. This repository is that product. It is not a trial or a teaser for ARK Shop.

**You do not need ARK Shop to use ARK Core.**

A stock install (Docker Compose and `/setup`, with no ARK Shop account) provides:

- Customer and vehicle records, with search, CSV import, and NHTSA VIN decode
- Repair orders, estimates, customer authorization, and inspections (including templates and photos)
- Scheduling, including appointment bays
- Job board, shop display, dashboard, day review, and technician production
- Time clock, staff roles, and workstations
- Parts and labor lines, parts pricing matrices, and labor rates
- Engine-oil service recorded on the repair order
- Documents, estimate and invoice PDFs, and printing settings
- A customer portal for estimates, approvals, inspections, and documents
- Reports, including end of day, sales and payments, margin, owner P&L, and production
- Tax, shop fees, deposits, and customer types
- A ledger for payments taken outside ARK (cash, check, or a card charged on a standalone terminal)
- First-run installer
- Dragon, with your own model provider. No ARK-hosted model is included.
- API contracts for separate client apps (`/api/mobile`, `/api/desk`, `/api/tech`, `/api/station`). The apps are not shipped here.

The customer portal can show an amount owed. Showing that amount is not card processing.

AllData and ProDemand buttons open the vendor site with the shop's own login. Core ships a labor-guide import interface. Licensed labor-guide data is not in this repository.

One Core installation is one shop. Core is not a shared-database multi-tenant product.

Financial totals stay on the server. The repair-order screen shows those totals. It does not calculate them again in the browser.

## A screen is not the service

Core includes screens for communications, conversations, calls, parts catalogs, and payments. Those screens are shop workflow. They do not mean the matching ARK-operated service is included.

Sending customer email or texts, operating hosted phones, capturing a card inside ARK, and opening the shop parts catalog require ARK Shop. When they are not connected, Core says so and keeps the shop running.

Settings can still show Communications, Parts catalogs, or Square Payments. That is not a self-hosted Twilio, Square, or PartsTech product.

## ARK Shop

ARK Shop is a private commercial product. It is built from a pinned ARK Core release, plus services ARK operates, commercial integrations, and managed infrastructure.

Hosted revenue helps fund continued development and maintenance of free, open-source ARK Core.

ARK Shop is being built as the fully managed way to run ARK, including infrastructure, deployment, updates, backups, monitoring, communications, payments, and commercial integrations. It is not open for general customer signup or provisioning today.

### Commercial capabilities already implemented

These ARK-operated services already exist in the ARK ecosystem. They belong to ARK Shop, not to a stock Core install. This table is not a signup offer.

| Capability | Where it lives |
| --- | --- |
| Customer email | ARK Shop |
| Customer texting | ARK Shop |
| Hosted phone service | ARK Shop |
| Card capture inside ARK | ARK Shop |
| PartsTech shop catalog access | ARK Shop |

### Shop work that stays in Core

| Capability | Where it lives |
| --- | --- |
| Recording a payment the shop already took | Core |
| Labor-guide data | You license it. Core ships the import interface, not the data. |
| AllData / ProDemand buttons | Core. They open the vendor site with the shop's own login. |
| Label printing (QZ Tray) | Core, after the shop supplies its own certificate and private key. |
| Example staff such as `admin@ark.test` | Optional development seed data. Not created by first-run setup. |
| Separate client apps (Desk, Tech, Companion, and similar) | Not in this repository. Core exposes the API contracts only. |

### Managed infrastructure, in development

The fully managed ARK Shop product is still being built. These are not available today:

- Managed infrastructure, deployment, updates, backups, and operational monitoring
- ARK Backup, ARK Storage, and ARK Data
- Moving a shop between self-hosted Core and ARK Shop
- ARK Connect
- An ARK-hosted Dragon model

## Why some Hosted work is not in this repository

ARK Core is intentionally open source. ARK Shop adds paid services and integrations around it.

Some commercial integrations use partner APIs, documentation, or implementation details that we are not permitted to redistribute publicly. Those integrations are available only as part of ARK Shop.

PartsTech is one example. Shop catalog access is part of ARK Shop. This repository does not publish the partner API materials for that integration.

## Separate from this repository

| Product | Relationship to Core |
| --- | --- |
| ARK Shop | Private commercial product. Pinned Core, plus ARK-operated services, commercial integrations, and managed infrastructure. |
| ARK Landlord | Private. Host provisioning, deployment, monitoring, and recovery. Not on a shop's request path. |
| Shop website | Customer-facing site and editor. Core may keep shop identity and lead records. See [platform/ark-core-website-boundary.md](platform/ark-core-website-boundary.md). |
| Desk, Tech, Companion, and similar clients | Separate apps. Core exposes `/api/mobile`, `/api/desk`, `/api/tech`, and `/api/station`. |
| Licensed labor guides | Data you license separately. Not redistributed. |

## Dragon

Core includes Dragon settings so a shop can point at a model it already runs. The default is `none`. Tests may use `fake`. An ARK-hosted model, provider credential store, and new AI product are not added here. Those belong to ARK Shop.

## Not a supported self-host integration

Twilio or Square names in code, schema, or tests are not a self-hosted Twilio or Square product. Card charging inside ARK, customer email, customer texting, hosted phones, and PartsTech shop catalog access belong to ARK Shop. Recording a payment the shop already took is a Core ledger entry.

## Related

- Install: [installation/README.md](installation/README.md)
- Payments implementation note: [platform/ark-payments-boundary-v1.md](platform/ark-payments-boundary-v1.md). Historical. The current Core/Hosted product split is this page.
- Website: [platform/ark-core-website-boundary.md](platform/ark-core-website-boundary.md)
