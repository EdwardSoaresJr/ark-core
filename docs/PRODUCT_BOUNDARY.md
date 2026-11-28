# ARK product boundary

What this repository is, what a stock Core install does, and what lives in ARK Shop.

This page is the current public product split. It is not a roadmap and it does not publish prices. It is the policy for changes in ARK Core, ARK Shop, and ARK Landlord. Do not keep a second copy.

## Rule for changes

Public Core runs the shop. Private ARK Shop sells the extras. Private Landlord runs the infrastructure. Only the project owner authorizes an exception.

A change belongs in this repository when a shop needs it to operate with no ARK Shop account. Managed commercial communications delivery, commercial AI (Dragon), licensed labor-data integrations, card capture, parts-catalog provider accounts, subscriptions, and SaaS provider configuration do not. Native customer email that runs entirely on installation-controlled SMTP or sendmail does. If that is unclear, stop and ask the project owner.

## What standalone Core does not provide

These belong to proprietary ARK SaaS (ARK Shop). Standalone Core must not advertise them, require them, or expose their provider configuration:

- Dragon and other commercial AI integrations
- Licensed labor-data integrations (catalog credentials and partner APIs)
- Managed / commercial customer email delivery (ARK Email and proprietary provider SDKs)
- SMS and MMS
- Hosted telephony (inbound/outbound voice, SIP, voicemail, recordings, phone provisioning)
- Integrated payment processing (card capture inside ARK, terminals that settle through ARK)
- SaaS provider configuration screens and credentials

## Communications boundary

Core manages the shop. ARK Shop powers managed commercial communications. Standalone Core may deliver **customer email** through installation-controlled SMTP or sendmail only.

### Standalone customer email (owner-approved, 2026-11-27)

Self-hosted Core installations may send customer transactional email using the shop's own mail infrastructure:

- Standard Laravel `MAIL_*` environment configuration, and/or Tabler administration of SMTP/sendmail settings stored in the installation database
- Estimates, invoices, inspections, statements, and related transactional mail that Core already models
- Honest delivery statuses (queued, transport-accepted, failed). SMTP acceptance is not confirmed recipient delivery
- Log and array transports are never successful customer delivery

Hosted SaaS must never expose standalone SMTP configuration and must never fall back to installation SMTP when managed delivery is unavailable.

SMS, MMS, voice, SIP, voicemail, recordings, proprietary provider SDKs, and commercial credential stores remain outside standalone Core.

ARK Shop can offer ARK-managed providers or the shop's own provider account for managed delivery. Either is part of the private product, in a paid tier or as an add-on.

Core keeps:

- the customer's phone numbers and email addresses as contact fields
- basic customer intake: the website contact or booking request and the resulting lead record, with its source and status
- local staff sign-in and account recovery that work without ARK Shop. The framework's password-reset and staff email-verification mail use the process `MAIL_*` settings for **staff** accounts (self-hosted: that install's mailer; hosted SaaS: the shared node platform SMTP — not a per-shop credential and not ARK Shop customer mail). Recovery must also work from the command line, with no mail at all.
- standalone customer email via installation SMTP/sendmail as described above
- small, provider-neutral extension points: domain events, contracts whose Core implementation does nothing, and registries for navigation, timeline entries, attention sources, and settings sections
- historical communications records and the shared Communications workspace shell described below

ARK Shop owns managed commercial communications about a lead or a customer:

- managed customer email delivery (ARK Email / proprietary providers)
- lead conversations, replies, and follow-up that require SMS or managed channels
- escalation of uncontacted leads over managed channels
- verification codes sent by text (and managed email where Shop owns delivery)
- SMS/MMS and hosted voice delivery

Core may confirm a code that ARK Shop sent through a minimal contract. Core never sends SMS.

An extension point must not contain a working SMS/voice sender, proprietary provider SDK, or commercial credential store. With no ARK Shop and no installation mail configured, Core says so without failing. A fresh Core install does not depend on tables ARK Shop owns for managed delivery.

There is no self-hosted Twilio exception. The shared Communications workspace exception below still applies.

### Owner-approved exception: the shared Communications workspace (2026-11-03)

The project owner approved one narrowly scoped exception. Core keeps one provider-neutral, three-lane Communications workspace (`/app/communications`), and ARK Core and ARK Shop both use it. There is no second workspace or inbox in ARK Shop.

- **The workspace in Core:**
  - the conversation list (needs attention, waiting, resolved, calls and voicemail, history; everyone, mine, unassigned; search);
  - the conversation with its customer and repair-order header, message timeline, repair-order activity, and composer shell;
  - the shop context lane (customer, vehicles, repair orders, assignment, follow-up, internal notes, recent visits);
  - the workflows on Core's own records: assignment, attention, waiting, follow-up, resolve and reopen, read state, internal notes, and conversation history.
- **Core does not deliver customer messages from this workspace.** With no ARK Shop, the workspace still shows history, notes, assignments, and context, and says plainly what cannot be sent.
- **What ARK Shop adds to the same workspace,** through Core's extension points (`App\Ark\Operations\Communications\CommunicationsWorkspaceSignals` and the workspace's actions and slots):
  - managed customer email delivery;
  - hosted SMS and MMS;
  - voice, voicemail, and recordings;
  - delivery tracking and provider errors;
  - consent and quiet hours;
  - real-time provider updates;
  - paid usage and integrations.
- **What stays out of Core:** provider-specific code, provider credentials, and commercial controls (entitlements, metering, pricing). Core's implementation of each extension point uses only Core's own records.
- **Enforcement:** `scripts/assert-no-communications.php` lists every file added under this exception in `WORKSPACE_EXCEPTION`, each with its reason. Any other new communications code still fails, and the legacy baseline can still only shrink. Adding a file to the exception needs the owner's approval.


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
- Native customer email through installation-controlled SMTP or sendmail (configure and test in Settings; no ARK account required)
- Reports, including end of day, sales and payments, margin, owner P&L, and production
- Tax, shop fees, deposits, and customer types
- A ledger for payments taken outside ARK (cash, check, or a card charged on a standalone terminal)
- First-run installer
- API contracts for separate client apps (`/api/mobile`, `/api/desk`, `/api/tech`, `/api/station`), excluding communications endpoints. The apps are not shipped here.

The customer portal can show an amount owed. Showing that amount is not card processing.

AllData and ProDemand buttons open the vendor site with the shop's own login. That is not a licensed labor-data integration. Core may ship a labor-guide **import interface**. Licensed labor-guide data and commercial catalog provider accounts are not in this repository and are not standalone Core features.

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
| Dragon and commercial AI | ARK Shop |
| Managed customer email delivery (ARK Email / proprietary providers) | ARK Shop |
| Customer texting (SMS and MMS) | ARK Shop |
| Voice: inbound, outbound, SIP phones, voicemail, and recordings | ARK Shop |
| Conversation delivery, provider inboxes, and call log services | ARK Shop |
| Communications automations, templates, and notifications | ARK Shop |
| Provider accounts, ARK-managed or the shop's own | ARK Shop |
| Card capture inside ARK | ARK Shop |
| PartsTech shop catalog access and other licensed catalog integrations | ARK Shop |
| SaaS provider configuration | ARK Shop |

### Shop work that stays in Core

| Capability | Where it lives |
| --- | --- |
| Customer phone and email contact fields | Core |
| Native customer email via installation SMTP/sendmail | Core (self-hosted only; never a hosted SaaS fallback) |
| Recording a payment the shop already took | Core |
| Manual payment-method labels for the ledger | Core |
| Provider-neutral contracts, events, and extension registries consumed by SaaS | Core |
| Labor-guide import interface | Core ships the interface. Licensed data is separate. |
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
| Dragon | ARK Shop. Not a standalone Core feature. |

## Enforcement

Core CI rejects SMS/voice/proprietary-provider communications code: provider SDKs and hosts, webhook routes, TwiML, SIP, communications tables, screens, and permissions that belong to ARK Shop. Installation SMTP/sendmail for standalone customer email is permitted. `scripts/assert-no-communications.php` is the check, and its denylist is the source of truth.

`scripts/communications-baseline.json` lists the code still being moved to ARK Shop.

- A new violation fails CI.
- A fixed violation must be removed from the baseline, or CI fails.
- A missing baseline also fails CI.
- The baseline may only shrink. When it is empty, CI rejects any communications code.

The script holds a short allowlist of framework and installation-mail files, each with a reason. Staff `MAIL_*` and standalone installation SMTP entries are listed explicitly. The baseline does not hold allowances.

ARK Shop CI separately proves that each communications route and service in the composed product is the one it expects. Each must come from Core until its move, and from ARK Shop after.

Standalone Core must not expose SaaS provider configuration through Settings navigation or by direct URL. Provider-neutral contracts and historical records remain. Hosted shops receive provider configuration through ARK Shop entitlements, not through a free standalone setup path.

## Related

- Install: [installation/README.md](installation/README.md)
- Payments implementation note: [platform/ark-payments-boundary-v1.md](platform/ark-payments-boundary-v1.md). Historical. The current Core/Hosted product split is this page.
- Website: [platform/ark-core-website-boundary.md](platform/ark-core-website-boundary.md)
