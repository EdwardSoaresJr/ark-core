# ARK

**Shop management software for independent auto repair shops.**

**Copyright (C) 2026 Edward Soares Jr.** · Licensed under **AGPL-3.0-only** (see `LICENSE`).

ARK is shop management software built around the way an automotive repair shop actually operates. It handles repair orders, customers, vehicles, estimates, inspections, scheduling, communications, and the day-to-day workflows between advisors and technicians.

Under the hood, ARK is designed around clear sources of truth, predictable system behavior, and server-side business rules rather than duplicating important logic throughout the application.

This repository contains the **public open-source distribution of ARK**.

It is a clean public snapshot and does not include private shop data, production credentials or infrastructure, licensed automotive datasets, or private Dragon knowledge sources.

## What you get

* **ARK Web** — the Laravel-based core shop management system
* Database migrations, automated tests, and configuration examples
* Synthetic/demo shop seed data
* Optional client applications under `apps/`, including Desk, Tech, and Shop Glass where included
* **Dragon runtime** with support for your own model provider credentials
* Labor-guide import interfaces for integrating supported external data sources

## What is not included

Some parts of the environment used to operate and develop ARK cannot or should not be distributed publicly. This repository does not include:

* Production deployment runbooks or infrastructure configuration
* Credentials, backups, or live secrets
* Real-Time Labor Guide (RTE) or other licensed automotive datasets
* Private Dragon knowledge imports or ARKademy data
* Republished third-party training material

**Open the engine. Bring your own fuel.**

## See ARK in action

### Run the shop from one place

![ARK Job Board](docs/images/ark-job-board.png)

### Repair orders that keep the full story of the job together

![ARK Repair Order](docs/images/ark-repair-order.png)

### Digital vehicle inspections

![ARK Digital Vehicle Inspection](docs/images/ark-inspection.png)

### Customer estimates and approvals

![ARK Customer Estimate](docs/images/ark-estimate-customer.png)

### Customer communication built into the workflow

![ARK Communications](docs/images/ark-communications.png)

## Requirements

ARK can be run with Docker Compose or directly on a compatible PHP environment.

* PHP 8.3+ — match the version requirements in `composer.json`
* Composer when running directly on the host
* Node.js and npm for Vite assets
* MySQL 8 for the application database
* Redis — required for the canonical runtime (cache, sessions, queues, Horizon)

Automated tests use an isolated SQLite database at `database/testing.sqlite` through PHPUnit. Tests do not use your application MySQL database.

## Quick start with Docker Compose

**Recommended.** Compose boots the same runtime architecture ARK runs in production:

MySQL · Redis · app (nginx, PHP-FPM, Horizon, Reverb, scheduler) · persistent storage

```bash
git clone https://github.com/EdwardSoaresJr/ark.git
cd ark
docker compose up -d --build
```

Then open:

**http://localhost:8088/setup**

When using the included Compose environment, the default database connection values are:

* Host: `mysql`
* Port: `3306`
* Database: `ark`
* Username: `ark`
* Password: `ark`

**Cloud VPS (Vultr):** step-by-step guide with HTTPS — [`docs/installation/vultr.md`](docs/installation/vultr.md).  
Starter size is **~2 GB RAM (~$10/mo)** until a **$5 / 1 GB** plan is stranger-certified.

**Cloud VPS (Vultr):** [`docs/installation/vultr.md`](docs/installation/vultr.md) — Ubuntu, Docker, HTTPS (Caddy), `/setup`.  
Starter size: **~2 GB RAM (~$10/mo)** until a **$5 / 1 GB** plan is stranger-certified.

See `docs/installation/README.md` for what the stack includes and for advanced (non-Docker) installation.

## Quick start with local PHP

```bash
composer install
cp .env.example .env

# You may configure APP_KEY and DB_* manually,
# or allow /setup to guide configuration on writable installs.
php artisan key:generate

# Point DB_* at an empty MySQL database, then:
php artisan serve
```

Open the application URL in your browser. If ARK has not been installed yet, it will direct you to **`/setup`**.

Environment-based bootstrap configuration is also available for advanced deployments, but the setup wizard is the normal installation path.

Development seeders may create example staff accounts such as `admin@ark.test`. These accounts are for development and demonstration use only. A normal production installation creates its own administrator during setup.

## Optional integrations

ARK can operate without most third-party integrations.

Optional integrations include:

* Square
* Twilio
* ARK Mail
* PartsTech
* OpenAI / Dragon
* External labor-guide imports

Features that depend on an integration should remain disabled or clearly report that configuration is required when credentials are not available.

Configure only the integrations you intend to use.

### Dragon

Dragon can use an external model provider. For example:

```env
OPENAI_API_KEY=
DRAGON_PROVIDER=openai
DRAGON_OPENAI_MODEL=gpt-4o
```

The public distribution includes the Dragon runtime but does not include private shop knowledge sources or proprietary knowledge imports.

## Architecture

A few architectural rules are important when working on ARK:

* **Database per tenant** — ARK does not use a shared-database `shop_id` tenancy model.
* **Workstations and stations** represent physical locations within a shop, not separate tenants.
* **Authoritative services own business truth.** Projections and views present that information rather than independently recreating it.
* **Financial calculations stay server-side.** Important totals should come from authoritative calculators instead of being duplicated in client-side JavaScript.

These boundaries are intentional and should be preserved when extending the application.

See `docs/engineering/` for additional architecture and engineering documentation. Some historical documentation may still reference the shop environment where ARK was originally developed and tested.

## License

**Copyright (C) 2026 Edward Soares Jr.**

ARK is open-source software licensed under the **GNU Affero General Public License v3.0 only** (`AGPL-3.0-only`).

See:

* `LICENSE`
* `NOTICE`

Optional Square payment support installed through:

```bash
composer require ark/payments-square
```

may install additional third-party packages distributed under their own licenses. See `NOTICE` for details.

ARK's AGPL license does not change the licenses of those third-party packages.

For project naming and branding guidelines, see `TRADEMARKS.md`.

You may modify and fork ARK under the terms of the AGPL. Modified distributions should not be presented as the official ARK distribution without permission.

The licensing information in this repository describes the project's licensing choices and is not legal advice.

## Status

ARK is publicly available at:

https://github.com/EdwardSoaresJr/ark
