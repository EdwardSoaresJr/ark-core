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

Redis is recommended for production cache/queues but **not** required for first-run (installer uses file/database drivers until you harden the stack).

## Bootstrap vs application configuration

| Layer | Examples | Where it lives |
| --- | --- | --- |
| Bootstrap | `APP_KEY`, `APP_URL`, `DB_*` | Environment / `.env` (allowlisted writer only) |
| Application | shop name, timezone, phone | `ShopSettings` (database) |
| Integrations | Square, Twilio, OpenAI | Optional — Settings after install |

## Two deployment modes

**Writable:** ARK can update `.env` during setup.

**Immutable (Docker/K8s/platform):** inject `APP_KEY` and `DB_*` via the host. The wizard validates and continues without fighting the platform.

## After install

- `/setup` is **locked**. There is no `?force=` reopen.
- Configure Dragon, Square, telephony, and mail under **Settings** when ready.
- Licensed labor-guide data is never bundled. Import only what you are licensed to use.

## Operator commands

```bash
php artisan ark:install-status
php artisan ark:install-recover --force   # clears interrupted IN_PROGRESS only — never unlocks INSTALLED
```

## Docker Compose (stranger path)

Smallest self-host stack (MySQL + ARK):

```bash
docker compose up -d --build
```

Then open **http://localhost:8088** — you should land on `/setup`.

Wizard database fields on the Compose network:

| Field | Value |
| --- | --- |
| Host | `mysql` |
| Port | `3306` |
| Database | `ark` |
| User | `ark` |
| Password | `ark` |

This is **not** the production Coolify image. It is the open-source first-run path: bring the stack up, finish installation in the browser, recreate containers, and keep working.

See [TROUBLESHOOTING.md](./TROUBLESHOOTING.md).
