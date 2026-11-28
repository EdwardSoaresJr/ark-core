# Native customer email (self-hosted)

Following the separation of commercial communications services from ARK Core, native email uses installation-controlled mail infrastructure. Self-hosted shops can send customer communications without an ARK account.

## What it covers

- Estimates, invoices, inspections, and customer statements
- PDF attachments and portal links where Core already provides them
- Test email from Settings

SMS, MMS, voice, and managed ARK Email remain ARK Shop capabilities.

## Configure in Settings (recommended)

1. Sign in as an Admin.
2. Open **Settings → Email**.
3. Enable customer email, enter SMTP hostname, port, encryption, username, password, and sender address.
4. Save, then send a test message to yourself.

Passwords are encrypted at rest and masked in the UI. Check **Replace stored password** to change or clear them. Configuration changes apply to the next send without restarting queue workers.

Hosted SaaS shops do not see this screen and never fall back to installation SMTP.

## Environment configuration (optional)

Automated and infrastructure-managed installs may set Laravel `MAIL_*` instead of (or before) using the UI:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.yourshop.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=shop@yourshop.com
MAIL_FROM_NAME="Your Shop"
```

`log` and `array` mailers are never treated as successful customer delivery.

### Precedence

1. Shop Settings when enabled and complete
2. Otherwise environment `MAIL_*` when mailer is `smtp` or `sendmail`
3. Otherwise not configured

The Email settings page shows which source is active.

## Delivery honesty

- **Queued** — accepted into the application queue
- **Transport accepted** — the SMTP/sendmail server accepted the message
- **Failed** — transport or validation error
- SMTP acceptance is **not** confirmed delivery to the recipient’s inbox
