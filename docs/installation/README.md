# Installing ARK

ARK Core is self-hosted shop software. **Docker Compose is the recommended, verified path.**

1. Start the Compose stack
2. Open the ARK URL
3. Complete **first-run setup** at `/setup`
4. Sign in with the administrator you created

You should not hand-edit a large `.env` for a typical Compose install.

What the installer does **not** do: load demo tickets, enable email/SMS/card capture, or turn on Dragon.

## Prerequisites

**Compose (recommended):** Docker with Compose v2. The app image includes PHP, nginx, Horizon, Reverb, and the scheduler.

**Native PHP (advanced):**

- PHP 8.3+ with Composer extensions (`pdo_mysql`, `mbstring`, `openssl`, …)
- Node.js and npm (`npm install` and `npm run build` — assets are not committed)
- MySQL 8 (empty database)
- Writable `storage/` and `bootstrap/cache/`

Redis is required in Compose. Native installs can start with file drivers, then add Redis before relying on queues and realtime.

## Bootstrap vs shop configuration

| Layer | Examples | Where it lives |
| --- | --- | --- |
| Bootstrap | `APP_KEY`, `APP_URL`, `DB_*` | Environment / `.env` |
| Shop | name, timezone, phone | Settings (database) |
| Managed services | Email, SMS, card capture, hosted voice | ARK Platform after install |
| Licensed data | Labor-guide CSVs | Import yourself; never bundled |

## Two deployment modes

**Writable:** ARK can update `.env` during setup.

**Immutable (Docker):** Compose injects `DB_*` and usually `APP_URL`. First boot generates `APP_KEY`, database passwords, and Reverb secrets onto a volume when they are not already stored. The wizard verifies the runtime database and continues.

## After install

- `/setup` is **locked**. There is no `?force=` reopen.
- Configure shop identity, hours, financial rules, workflow, documents, staff, and printing under **Settings**.
- Connect **ARK Platform** only if you need customer email, texting, in-app card capture, or hosted voice.
- Record payments taken at the counter on the repair order. Managed card processors are not a Core Settings form.
- Stock Core has no working Dragon model provider. Dragon Memory in Settings does not enable an assistant.
- Licensed labor-guide data is never bundled. Import only what you are licensed to use (`ark:rte-import`).

## Operator commands

```bash
php artisan ark:install-status
php artisan ark:install-recover --force   # interrupted IN_PROGRESS only — never unlocks INSTALLED
```

## Cloud VPS beginner guide

Ubuntu, Docker, HTTPS (Caddy), `/setup`:

→ **[vultr.md](./vultr.md)**

**1 GB RAM** is the supported starter/minimum for a small shop (use swap). **2 GB RAM** is recommended when you want extra headroom.

## Docker Compose (recommended)

| Service | Role |
| --- | --- |
| `mysql` | Application database (volume `ark_mysql`) |
| `redis` | Cache and optional session/queue transport (volume `ark_redis` — **ephemeral**) |
| `app` | nginx, PHP-FPM, Horizon, Reverb, scheduler (volume `ark_storage`) |

Durable backup/restore: **`ark_mysql` + `ark_secrets` + `ark_storage`**. See **[portable-state.md](./portable-state.md)**.

```bash
docker compose up -d --build
```

Then open **http://localhost:8088/setup**.

The Database step should show **Connected**. You do not type Docker-internal MySQL credentials.

First boot writes unique secrets onto a volume. Recreating containers keeps them. `docker compose down -v` is a new installation.

## Native PHP

```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
```

Point `DB_*` at an empty MySQL database (or let `/setup` write them on a writable host), then `php artisan serve` and open `/setup`.

Optional development seed (not first-run): `php artisan db:seed` may create `admin@ark.test` / `password`. Production installs use the wizard admin only.

See [TROUBLESHOOTING.md](./TROUBLESHOOTING.md).

## Advanced

Native Apache/Nginx, manual queue workers, and `docker/selfhost/Dockerfile` are for operators who already know why they want them. Prefer Compose.

Product boundaries: [../PRODUCT_BOUNDARY.md](../PRODUCT_BOUNDARY.md).
