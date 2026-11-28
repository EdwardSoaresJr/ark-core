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
3. Choose a configuration mode:
   - **Disabled** — customer email stays off (environment `MAIL_*` does not reactivate it)
   - **Installation Settings** — use the SMTP/sendmail form on this page only
   - **Environment Managed** — use server `MAIL_*` only
4. For Installation Settings, enter SMTP hostname, port, encryption, username, password, and sender address.
5. Save, then send a test message to yourself.

Passwords are encrypted at rest and masked in the UI. They are never flashed back into the session. Check **Replace stored password** to change or clear them. Configuration changes apply to the next send without restarting queue workers. Changing connection settings clears the previous test status.

TLS (STARTTLS) is required when encryption is TLS — plaintext downgrade is refused. Certificate verification stays on.

Hosted SaaS shops do not see this screen and never fall back to installation SMTP.

## Environment configuration (optional)

Automated and infrastructure-managed installs may set Laravel `MAIL_*`, then select **Environment Managed** in Settings → Email:

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

### Modes (no silent crossover)

| Mode | Behavior |
| --- | --- |
| Disabled | No installation customer delivery |
| Installation Settings | Only the saved shop SMTP/sendmail (invalid → not configured) |
| Environment Managed | Only `MAIL_*` smtp/sendmail |

The Email settings page shows the active mode and transport.

## Delivery honesty

- **Queued** — accepted into the application queue
- **Transport accepted** — the SMTP/sendmail server accepted the message
- **Failed** — transport or validation error
- SMTP acceptance is **not** confirmed delivery to the recipient’s inbox
