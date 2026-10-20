---
title: Environment variables
description: Bootstrap and integration environment variables from the ARK Core example file.
---

# Environment variables

<!-- source: .env.example -->

Names come from `.env.example` and the config files that read them. Defaults below are the example file or the config fallback, not a recommendation to copy a production secret.

## Application

| Variable | Example or fallback | Purpose |
| --- | --- | --- |
| `APP_NAME` | `ARK` | Application name |
| `APP_ENV` | `local` | Environment name |
| `APP_KEY` | empty | Encryption key. Required before production use. |
| `APP_DEBUG` | `true` in the example | Stack traces. Off in production. |
| `APP_URL` | `http://localhost` | Public origin. Compose uses `http://localhost:8088`. |
| `APP_LOCALE` | `en` | Locale |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost |
| `LOG_CHANNEL` | `stack` | Log stack |
| `LOG_LEVEL` | `debug` | Log verbosity |

## Database

| Variable | Example |
| --- | --- |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `ark` |
| `DB_USERNAME` | `root` |
| `DB_PASSWORD` | empty |
| `ARK_MANAGED_DATABASE` | unset. Compose sets `true`. |

`ARKSMS_LEGACY_*` is an optional read-only import source. Leave it empty when you are not importing.

## Session, cache, queue, broadcast

| Variable | Example | Notes |
| --- | --- | --- |
| `SESSION_DRIVER` | `file` | Compose uses Redis. |
| `CACHE_STORE` | `file` | |
| `QUEUE_CONNECTION` | `sync` | Compose uses Redis and Horizon. |
| `BROADCAST_CONNECTION` | `log` | Compose runs Reverb. |
| `REDIS_HOST` | `127.0.0.1` | |
| `REDIS_PORT` | `6379` | |
| `FILESYSTEM_DISK` | `local` | |
| `ARK_MEDIA_DISK` | fallback `local` | Read in `config/filesystems.php`. |

## Mail and errors

| Variable | Example |
| --- | --- |
| `MAIL_MAILER` | `log` |
| `MAIL_FROM_ADDRESS` | `noreply@demo-auto.test` |
| `ERROR_REPORT_ENABLED` | `true` |
| `ERROR_REPORT_EMAIL` | `you@example.com` |

Replace the example mailbox before you rely on error mail.

## Dragon, labor guides, PDFs, labels

| Variable | Fallback | Notes |
| --- | --- | --- |
| `DRAGON_PROVIDER` | `none` | Tests use `fake`. |
| `LABOR_GUIDE_ALLDATA_URL` | `https://my.alldata.com/repair` | Opens the vendor site. |
| `LABOR_GUIDE_PRODEMAND_URL` | `https://www.prodemand.com` | Opens the vendor site. |
| `PDF_CHROME_PATH` | empty | Chromium for PDFs. |
| `QZ_CERTIFICATE_PATH` | empty | Shop-supplied. Secret. |
| `QZ_PRIVATE_KEY_PATH` | empty | Shop-supplied. Secret. |

## Surfaces

`SURFACE_DOMAINS_ENABLED`, `APP_DOMAIN`, `PORTAL_DOMAIN`, and `PUBLIC_DOMAIN` are host routing. They are not shop content. `ARK_WEBSITE_HOST` is commented in the example. The website editor product is not part of Core.

Do not commit real values for `APP_KEY`, `DB_PASSWORD`, `REVERB_APP_SECRET`, AWS keys, or QZ private keys. `.env` is gitignored.
