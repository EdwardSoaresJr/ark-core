# SMTP acceptance evidence — Core #290

## Controlled transport proof (automated)

`tests/Feature/Installation/InstallationMailSmtpAcceptanceTest.php`

- Fake `TransportInterface` records subject, HTML, recipient, and attachment count.
- Estimate send returns `transport_accepted` (ok=true, confirmedDelivered=false).
- HTML includes portal link and vehicle; one PDF attachment present.
- Failure path redacts credentials from operator messages.

Command:

```bash
php artisan test --filter='InstallationMailSmtpAcceptance'
# passed (3 tests)
```

## TLS / certificate policy (Symfony Mailer v8.1.5)

`InstallationMailTransportFactory` builds `EsmtpTransport` directly (DSN `encryption=tls` is ignored by Symfony 8 `EsmtpTransportFactory`).

- TLS mode: `setRequireTls(true)` + `setAutoTls(true)` — refuses plaintext when STARTTLS is absent.
- SSL mode: implicit TLS + require TLS.
- `verify_peer` / `verify_peer_name` forced true; `allow_self_signed` false.

Covered by `InstallationMailTransportFactoryTest`.

## Live external SMTP

Not exercised in this gate against a public mailbox (no production send authorized). Controlled transport + TLS unit proof stand as acceptance evidence for merge review; optional live Mailpit/SMTP proof can be attached by the owner before production cutover.
