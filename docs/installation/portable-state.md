# Portable installation state

ARK Core treats shop durability as a **defined boundary**. A complete installation can be backed up on one Docker host and restored on another without losing operational data, uploaded media, or the cryptographic identity required to use that data.

This is the public contract for self-host and future managed-host migrations.

## Durable (must move together)

| Layer | Docker Compose volume | Contents |
| --- | --- | --- |
| **Database** | `ark_mysql` | Customers, vehicles, repair orders, estimates, inspections, settings, sessions when `SESSION_DRIVER=database`, queue rows when `QUEUE_CONNECTION=database`, and all other MySQL operational truth |
| **Persistent files** | `ark_storage` | `storage/app/public` media, `storage/app/install/` (installation UUID, install checkpoint, optional `.env` copy), and other shop files written under `storage/` |
| **Installation secrets** | `ark_secrets` | Per-install `APP_KEY`, database passwords, Reverb credentials — generated once on first boot and never rotated silently |

On Compose/Vultr hosts, `infra/coolify/entrypoint.sh` loads `ark_secrets/install.env` and rewrites `storage/app/install/dotenv` (and `/app/.env`) before php-fpm starts. That keeps Laravel readable after an `app` container recreate without manual secret reconstruction.

Restore **all three** on the new host. MySQL without matching `APP_KEY` / secrets breaks decryption and authentication. Secrets without the matching MySQL data directory are useless.

On native (non-Docker) installs the same boundary applies:

- MySQL database dump or data directory
- Writable `storage/` (especially `storage/app/`)
- Bootstrap secrets: `APP_KEY`, `DB_*`, and any persisted Reverb keys in environment or install storage

## Ephemeral (safe to discard)

These are runtime convenience. They rebuild after restore:

| Layer | Docker Compose volume | Why ephemeral |
| --- | --- | --- |
| **Redis** | `ark_redis` | Cache, optional session/queue transport — not authoritative shop truth |
| **Containers / images** | — | Replace with `docker compose up -d --build` |
| **Compiled views** | inside `ark_storage` framework subtree | Regenerated on demand |
| **Logs** | stderr / log files | Operational telemetry, not shop state |
| **`bootstrap/cache`** | — | Framework cache; regenerates |

`docker compose down` keeps durable volumes. `docker compose down -v` **destroys** durable state and starts a new installation with new secrets.

## Backup (operator procedure today)

1. Quiesce writes if you need a strict point-in-time snapshot (stop the `app` service or put the shop in maintenance).
2. Archive the durable volumes or their contents:
   - `ark_mysql`
   - `ark_secrets`
   - `ark_storage`
3. Record the ARK version / git SHA you are running (image tag or `GIT_SHA` build arg).

Restore onto a fresh host:

1. Clone this repository and start Compose with the **same** durable volumes attached.
2. Do **not** run `install-bootstrap` against an empty secrets file when MySQL data already exists — bootstrap detects existing MySQL without secrets and fails closed.
3. Open the shop URL and verify customers, repair orders, and media.

Automated backup/restore tooling and ARK Platform “Move to managed hosting” are future product paths. The boundary above is what those tools must preserve.

## Installation identity

`InstallationIdentity` writes a durable UUID to `storage/app/install/installation_uuid`. It identifies this Box to ARK Platform pairing APIs. It is **not** an authentication secret, but it should move with `ark_storage` so Cloud pairings survive migration.

## Verification target (stranger box)

The intended proof for this contract:

1. Fresh stranger install → synthetic shop (customers, vehicle, RO, inspection, photos)
2. Backup durable state
3. Destroy standalone deployment
4. Fresh deployment on another host (or Coolify-managed)
5. Restore durable state
6. Verify same shop, data, and media

That exercise validates portability before managed-host automation ships.
