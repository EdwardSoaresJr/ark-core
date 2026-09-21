# ARK product boundary

What this repository is, what a stock Core install does, and what lives elsewhere.

This page matches the public Core code on this branch. It is not a roadmap.

## Core

ARK Core is self-hosted shop management software for one repair location.

A stock install (Docker Compose + `/setup`, no Platform connection) provides:

- Customers, vehicles, repair orders, estimates, inspections
- Scheduling and shop workflow
- Customer portal for viewing estimates and inspections
- Recording payments taken outside ARK (cash, check, card at a standalone terminal)
- NHTSA VIN decode
- Shop identity, financial rules, documents, staff, and printing settings
- First-run installer

One Core installation is one shop. Core is not a multi-tenant SaaS database.

## Optional (not required to run the shop)

| Capability | Prerequisite |
| --- | --- |
| Customer email (estimates, invoices, documents) | ARK Platform pairing — ARK Email |
| Customer SMS / texting | ARK Platform pairing — ARK Texting |
| Card capture inside ARK | ARK Platform pairing — ARK Payments |
| Hosted voice / desk phones | ARK Platform Voice (stock Core voice transport is not configured) |
| Labor-guide **data** | Licensed CSV import (`ark:rte-import`). Schema only ships here. |
| AllData / ProDemand buttons | Shop login URLs in environment or settings. Opens the vendor site. |
| Label printing (QZ Tray) | Certificate and private key supplied by the shop |
| Demo tickets and `admin@ark.test` | Optional `php artisan db:seed` on a development database — **not** first-run |

Without those connections, Core still runs repair orders. Email, SMS, and in-app card capture stay unavailable and say so in Settings.

## Separate products (not in this repository)

| Product | Relationship to Core |
| --- | --- |
| **ARK Platform** | Control plane and managed services. Separate repository. |
| **Foundry / shop website** | Customer-facing site and editor. Not Core. Core may keep shop identity and leads. |
| **ARK Desk, ARK Tech, Companion, Shop Glass** | Client applications. Core exposes API contracts (`/api/mobile`, `/api/desk`, `/api/tech`, `/api/station`). The apps are not shipped here. |
| **Licensed labor guides (RTE and others)** | Data you license separately. Not redistributed. |
| **ARKademy** | Training host. Not this tree. |

## Dragon

Agent-related code exists in this repository (chat loop, tools, memory tables, tests).

Stock Core does **not** ship:

- A functional model provider
- A Settings page to enter model credentials
- Private shop knowledge or ARKademy imports

Default `DRAGON_PROVIDER` is `none`. The only other built-in provider is `fake`, for tests. Settings → Dragon Memory lists facts a working agent could store; it does not turn Dragon on.

Treat Dragon as **unavailable in stock Core**, not as an included assistant.

## Experimental

Present in the tree, not a supported shop feature:

- Voice lab and `firmware/voice-terminal` (ESP capture → Core lab endpoint; off unless explicitly enabled)
- Station / Desk / Tech API surfaces without the corresponding client apps

## Leftover provider residue (not resolved)

Stock Core outbound SMS uses the Platform texting client. Stock Core voice resolves to a not-configured provider.

The codebase still contains Twilio-named helpers, Square schema columns, a Square `PaymentGateway` case, and tests that mention `api.twilio.com`. Recording playback still has a Twilio-auth code path that cannot succeed without credentials Core no longer exposes.

**This documentation pass does not close that residue.** It is tracked in [engineering/TECHNICAL_DEBT.md](engineering/TECHNICAL_DEBT.md). Do not treat leftover names or schema as a supported self-host Twilio or Square integration.

## Related

- Install: [installation/README.md](installation/README.md)
- Payments recording vs managed capture: [platform/ark-payments-boundary-v1.md](platform/ark-payments-boundary-v1.md)
- Website vs Core: [platform/ark-core-website-boundary.md](platform/ark-core-website-boundary.md)
- Canonical repo: [engineering/CANONICAL_REPOSITORY.md](engineering/CANONICAL_REPOSITORY.md)
