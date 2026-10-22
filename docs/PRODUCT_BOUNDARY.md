# ARK product boundary

What this repository is, what a stock Core install does, and what lives in ARK Shop.

This page is the current public product split. It is not a roadmap and it does not publish prices. It is the policy for changes in ARK Core, ARK Shop, and ARK Landlord. Do not keep a second copy.

## Rule for changes

Public Core runs the shop. Private ARK Shop sells the extras. Private Landlord runs the infrastructure. Only the project owner authorizes an exception.

A change belongs in this repository when a shop needs it to operate with no ARK Shop account. Communications, commercial AI, parts catalogs, card capture, subscriptions, and provider accounts do not. If that is unclear, stop and ask the project owner.

## Communications are not part of Core

Core manages the shop. ARK Shop powers its communications.

Core does not contain a functional communications subsystem. That includes one described as optional, self-hosted, or bring-your-own-provider. Core must not send, receive, route, store, or display customer communications through any provider. That covers:

- email sending and delivery to customers
- SMS and MMS, in either direction
- inbound and outbound voice, SIP routing, SIP endpoints, and provisioning
- voicemail and call recording
- Twilio, SMTP relays, and any other communications provider integration
- communications credentials, settings, templates, and automations
- conversation history, inboxes, and call logs
- communications notifications, delivery queues, webhooks, callbacks, and background processing
- communications screens and APIs

ARK Shop can offer ARK-managed providers or the shop's own provider account. Either is part of the private product, in a paid tier or as an add-on.

Core keeps:

- the customer's phone numbers and email addresses as contact fields
- basic customer intake: the website contact or booking request and the resulting lead record, with its source and status
- local staff sign-in and account recovery that work without ARK Shop. The framework's password-reset and staff email-verification mail use the shop's own mail settings. Recovery must also work from the command line, with no mail at all.
- small, provider-neutral extension points: domain events, contracts whose Core implementation does nothing, and registries for navigation, timeline entries, attention sources, and settings sections

ARK Shop owns everything that communicates about a lead or a customer:

- lead conversations, replies, and follow-up
- escalation of uncontacted leads
- verification codes sent by email or text
- notifications to customers

Core may confirm a code that ARK Shop sent through a minimal contract. Core never sends one.

An extension point must not contain a working sender, receiver, router, or store. With no ARK Shop installed, a shop has no communications features, and Core says so without failing. A fresh Core install creates no communications tables and does not depend on tables ARK Shop owns.

There is no self-hosted Twilio or SMTP exception.

Previously published versions of Core included communications code under AGPL-3.0. That code stays available under that license. This policy covers what Core contains from now on.

ARK Shop consumes a pinned Core release. Core does not depend on ARK Shop. Landlord provisions hosts and is not on a shop's request path.

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
- A customer portal for estimates, approvals, inspections, and documents, shared by link
- Reports, including end of day, sales and payments, margin, owner P&L, and production
- Tax, shop fees, deposits, and customer types
- A ledger for payments taken outside ARK (cash, check, or a card charged on a standalone terminal)
- First-run installer
- Dragon, with your own model provider. No ARK-hosted model is included.
- API contracts for separate client apps (`/api/mobile`, `/api/desk`, `/api/tech`, `/api/station`), excluding communications endpoints. The apps are not shipped here.

The customer portal can show an amount owed. Showing that amount is not card processing.

AllData and ProDemand buttons open the vendor site with the shop's own login. Core ships a labor-guide import interface. Licensed labor-guide data is not in this repository.

One Core installation is one shop. Core is not a shared-database multi-tenant product.

Financial totals stay on the server. The repair-order screen shows those totals. It does not calculate them again in the browser.

## ARK Shop

ARK Shop is a private commercial product. It is built from a pinned ARK Core release, plus services ARK operates, commercial integrations, and managed infrastructure.

Hosted revenue helps fund continued development and maintenance of free, open-source ARK Core.

ARK Shop is being built as the fully managed way to run ARK, including infrastructure, deployment, updates, backups, monitoring, communications, payments, and commercial integrations. It is not open for general customer signup or provisioning today.

### Commercial capabilities already implemented

These capabilities already exist in the ARK ecosystem. They belong to ARK Shop, not to a stock Core install. This table is not a signup offer.

| Capability | Where it lives |
| --- | --- |
| Customer email | ARK Shop |
| Customer texting (SMS and MMS) | ARK Shop |
| Voice: inbound, outbound, SIP phones, voicemail, and recordings | ARK Shop |
| Conversation history, inbox, and call log | ARK Shop |
| Communications automations, templates, and notifications | ARK Shop |
| Provider accounts, ARK-managed or the shop's own | ARK Shop |
| Card capture inside ARK | ARK Shop |
| PartsTech shop catalog access | ARK Shop |

### Shop work that stays in Core

| Capability | Where it lives |
| --- | --- |
| Customer phone and email contact fields | Core |
| Recording a payment the shop already took | Core |
| Labor-guide data | You license it. Core ships the import interface, not the data. |
| AllData / ProDemand buttons | Core. They open the vendor site with the shop's own login. |
| Label printing (QZ Tray) | Core, after the shop supplies its own certificate and private key. |
| Example staff such as `admin@ark.test` | Optional development seed data. Not created by first-run setup. |
| Separate client apps (Desk, Tech, Companion, and similar) | Not in this repository. Core exposes the non-communications API contracts only. |

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
| ARK Shop | Private commercial product. Pinned Core, plus communications, ARK-operated services, commercial integrations, and managed infrastructure. |
| ARK Landlord | Private. Host provisioning, deployment, monitoring, and recovery. Not on a shop's request path. |
| Shop website | Customer-facing site and editor. See [platform/ark-core-website-boundary.md](platform/ark-core-website-boundary.md). |
| Desk, Tech, Companion, and similar clients | Separate apps. Core exposes `/api/mobile`, `/api/desk`, `/api/tech`, and `/api/station` without communications endpoints. |
| Licensed labor guides | Data you license separately. Not redistributed. |

## Dragon

Core includes Dragon settings so a shop can point at a model it already runs. The default is `none`. Tests may use `fake`. An ARK-hosted model, provider credential store, and new AI product are not added here. Those belong to ARK Shop.

## Enforcement

Core CI rejects functional communications code: provider SDKs and hosts, webhook routes, TwiML, SIP, communications tables, screens, permissions, and customer mail. `scripts/assert-no-communications.php` is the check, and its denylist is the source of truth.

`scripts/communications-baseline.json` lists the code still being moved to ARK Shop.

- A new violation fails CI.
- A fixed violation must be removed from the baseline, or CI fails.
- A missing baseline also fails CI.
- The baseline may only shrink. When it is empty, CI rejects any communications code.

ARK Shop CI separately proves that each communications route and service in the composed product is the one it expects. Each must come from Core until its move, and from ARK Shop after.

## Related

- Install: [installation/README.md](installation/README.md)
- Payments implementation note: [platform/ark-payments-boundary-v1.md](platform/ark-payments-boundary-v1.md). Historical. The current Core/Hosted product split is this page.
- Website: [platform/ark-core-website-boundary.md](platform/ark-core-website-boundary.md)
