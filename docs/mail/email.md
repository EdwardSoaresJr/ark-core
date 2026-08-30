# Customer email (ARK Mail)

ARK sends transactional customer email through **ARK Mail**.

## Operator setup

1. Open **Settings → Email**
2. Choose **Connect**
3. Set the shop reply-to address (defaults to Shop Profile email)

| State | Result |
|---|---|
| Connected | Transactional mail works |
| Not connected | *Email isn’t configured yet.* — nothing pretends to have sent |
| Suspended | ARK Mail is suspended — reconnect or contact support |

Marketing and broadcast email are not supported.

## Development / CI

Local and test environments may use Laravel `log` or `array` mailers so work does not require the hosted service.

```env
ARK_MAIL_SERVICE_URL=
ARK_MAIL_ALLOW_ACTIVATION=false
MAIL_MAILER=log
```

## Engineering notes

`ArkMailClient` posts to the hosted ARK Mail API using the installation UUID and the encrypted shop credential stored after Connect.

Do not put the mail service’s upstream provider tokens in self-hosted ARK. From address, reply-to, and quotas are enforced by the mail service.

### Credential compromise

| Compromise | Attacker can | Attacker cannot |
|---|---|---|
| Database only (no `APP_KEY`) | See encrypted credential ciphertext | Mint valid signed requests |
| Full host (DB + `APP_KEY` / running process) | Send as that installation until revoked | Access other shops’ mail, or the mail service’s upstream provider credentials |

Revoke or suspend the installation on the mail service if a shop host is compromised.
