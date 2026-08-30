# Customer email (ARK Mail + BYO)

## Options

1. **ARK Mail** (recommended convenience) — managed transactional email via the private hosted ARK Mail service. The shop never receives Postmark master credentials.
2. **Bring your own Postmark** — configure a server token under Settings → Email. Self-hosted ARK remains fully independent.
3. **Not configured** — send actions show *Email isn’t configured yet* and link to Settings. No false “sent”, no crash, no silent discard.

## Reply-To

ARK Mail sends From an ARK-controlled domain (`shop-{id}@sending-domain`). **Reply-To** is the shop’s configured email (Shop Profile / reply-to). Customers reply directly to the shop.

## Transactional only

ARK Mail allows operational classes such as estimate, invoice, inspection, appointment, document, payment/deposit/review links, and account system messages. Marketing, newsletters, and broadcast are rejected by the hosted service — not by trusting the client label.

## Configuration

```env
ARK_MAIL_SERVICE_URL=
ARK_MAIL_ALLOW_ACTIVATION=false
POSTMARK_TOKEN=
```

Never put ARK Mail’s upstream Postmark credentials in self-hosted ARK.

## Installation identity

`storage/app/install/installation_uuid` is a durable non-secret UUID. The ARK Mail credential (encrypted in shop settings) authenticates.

## Credential threat boundary

| Compromise | What an attacker gets | What they do not get |
|---|---|---|
| **Public ARK database alone** | Encrypted `ark_mail_credential` ciphertext (and other shop rows) | Usable signing secret **if** `APP_KEY` / disk secrets are not also compromised — Laravel `encrypted` cast requires the app key |
| **Full ARK host** (DB + `APP_KEY` / filesystem / running process) | Ability to sign as that installation until revoked | Upstream Postmark tokens, other tenants, ability to override From/Reply-To/quotas on the control plane |

ARK Mail’s boundary is **service-side policy**, not DRM on the client. A fully compromised authorized install can use its valid credentials until the tenant/installation is suspended or revoked on ark-mail.

Stranger-cert should explicitly check: DB dump without `APP_KEY` is insufficient to mint valid HMAC signatures.
