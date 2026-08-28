# ARK

**Shop management software for independent repair shops.**

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

- Production Coolify / LugsNPlugs deploy runbooks
- Credential backups or live secrets
- Real-Time Labor Guide (RTE) or other licensed automotive datasets
- Private Dragon knowledge imports / ARKademy dumps
- Third-party training course republication

**Open the engine. Bring your own fuel.**

## Requirements

- PHP 8.3+ (match `composer.json`)
- Composer
- Node.js + npm (Vite assets)
- MySQL 8 (application database)
- Redis (queues / cache; required for full production shape)

Automated tests use an isolated SQLite file (`database/testing.sqlite`) via PHPUnit — not your MySQL database.

## Quick start (local)

```bash
cp .env.example .env
composer install
php artisan key:generate

# Configure MySQL in .env, then:
php artisan migrate
php artisan db:seed

npm install
npm run build

php artisan serve
```

Default seeded staff (local only):

| Email | Password | Role |
| --- | --- | --- |
| `admin@ark.test` | `password` | Admin |
| `advisor@ark.test` | `password` | Advisor |
| `tech@ark.test` | `password` | Technician |

Change these before any shared environment.

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

**Not decided in this staging tree.** Do not treat this snapshot as an official
public release until a `LICENSE` file is added after legal review.

## Status

**Staging / pre-publish.** Inspect `OPEN_SOURCE_STAGING_MANIFEST.md` before any
GitHub publication. Do not assume this tree is redistribution-complete.
