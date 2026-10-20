---
title: Environment and services
description: Environment variables, database, storage, mail, queues, and the scheduler for ARK Core.
---

# Environment and services

<!-- source: .env.example -->

Bootstrap configuration lives in the environment. Shop configuration lives in the database after `/setup`. Do not put provider tokens for ARK Shop services into a public Core `.env` and treat that as a self-hosted product.

`.env.example` is the template. Names below are the ones a new install actually has to understand. Values in examples are placeholders.

## Bootstrap

| Variable | Role |
| --- | --- |
| `APP_NAME` | Display name. Default `ARK`. |
| `APP_ENV` | `local`, `production`, or `testing`. |
| `APP_KEY` | Encryption key. Generate with `php artisan key:generate` or let Compose create it. Losing it makes encrypted values unreadable. |
| `APP_DEBUG` | `true` only on a machine you control. Leave it off on a public shop. |
| `APP_URL` | Public origin, including scheme and port. Compose uses `http://localhost:8088`. |
| `DB_CONNECTION` | `mysql` for the shop. Tests force SQLite separately. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Empty MySQL database. |
| `ARK_MANAGED_DATABASE` | When `true`, `/setup` uses the runtime database without asking for credentials. Compose sets this. |

`APP_KEY` must stay with the database and `storage/` for the life of that shop. See [backup](/getting-started/updates-and-backup).

## Database

MySQL 8 is the application database. Core does not document Postgres or a shared multi-tenant schema. One installation, one database, one shop.

The installer fails closed on a non-empty foreign schema. After a successful install, schema changes ship as Laravel migrations in `database/migrations`. Apply them with `php artisan migrate` as part of an update. Do not run `migrate:fresh` or `db:wipe` against a shop you intend to keep.

## Storage

<!-- source: config/filesystems.php -->

| Disk | Default root | Use |
| --- | --- | --- |
| `local` | `storage/app/private` | Private application files. Inspection photo responses on the mobile API read this disk by name. |
| `public` | `storage/app/public` | Files exposed through the `public/storage` link. |
| `s3` | S3-compatible endpoint | Optional durable media. Set `ARK_MEDIA_DISK=s3` when you intend application media to use that disk. The default `ARK_MEDIA_DISK` is `local`. |

`FILESYSTEM_DISK` selects Laravel's default disk (`local` in the example). Configuring an S3 disk does not by itself move every existing store. Inspection photo download on `/api/mobile` currently calls `Storage::disk('local')` explicitly.

Uploaded shop files are part of durable state. Back them up with the database and `APP_KEY`.

## Mail

<!-- source: config/mail.php -->

`MAIL_MAILER` defaults to `log` in `.env.example`, which writes messages to the log instead of sending them. Set a real mailer (`smtp` or another Laravel mailer) when the server should send mail.

`MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` set the default from address. A reply-to address, when configured, is applied while a message is sending.

Customer email and customer texting as ARK-operated services are ARK Shop. Configuring Laravel mail on your own server is ordinary application mail. It does not turn Core into that hosted messaging product.

## Queues

<!-- source: config/queue.php -->

| Install | Queue |
| --- | --- |
| Compose | Redis and Horizon run inside the app container. |
| PHP, first boot | `QUEUE_CONNECTION=sync`. Jobs run in the request. |
| PHP, background work | `QUEUE_CONNECTION=redis`, Redis available, and a worker process. |

`composer dev` starts `php artisan queue:listen` for local development. Production Compose uses Horizon. A host install that sets `redis` and never runs a worker will accept jobs and not process them.

Do not point a worker at a shop database from a laptop that also runs tests. Tests use in-memory SQLite and do not share that database.

## Scheduler

<!-- source: bootstrap/app.php -->

Scheduled commands are registered in `bootstrap/app.php`. They include the owner digest, coaching digest, unhandled-communications escalation, time-clock sync, stale call-session reconciliation, appointment reminders, and a platform heartbeat. Digests and appointment reminders no-op unless the matching feature flag or shop setting allows them.

Compose runs the scheduler in the app container. A host install needs cron:

```bash
* * * * * cd /path/to/ark-core && php artisan schedule:run >> /dev/null 2>&1
```

Appointment reminders do nothing useful as customer texts unless a messaging transport is actually configured. On a stock install that transport is not a self-hosted SMS product.

## Realtime

`BROADCAST_CONNECTION=log` is the example default. Compose runs Laravel Reverb. `REVERB_APP_KEY` and `REVERB_APP_SECRET` are secrets. Do not commit them. A host mismatch between the Reverb public host and `APP_URL` is logged as a warning outside the test environment.

## Dragon

<!-- source: config/dragon.php -->

`DRAGON_PROVIDER` defaults to `none`. Tests may set `fake`. Stock Core does not include an ARK-hosted model. Configure a provider you operate, or leave Dragon unset.

## Printing and PDFs

`PDF_CHROME_PATH` points at Chromium when it is not on the default path. `QZ_CERTIFICATE_PATH` and `QZ_PRIVATE_KEY_PATH` are files the shop supplies for QZ Tray label printing. Those paths are secrets. They are not generated by `/setup`.

## Legacy import

`ARKSMS_LEGACY_*` points at an optional read-only legacy database for `php artisan` import commands. Leave them empty when you are not importing. Do not point them at a production customer database from a notebook, and do not commit that environment file.
