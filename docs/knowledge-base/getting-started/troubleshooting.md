---
title: Troubleshooting
description: Installer, database, asset, and queue failures on a stock ARK Core install.
---

# Troubleshooting

<!-- source: docs/installation/TROUBLESHOOTING.md -->

## Setup redirects forever or the page is blank

- `storage/app` and `storage/framework` must be writable.
- Run `php artisan ark:install-status`.
- Read `storage/logs/laravel.log`. The installer is not supposed to write the database password there.

## The environment file cannot be written

An immutable host must inject `APP_URL` and `DB_*`. Compose generates `APP_KEY` and database secrets on first boot. The wizard will not chmod the server to make `.env` writable.

## Database connection failed

Compose: the database step should say connected, with no password field. If it does not, read `docker compose logs mysql` and the install bootstrap logs.

Host PHP: create an empty database first, then check host, port, user, and password. `DB_HOST=127.0.0.1` from inside a container is the container, not your laptop.

## Database not empty

The installer stops. It will not overwrite a populated schema. Use an empty database.

## Migration failed during setup

Installation is not marked complete. Fix the error, then retry `/setup`. Do not run `migrate:fresh` against a database that already has shop rows.

## Setup stopped halfway

```bash
php artisan ark:install-recover --force
```

Then open `/setup` again. This command does not clear a finished install.

## Secrets missing after an update

A machine can have a MySQL volume and no saved installation secrets. Core will not generate a new password for data that already exists. Restore `ark_secrets`, or start over with `docker compose down -v`, which deletes that shop's data on this machine.

## The interface has no CSS or JavaScript

The Vite build is missing. On a host install run `npm run build`. On Compose, rebuild the image so the asset build runs inside the image. `public/hot` is a dev-server marker. It should not be present on a production host.

## Jobs never run

`QUEUE_CONNECTION=sync` runs jobs inside the request. `redis` or `database` stores them until a worker runs. Compose starts Horizon. A host install needs a supervised `php artisan horizon` or `queue:work`.

## PDFs fail

`PDF_CHROME_PATH` must point at a Chromium binary the PHP user can execute. `PDF_NO_SANDBOX` is a last resort on containers that cannot use a Chromium sandbox. Prefer a sandbox when the host allows it.

## Health

Laravel's health route is `/up`, registered from `bootstrap/app.php`. It checks that the application can boot. It does not prove that MySQL, Redis, Horizon, or Chromium are healthy.
