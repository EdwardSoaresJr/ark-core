# Email boundary

ARK Core has no outbound email capability.

Core emits typed email intents and authoritative variables. Platform owns email entitlement, templates, rendering, branding, delivery, and provider integration. Free email capabilities are still Platform capabilities. Core must never provide a local-provider fallback.

## What Core sends

A typed operation, the recipient, and the facts needed to fill the message:

- shop and customer identity
- totals and balances Core already calculated
- secure URLs, tokens, and codes Core created
- expirations Core chose
- document bytes Core stored, when the message includes an attachment

Core does not send subject or HTML. Core does not keep a template registry, editable templates, or provider settings.

## What Platform owns

- operation catalog
- allowance and entitlement
- default templates
- published shop template revisions
- subject
- HTML and text rendering
- branding
- provider delivery
- the delivery result

Draft template revisions do not affect delivery. A later template editor is a Platform UI over this contract. It must not require a Core change.

There is no generic arbitrary-content operation. `account.system_message` is not a catalog operation.

## Allowance

Platform answers "can this shop send this operation?" from the catalog class:

- `system` does not require a mail entitlement. Password recovery, staff invitations, portal sign-in, booking identity codes, account email verification, owner and coaching digests, and exception reports are still Platform capabilities.
- `starter` is included Starter repair-order email (`repair_order.estimate_ready`, `repair_order.final_invoice_ready`). It uses a Starter grant, not the mail entitlement.
- `mail` requires an active mail entitlement. Estimates, invoices, documents, deposits, review requests, and website lead confirmations are in this class.

Free or Starter does not put a mailer back in Core.

## No local fallback

A self-hosted Core install does not gain email by setting `MAIL_MAILER`, Postmark, SMTP, or SES. If Platform is unavailable or rejects the intent, the send fails. It does not fall back.

Do not install provider mail packages. Tests may fake the Platform boundary. They must not add a local send path.
