---
title: Updates, backup, and restore
description: How to update ARK Core and which state must move together.
---

# Updates, backup, and restore

<!-- source: docs/installation/portable-state.md -->

## Update

There is no separate auto-updater in Core. An update is a new checkout of this repository, a rebuilt app, and migrations.

Compose, after you have a backup:

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

The `exec` service name follows `docker-compose.yml`. If your checkout names the PHP service differently, use that name. Run `docker compose ps` rather than guessing.

Host PHP:

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

Read the migration names in the commits you pulled before `--force` on a shop you care about. Migrations in this repository are forward schema changes. They are not a generic undo button.

`php artisan down` is available when you want a maintenance window. Bring the app back with `php artisan up`.

## What must be backed up together

| Piece | Compose | Host PHP |
| --- | --- | --- |
| Database | Volume `ark_mysql` | `mysqldump` of the Core database, or the data directory |
| Files | Volume `ark_storage` | The `storage/` tree, especially `storage/app` |
| Secrets | Volume `ark_secrets` | `APP_KEY`, `DB_*`, and Reverb keys |

Restore all three onto the same Core version you dumped, or onto a newer version only after you have run that version's migrations on a copy. Redis is cache and queue transport. It is not the shop record. You can discard it.

`docker compose down` keeps volumes. `docker compose down -v` deletes them and creates a new installation.

If Compose reports that install secrets are missing while a MySQL volume already exists, Core will not invent a new password for that data. Restore the secrets volume, or accept that `down -v` is a new shop.

## Quiesce

For a strict snapshot, stop the app service or enable maintenance mode before dumping MySQL and copying `storage/`. A dump taken while invoices and photo uploads are in flight can be internally inconsistent.

## What is not in Core

ARK Backup, as a managed product, is not part of this repository. The boundary above is the contract a backup tool has to preserve. There is no supported one-command move from a self-hosted shop into ARK Shop.

Record the git commit you are running next to the backup. The image or checkout and the SQL dump are a pair.
