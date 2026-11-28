---
title: Install with PHP
description: Run ARK Core on a host PHP install without Docker.
---

# Install with PHP

<!-- source: docs/installation/README.md -->

Use this path when you are running PHP, MySQL, and a web server yourself. It is a supported install. It is not the default demo path. You are responsible for the queue worker, the scheduler, Redis if you enable realtime features, and HTTPS.

## Application code

```bash
git clone https://github.com/EdwardSoaresJr/ark-core.git
cd ark-core
composer install
cp .env.example .env
php artisan key:generate
```

Create an **empty** MySQL 8 database and set `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`. The installer refuses a database that already has a foreign or populated schema.

Compile the shop interface:

```bash
npm install
npm run build
```

`storage/` and `bootstrap/cache/` must be writable by the PHP user. `composer install` runs `php artisan storage:link`, which links `public/storage` to `storage/app/public`.

## Web server

Point the document root at `public/`. A typical local check is:

```bash
php artisan serve
```

Open the URL. If Core is not installed, requests are redirected to `/setup`.

For Apache or nginx, use a normal Laravel front controller. Pretty URLs must reach `public/index.php`. Do not expose the repository root as the document root.

## Workers you still have to run

A host install does not start Horizon, Reverb, or the scheduler for you. `.env.example` defaults to:

| Variable | Example default | Effect |
| --- | --- | --- |
| `QUEUE_CONNECTION` | `sync` | Jobs run inside the web request |
| `CACHE_STORE` | `file` | File cache |
| `SESSION_DRIVER` | `file` | File sessions |
| `BROADCAST_CONNECTION` | `log` | Broadcasts are logged, not pushed to browsers |

That is enough to finish setup and use the shop without Redis. Before you depend on background jobs or browser realtime, switch the queue and cache to Redis and run the processes in [Environment and services](/getting-started/configuration).

PDF generation prefers a Chromium binary when Node/Chrome are available. On constrained hosts (including shared hosting from a Release ZIP), the native PHP PDF engine (mPDF) renders estimates and invoices without Chromium. Label printing through QZ Tray needs a certificate and private key the shop supplies; those files do not ship in the repository.

For shared hosting without Composer or Node on the server, use a CI-built Release ZIP (`scripts/build-release-zip.sh`) and point the document root at `public/`. Host PHP must be **8.4.1+**. The `/setup` system check refuses older versions before installation begins.

## Development convenience

`composer dev` runs the PHP server, a queue listener, Reverb, log tailing, and Vite together. That script is for local development. It is not a production process manager.

Development seeders may create accounts such as `admin@ark.test`. A normal production installation creates its own administrator during setup. Do not treat seeder accounts as production credentials.
