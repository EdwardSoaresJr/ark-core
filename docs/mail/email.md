# Customer email (ARK Cloud → ARK Mail)

Official ARK sends production transactional email through **ARK Cloud**, which entitles the Box to the **ARK Mail** service.

```
OutboundTransactionalMail
        ↓
ArkMailClient
        ↓
ARK Cloud (identity + entitlement)
        ↓
Mail service policy / fake|Postmark transport
```

## Production behavior

| State | Result |
|---|---|
| Box paired + mail entitled | Transactional mail works |
| Not paired / not entitled | *Email isn’t configured yet.* — no false “sent” |

No official BYO Postmark / SMTP Settings path.

## Pairing

1. Box starts pairing (`POST /api/v1/pairing/start`) and shows a short code.
2. Operator approves the code in the ARK Cloud portal for a shop (**no credential in the browser**).
3. Box claims once (`POST /api/v1/pairing/claim`) and stores the Cloud-issued credential.

Cloud owns installation identity and entitlements. Mail owns mail-specific policy.

## Development / CI

Non-production may use Laravel `log` or `array` mailers.

## Configuration

```env
ARK_MAIL_SERVICE_URL=
ARK_MAIL_ALLOW_ACTIVATION=false
MAIL_MAILER=log
```

`ARK_MAIL_SERVICE_URL` points at ARK Cloud (Mail is served under Cloud). Never put upstream Postmark credentials in self-hosted ARK.
