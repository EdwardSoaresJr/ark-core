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

Redis is part of the **canonical** Docker Compose runtime (cache, sessions, Horizon queues, Reverb support). Native LAMP/LEMP installs can start thinner for first-run, then add Redis before enabling realtime telephony and background jobs.

## Bootstrap vs application configuration

| Layer | Examples | Where it lives |
| --- | --- | --- |
| Bootstrap | `APP_KEY`, `APP_URL`, `DB_*` | Environment / `.env` (allowlisted writer only) |
| Application | shop name, timezone, phone | `ShopSettings` (database) |
| Integrations | Square, Twilio, OpenAI | Optional — Settings after install |

## Two deployment modes

**Writable:** ARK can update `.env` during setup.

**Immutable (Docker/K8s/platform):** Compose/Coolify inject `DB_*` (and usually `APP_URL`). Canonical Docker **bootstraps `APP_KEY`** onto durable install storage when the host does not inject one. The wizard validates and continues without fighting the platform.

## After install

- `/setup` is **locked**. There is no `?force=` reopen.
- Configure Dragon, Square, telephony, and mail under **Settings** when ready.
- Licensed labor-guide data is never bundled. Import only what you are licensed to use.

## Operator commands

```bash
php artisan ark:install-status
php artisan ark:install-recover --force   # clears interrupted IN_PROGRESS only — never unlocks INSTALLED
```

## Vultr (cloud VPS) beginner guide

Step-by-step for a small Vultr Ubuntu server, Docker, HTTPS (Caddy), and `/setup`:

→ **[vultr.md](./vultr.md)**

Uses a **2 GB (~$10/mo)** plan until the **1 GB (~$5)** tier is stranger-certified. We will not advertise $5 until that pass exists.

## Vultr (cloud VPS) beginner guide

Step-by-step for a small Vultr Ubuntu server with Docker and HTTPS:

→ **[vultr.md](./vultr.md)**

Uses a **2 GB (~$10/mo)** plan until the **1 GB (~$5)** tier is stranger-certified. We will not advertise $5 until that pass exists.

## Docker Compose (recommended)

Canonical self-host stack — same runtime architecture production uses:

| Service | Role |
| --- | --- |
| `mysql` | Application database (volume `ark_mysql`) |
| `redis` | Cache, sessions, Horizon queues (volume `ark_redis`) |
| `app` | Production Dockerfile: nginx, PHP-FPM, **Horizon**, **Reverb**, **scheduler** (volume `ark_storage`) |

```bash
docker compose up -d --build
```

Then open **http://localhost:8088/setup**.

The Database step is pre-filled from Compose runtime settings. Leave the password blank and click **Test Connection** — you should not need to type Docker service names or credentials by hand.

Compose defaults include `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, and `BROADCAST_CONNECTION=reverb`, with Reverb listening inside the app container (same supervisord model as Coolify). Local Reverb app id/key/secret are development defaults in `docker-compose.yml` — replace them for any internet-facing shop.

Recreate the app container after install; MySQL, Redis, and `ark_storage` keep shop state.

## Advanced installation

Native PHP / Apache / Nginx (LAMP or LEMP), manual queue workers, and the reduced `docker/selfhost/Dockerfile` Apache image are documented for operators who know why they want them. They are **not** the default path. Prefer Compose unless you are intentionally running a custom stack.

See [TROUBLESHOOTING.md](./TROUBLESHOOTING.md).
