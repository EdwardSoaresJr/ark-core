---
title: First-run setup
description: What the /setup wizard does and what it refuses to do.
---

# First-run setup

<!-- source: routes/install.php -->

The installer is a web wizard. The route file is `routes/install.php`. It is wrapped in middleware that allows setup only while installation is incomplete and that keeps the installer on its own runtime (file sessions and cache) before MySQL is ready.

## What you do

1. Open the application URL.
2. If Core is not installed, you are sent to `/setup`.
3. On a Compose install, confirm the runtime database. You should not enter container credentials.
4. On a PHP install, provide the empty MySQL database if it is not already in the environment.
5. Create the administrator account. That account is the first staff user. It is not `admin@ark.test` unless you separately ran a development seeder.

Shop name, timezone, and similar shop facts are stored as shop settings in the database after install. They are not bootstrap environment variables.

## What the installer will not do

- It will not migrate a database that already contains an unrelated schema. Point it at an empty database.
- It will not log the database password.
- It will not reopen after a completed install. There is no `?force=` switch.
- `php artisan ark:install-recover --force` clears an interrupted `IN_PROGRESS` install only. It does not unlock an install that finished.

Check status with:

```bash
php artisan ark:install-status
```

<!-- source: app/Console/Commands/ArkInstallStatusCommand.php -->

## Writable and immutable environments

| Mode | Who writes `.env` |
| --- | --- |
| Writable | The wizard may update allowlisted bootstrap keys (`APP_KEY`, `APP_URL`, `DB_*`) on a host where the environment file is writable. |
| Immutable | Docker, Kubernetes, or a platform injects `DB_*` and usually `APP_URL`. Compose generates `APP_KEY` and database secrets on first boot. The wizard verifies the database and continues. |

After install, configure mail, Dragon, printing, and labor-guide links under Settings. Recording a payment the shop already took is a ledger entry on the repair order. Charging a card inside ARK is not part of this wizard.
