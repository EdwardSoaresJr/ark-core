# ADR-0008: Core owns shop work; Platform owns commercial execution

**Status:** Accepted · implementation incomplete  
**Date:** 2026-09-20

**Extends:** [ADR-0007](ADR-0007-stock-core-voice-transport-boundary.md) (voice) to all independently monetizable providers.

**This ADR authorizes no extraction, deletion, deployment, or production change.** Completion requires the two gates below. A listed failure is not a work order.

Companion: [Core–Platform ownership contract](../../platform/core-platform-ownership-contract-v1.md).

## Context

Core still contains leftover provider execution (Square capture fallback, Twilio voice, Postmark send, PartsTech HTTP). Platform already executes some of those services for hosted shops. Hostname changes (`app` / `cloud` / `api`) do not move credentials, SDKs, or entitlement checks.

Core cannot have zero HTTP or API code: it has a shop UI, a Platform client, and fabric ingress. It can have zero independently monetizable provider integrations.

## Decision

**Core owns the workflow and the shop data. Platform owns external service execution.**

| Core | Platform |
| --- | --- |
| Customers, vehicles, repair orders | Provider credentials and integrations |
| Estimates, invoices, inspections | Twilio, Square, Postmark, PartsTech connectivity |
| Local shop settings and workflows | Subscription billing and entitlements |
| Local records and the payment ledger | Payment capture and provider webhooks |
| Minimal authenticated Platform client | External API endpoints and provider adapters |

Allowed in Core: shop HTTP, staff/customer UI, fabric ingress that applies Platform results, thin clients (`ArkPaymentsClient`, `ArkCommunicationsClient`, `ArkMailClient`, `ArkPartsClient`, pairing/heartbeat).

Forbidden in Core for monetized services: provider credentials, provider SDK or direct provider HTTP, entitlement enforcement.

**No fallback:** no Platform means no managed service. Disabling or disconnecting Platform must not cause Core to contact Square, Twilio, Postmark, or PartsTech. Ordinary shop operations stay available. The shop records an external payment, or the managed action is unavailable.

Handshake (card payment is the pattern; same for SMS, mail, catalog):

1. Core starts the shop request (amount, RO, surface).
2. Platform checks entitlement and executes the provider.
3. Platform returns the result (or delivers it on ingress).
4. Core records the result in shop records (ledger, conversation workflow, estimate lines).

**Standalone Core stays a complete shop OS** without a Platform subscription: customers, vehicles, ROs, estimates, invoices, inspections, staff, printing, local settings, and recording external payments. It does not take cards, send managed SMS/mail, or search a paid parts catalog.

**Cloud is not a third product.** `cloud.arksms.com` is Platform’s current API/webhook host. `app.arksms.com` is Platform’s customer-facing application. `api.arksms.com` may later replace the Cloud API host.

## Three workstreams — keep distinct

| Stream | Job | Not |
| --- | --- | --- |
| **ADR-0008** | Code extraction and operational verification (audit + standalone tests) | Settings chrome, DNS |
| **Isolated Settings** | UI changes and Voice ownership gating | Removing Core provider paths |
| **Hostnames** | Customer and API address migration | Architectural completeness |

## Migration — one provider at a time

A known gap is not permission to delete production code.

1. Prove the Platform path is what the shop actually uses.
2. Verify that path on the floor.
3. Only then remove the matching Core provider path.

Do not bundle Square, Voice, mail, and PartsTech. Do not remove a Core path because Settings already hid it.

## Acceptance — complete only when both pass

Not when domains look right.

**Code audit**

1. Monetized provider credentials and OAuth live only in Platform.
2. Provider adapters, capture, send, catalog HTTP, and provider webhooks live only in Platform.
3. Entitlement for those services is decided only in Platform.
4. Core has no Square / Twilio / Postmark / PartsTech execute path after Platform is disabled or disconnected.

**Standalone Core tests**

Core without Platform opens a repair order and records an external payment. It does not contact a commercial provider to do that.

Known gaps today (not a cleanup license): Core Square fallback when `ManagedPaymentsGate::platformCapture()` is false; Core Twilio voice webhooks; Core Postmark send; Core PartsTech HTTP and Settings secrets.

## Consequences

- New Core work may call Platform. It must not add a provider token field, webhook, SDK, or a disconnect fallback to the provider.
- Class names that still say Square do not make Core a Square integration. Behavior must follow the handshake.
- Self-hosted provider keys are leftover compatibility, not a product.
- [Payments boundary v1](../../platform/ark-payments-boundary-v1.md) stands: Core is first-class shop software without Cloud, not a card processor.
