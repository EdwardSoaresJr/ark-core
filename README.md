# ARK

**Self-hosted automotive shop management software for independent repair shops.**

**Copyright (C) 2026 Edward Soares Jr.** · Licensed under **AGPL-3.0-only** (see `LICENSE`).

This repository is **ARK Core**: repair orders, customers, vehicles, estimates, inspections, scheduling, and the advisor/technician workflows around them. Business rules stay on the server.

It does **not** include private shop data, production secrets, licensed automotive datasets, or a working AI assistant.

Product boundaries: [`docs/PRODUCT_BOUNDARY.md`](docs/PRODUCT_BOUNDARY.md).

## What works standalone

A fresh Core install with no Platform connection can run the shop:

- Customers, vehicles, repair orders, estimates, inspections
- Scheduling
- Recording payments taken outside ARK (cash, check, card at a counter terminal)
- NHTSA VIN decode
- Customer portal for viewing estimates and inspections
- First-run setup at `/setup`

## Optional

These need something you bring, or ARK Platform:

| Need | What to use |
| --- | --- |
| Send customer email | ARK Platform → ARK Email |
| Send customer SMS | ARK Platform → ARK Texting |
| Take cards inside ARK | ARK Platform → ARK Payments |
| Hosted voice / ringing phones | ARK Platform Voice |
| Labor times in estimates | Import a **licensed** labor-guide CSV (`ark:rte-import`). Data is not bundled. |
| AllData / ProDemand | Optional browser launch URLs. Not an API integration. |

Core does not advertise a complete communications or payment stack without Platform. You can still write the RO and record money taken at the counter.

## Separate

Not in this repository:

- **ARK Platform** — managed services and control plane
- **Foundry** — shop website / CMS
- **Desk, Tech, Companion, Shop Glass** — client apps (Core publishes API contracts only)
- **Licensed labor-guide datasets**
- **A usable Dragon assistant** — see below

## Unavailable in stock Core

**Dragon.** Agent code exists (tools, memory tables, tests). Stock Core ships no model provider and no Settings screen to attach one. Default `DRAGON_PROVIDER=none`. `fake` is for automated tests. Settings → Dragon Memory does not enable an assistant.

## Experimental

Voice lab firmware (`firmware/voice-terminal`) and related lab endpoints are off unless you enable them on purpose. They are not production telephony.

## See ARK in action

### Run the shop from one place

![ARK Job Board](docs/images/ark-job-board.png)

### Repair orders that keep the full story of the job together

![ARK Repair Order](docs/images/ark-repair-order.png)

### Digital vehicle inspections

![ARK Digital Vehicle Inspection](docs/images/ark-inspection.png)

### Customer estimates and approvals

![ARK Customer Estimate](docs/images/ark-estimate-customer.png)

### Customer communication in the workflow

![ARK Communications](docs/images/ark-communications.png)

The communications **workspace** is in Core. Sending SMS or email still requires Platform (or remains unavailable).

## Requirements

- PHP 8.3+ (see `composer.json`; the Docker image uses PHP 8.4)
- Composer for native PHP installs
- Node.js and npm to build Vite assets (native PHP only — Docker builds them)
- MySQL 8
- Redis — required for Docker Compose (cache, sessions, queues, Horizon)

Automated tests use isolated SQLite (`:memory:`). They do not use your shop MySQL database.

```bash
composer test:parallel   # fast full suite (8 workers)
composer test:serial     # single-process diagnostic
./scripts/test-fast.sh   # same as test:parallel
```

## Quick start (Docker Compose)

**This is the verified path.**

```bash
git clone https://github.com/EdwardSoaresJr/ark-core.git
cd ark
docker compose up -d --build
```

Open **http://localhost:8088/setup**

The wizard uses the database Compose created. You should not type Docker-internal credentials.

First-run creates **your** administrator. It does not load demo tickets.

HTTPS on a small VPS: [`docs/installation/vultr.md`](docs/installation/vultr.md).  
Moving hosts: [`docs/installation/portable-state.md`](docs/installation/portable-state.md).  
Details: [`docs/installation/README.md`](docs/installation/README.md).

## Native PHP (advanced)

Prefer Compose unless you already run PHP, MySQL, and a process manager.

```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
```

Create an **empty** MySQL database, set `DB_*` in `.env` (or let `/setup` write them on a writable install), then:

```bash
php artisan serve
```

Open the app URL. Uninstalled Core redirects to **`/setup`**.

`public/build` is not committed. Skipping `npm run build` leaves the UI without compiled assets.

## Demo data (optional, development only)

The installer does **not** seed a demo shop.

On a development database you may run `php artisan db:seed`. That can create example staff such as `admin@ark.test` (password `password`). Do not use those accounts in production.

## After install

`/setup` locks. There is no browser reopen.

Then in **Settings**:

- Shop identity, financial rules, workflow, documents, staff, printing
- **ARK Platform** — connect if you want email, texting, card capture, or hosted voice
- Customer messaging defaults (snippets, review URL) — sending still needs Platform
- Dragon Memory — only relevant if a model provider exists; stock Core has none

Record external payments on the repair order. Do not paste Twilio, Square, Postmark, or OpenAI tokens into Core.

## Architecture (short)

- One database per Core install — not shared-database `shop_id` tenancy
- Workstations are desks in a shop, not tenants
- Financial totals stay server-side
- Maintainer notes live under `docs/engineering/` and are not the product manual

See [`docs/PRODUCT_BOUNDARY.md`](docs/PRODUCT_BOUNDARY.md) and [`docs/README.md`](docs/README.md).

## License

**Copyright (C) 2026 Edward Soares Jr.**

GNU Affero General Public License v3.0 only (`AGPL-3.0-only`). See `LICENSE` and `NOTICE`.

Naming: `TRADEMARKS.md`. Security reports: `SECURITY.md`. Contributions: `CONTRIBUTING.md`.

You may modify and fork ARK under the AGPL. Modified distributions should not be presented as the official ARK distribution without permission.

This licensing information is not legal advice.

## Status

https://github.com/EdwardSoaresJr/ark-core
