# Customer email (ARK Mail)

Official ARK sends production transactional email through **ARK Mail** only.

```
OutboundTransactionalMail
        ↓
ArkMailClient
        ↓
private ark-mail control plane
        ↓
Postmark (service-side)
```

## Production behavior

| State | Result |
|---|---|
| ARK Mail connected | Transactional mail works |
| ARK Mail not connected | *Email isn’t configured yet.* — no false “sent” |

There is **no** official BYO Postmark / SMTP Settings path. Forks may implement other providers under AGPL; official ARK does not maintain that seam.

## Development / CI

Non-production environments may use Laravel `log` or `array` mailers so local work and tests do not require the hosted service.

## Reply-To

When ARK Mail is connected, customer replies go to the shop reply-to address (Settings → Email), defaulting to Shop Profile email.

## Transactional only

Estimate, invoice, inspection, appointment, document, payment/deposit/review links, and account system messages. Marketing and broadcast are rejected by the hosted service.

## Configuration

```env
ARK_MAIL_SERVICE_URL=
ARK_MAIL_ALLOW_ACTIVATION=false
MAIL_MAILER=log
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
