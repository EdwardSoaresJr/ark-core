# ARK

**Shop management software for independent auto repair shops.**

**Copyright (C) 2026 Edward Soares Jr.** · Licensed under **AGPL-3.0-only** (see `LICENSE`).

ARK is operational shop software — repair orders, customers, vehicles, estimates,
inspections, scheduling, communications, and advisor/technician workflows —
built around authorities and projections, not CRUD theater.

This repository is the **public open-source distribution** of ARK.

It is a **clean snapshot**. It does **not** carry private shop history,
production infrastructure, licensed labor-guide datasets, or Hosted Dragon fuel
from the private foundry that proves ARK on the floor.

## What you get

- ARK Web (Laravel) — core shop management system
- Migrations, tests, and configuration examples
- Synthetic / demo shop seed data
- Optional client apps under `apps/` (Desk, Tech, Shop Glass) where included
- Dragon **runtime** (bring your own model provider key) — without private shop knowledge packs
- Labor-guide **import interface** — without redistributed third-party labor data

## What you do not get (by design)

- Production Coolify / Demo Auto Repair deploy runbooks
- Credential backups or live secrets
- Real-Time Labor Guide (RTE) or other licensed automotive datasets
- Private Dragon knowledge imports / ARKademy dumps
- Third-party training course republication

**Open the engine. Bring your own fuel.**

## See ARK in action

### Run the whole shop from one place

![ARK Job Board](docs/images/ark-job-board.png)

### Repair orders without losing the story of the job

![ARK Repair Order](docs/images/ark-repair-order.png)

### Digital vehicle inspections

![ARK Digital Vehicle Inspection](docs/images/ark-inspection.png)

### Customer estimates and approvals

![ARK Customer Estimate](docs/images/ark-estimate-customer.png)

### Customer communication built into the workflow

![ARK Communications](docs/images/ark-communications.png)

## Requirements

- PHP 8.3+ (match `composer.json`) — **or** Docker Compose (below)
- Composer (host path only)
- Node.js + npm (Vite assets; host path / image build as needed)
- MySQL 8 (application database)
- Redis optional for first-run (file/database drivers); recommended for full production shape

Automated tests use an isolated SQLite file (`database/testing.sqlite`) via PHPUnit — not your MySQL database.

## Quick start (Docker Compose — stranger path)

```bash
docker compose up -d --build
```

Open **http://localhost:8088** → complete **`/setup`**.

On the Compose network, database fields are: host `mysql`, port `3306`, database/user/password `ark`.

See `docs/installation/README.md`.

## Quick start (local PHP)

```bash
composer install
cp .env.example .env
# Optional: set APP_KEY and DB_* here, or let /setup guide you on writable hosts.
php artisan key:generate   # skip if the wizard will generate on writable installs

# Point DB_* at an empty MySQL database, then:
php artisan serve
```

Visit the app URL. If ARK is not installed, the browser opens **`/setup`**.

Advanced operators may still configure bootstrap via environment variables; the wizard is the normal product path.

Default seeded staff users (`admin@ark.test`, etc.) are for **development seeders only** — production first-run creates your own administrator in the wizard.

## Optional integrations

Square, Twilio, Postmark, PartsTech, OpenAI/Dragon, and labor-guide imports are
**optional**. When credentials are absent, capabilities should fail honestly or
stay disabled — configure only what you use.

Dragon:

```env
OPENAI_API_KEY=
DRAGON_PROVIDER=openai
DRAGON_OPENAI_MODEL=gpt-4o
```

## Architecture notes

- **Database-per-tenant** — do not introduce a `shop_id` multi-tenant column model
- **Workstation / station** — intra-tenant physical place for orientation
- **Authorities own truth; projections summarize; views render**
- Financial totals flow through authoritative server calculators — not duplicated in JavaScript

See `docs/engineering/` for deeper doctrine (scrubbed for public distribution; some historical notes may still mention the proving-ground shop).

## License

**Copyright (C) 2026 Edward Soares Jr.**

License: ARK is open-source software licensed under the
**GNU Affero General Public License v3.0 only** (`AGPL-3.0-only`).
See `LICENSE` and `NOTICE`.

Optional Square payments (`composer require ark/payments-square`) installs
additional third-party packages under **their** licenses; see `NOTICE`.
ARK’s AGPL does not relicense those packages.

Brand and naming: see `TRADEMARKS.md`. You may fork under the AGPL; do not
present a modified product as the official ARK distribution without permission.

This licensing choice is a **project decision**, not legal advice.

## Status

Public release: https://github.com/EdwardSoaresJr/ark
