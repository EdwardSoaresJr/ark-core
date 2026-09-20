# ARK Payments Boundary v1

**Status:** Locked product doctrine · Documentation only  
**Does not implement:** ARK Payments (Cloud), Square/Stripe in Core, billing, Hosted transfer, ARK Data, full ARK Connect

## Ownership

| Layer | Owns |
| --- | --- |
| **ARK Core** | Payment **truth** — invoice, amount due, ledger, payment method, allocations, refunds/adjustments, receipts/documents, RO settlement |
| **ARK Platform Payments** (future) | Managed **provider connectivity** — OAuth, tokens, webhooks, terminals, payment links that charge cards |
| **Shop staff** | Recording external payments (cash, card taken elsewhere, check, other) on the repair order |

Core does **not** ship turnkey processor credentials, OAuth, charging clients, or processor webhooks.

A shop may charge a customer with Square, Stripe, a standalone terminal, cash, or check **outside** ARK, then **record** that settlement in Core. That is a supported Core workflow — not a degraded workaround.

## Provider-neutral Core

Core may understand provider-neutral concepts (payment request, status, external settlement, opaque external reference). Core domain contracts must not require Square/Stripe identifiers as authority.

Historical schema (`payment_gateway_attempts`, legacy `square_*` settings columns) may remain for existing installs. Core no longer writes processor attempts or exposes processor credential settings.

## Portal / secure links

Portal pay/deposit tokens identify an invoice or deposit request and show amount due. Card capture on those pages is reserved for future **ARK Platform Payments** via **ARK Connect**.

Until then, customers are directed to pay at the shop; staff record the payment in Core.

Starter Estimate Ready / Final Invoice secure links are a **restricted** Cloud interaction path. They must remain migratable into broader ARK Connect without becoming a parallel security model — and must **not** auto-grant full paid Connect.

---

## One Core = one location

One Core installation is one operating shop/location.

ARK Platform may connect and organize many independent Core installations under an Account.

Cross-location aggregation belongs to future **ARK Data**, not to making Core a multi-location database.

Do not add `location_id` fan-out across Core operational tables as Core multi-location.

---

## Self-hosted and Hosted

| Concern | Rule |
| --- | --- |
| **Self-hosted Core** | First-class. Fully capable without Cloud. |
| **ARK Hosted** | Same ARK Core binary/runtime. Hosting is infrastructure + managed ops — not a privileged Core edition. |
| **Hosting status** | Must **not** unlock Core features (`if hosted: enable_core_capability()` is forbidden). |
| **Cloud entitlements** | Determine managed Cloud capabilities. Separate from who operates the Box. |

### Commercial packaging (documentation only — not billing code)

| Offering | Role |
| --- | --- |
| **ARK Core** | Free · open source · unlimited · self-hostable · one location per Core |
| **ARK Platform Starter** | Free forever · after explicit Cloud connect · Essential Delivery · 20 Cloud-enabled ROs/month · Estimate Ready · Final Invoice |
| **ARK Complete** | Working target ~$149/location/month · self-hosted Core + Complete Cloud/app bundle |
| **ARK Complete Hosted** | Working target ~$199/location/month · same Complete capability + ARK-managed infrastructure uplift |

No Stripe Billing / subscription engine is implemented in Core by this doctrine.

---

## Two-way Hosted portability (future — not implemented)

```text
SELF-HOSTED ARK CORE  ⇄  ARK COMPLETE HOSTED
```

Moving between self-hosted and ARK Hosted is a **transfer of durable ARK state and installation authority**, not an application conversion.

Durable state (conceptually): database · persistent media · APP_KEY / required crypto · installation identity · Cloud relationship metadata · ARK/schema version · migration manifest · checksums.

Ephemeral: caches, queues, Redis transient state, containers, generated views.

**Single-active-Box:** migration must not leave two installations simultaneously active as the same Box. Fail-safe: source remains authoritative until destination is verified. Cloud entitlements survive deployment ownership change.

Do **not** implement Hosted provisioning, migration UI, fencing, or transfer endpoints in this repository slice.

---

## ARK Data (future — not implemented)

Core → Cloud **knowledge/projection**.

Authorized, versioned projections for historical sync, company-wide search, cross-location customer/VIN/RO/declined-work/warranty visibility, aggregate reporting, future Dragon context.

**Must not:** SSH/MySQL scrape the Box; Hosted-only DB backdoor; become a second SMS; mutate ROs as authority.

**Must:** Core-owned sync contract; explicit entitlement (Cloud Connected ≠ Data sync); offline Core continues without Data; DIY and Hosted use the same model.

---

## ARK Connect (future — not implemented)

Outside world ↔ **authoritative Core** interaction (portal actions, approvals, appointments, messaging, documents, future Pay Invoice, third-party apps).

Customer browser should not need direct privileged access to the Core Box.

Consequential writes route Connect → owning Core. Data may inform reads. Connect is not generic RCE / SQL / HTTP proxy — semantic authorized operations only.

Future payment path (not built):

```text
Customer Portal → ARK Connect → ARK Payments → processor → settlement recorded in Core
```

---

## Compatibility checklist (payments extraction)

This extraction must leave Core ledger authority intact, preserve external payment recording, leave a clean seam for Cloud Payments / Connect Pay Invoice, and avoid Hosted-only payment formats that block self-hosted restore.
