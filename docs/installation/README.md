# Installing ARK

ARK is self-hosted web software. The normal path:

1. Deploy ARK to a web server (or start the PHP/Laravel stack locally)
2. Visit the ARK URL in a browser
3. Complete the **first-run setup wizard** at `/setup`
4. Enter ARK with the administrator you created

You should **not** need to hand-edit a giant `.env` for a typical install.

## Prerequisites

- PHP 8.3+ with extensions required by Composer (`pdo_mysql`, `mbstring`, `openssl`, …)
- MySQL 8 (empty database)
- Writable `storage/` and `bootstrap/cache/`
- Composer dependencies installed (`composer install`)

Redis is part of the Docker Compose runtime (cache, sessions, Horizon queues, Reverb support). Native LAMP/LEMP installs can start thinner for first-run, then add Redis before enabling realtime telephony and background jobs.

## Bootstrap vs application configuration

| Layer | Examples | Where it lives |
| --- | --- | --- |
| Bootstrap | `APP_KEY`, `APP_URL`, `DB_*` | Environment / `.env` (allowlisted writer only) |
| Application | shop name, timezone, phone | `ShopSettings` (database) |
| Integrations | Mail, telephony, labor guides | Optional — Settings after install |

## Two deployment modes

**Writable:** ARK can update `.env` during setup.

**Immutable (Docker/K8s/platform):** Compose/Coolify inject `DB_*` (and usually `APP_URL`). Docker Compose generates `APP_KEY`, the application database password, the MySQL root password, and Reverb secrets on first boot when they are not already stored. The wizard verifies the runtime database and continues.

## After install

- `/setup` is **locked**. There is no `?force=` reopen.
- Configure Dragon, telephony, and mail under **Settings** when ready. Record external payments on the repair order; managed processors belong to ARK Platform Payments.
- Licensed labor-guide data is never bundled. Import only what you are licensed to use.

## Operator commands

```bash
php artisan ark:install-status
php artisan ark:install-recover --force   # clears interrupted IN_PROGRESS only — never unlocks INSTALLED
```

## Cloud VPS beginner guide

Step-by-step for a small Ubuntu cloud server, Docker, HTTPS (Caddy), and `/setup`:

→ **[vultr.md](./vultr.md)**

**1 GB RAM** is the supported starter/minimum for a small shop (use swap). **2 GB RAM** is recommended when you want extra headroom during updates, imports, photos, and heavier use.

## Docker Compose (recommended)

Self-host stack — same runtime shape production uses:

| Service | Role |
| --- | --- |
| `mysql` | Application database (volume `ark_mysql`) |
| `redis` | Cache and optional session/queue transport (volume `ark_redis` — **ephemeral**, not shop truth) |
| `app` | Production Dockerfile: nginx, PHP-FPM, **Horizon**, **Reverb**, **scheduler** (volume `ark_storage`) |

Durable state boundary (backup/restore): **`ark_mysql` + `ark_secrets` + `ark_storage`**. See **[portable-state.md](./portable-state.md)**.

```bash
docker compose up -d --build
```

Then open **http://localhost:8088/setup**.

The Database step should show **Connected** for a normal Compose install. You do not type Docker-internal MySQL credentials.

First boot generates unique database and realtime secrets onto a dedicated volume. Recreating containers keeps those secrets. `docker compose down -v` is a new installation and generates new secrets.

Compose uses Redis for cache, sessions, Horizon, and Reverb. Advanced PHP installs can start thinner, then add Redis before enabling realtime telephony and background jobs.

## Advanced installation

Native PHP / Apache / Nginx (LAMP or LEMP), manual queue workers, and the reduced `docker/selfhost/Dockerfile` Apache image are documented for operators who know why they want them. They are **not** the default path. Prefer Compose unless you are intentionally running a custom stack.

See [TROUBLESHOOTING.md](./TROUBLESHOOTING.md).
