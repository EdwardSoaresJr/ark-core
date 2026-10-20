---
title: Install with Docker Compose
description: The supported container path for trying and self-hosting ARK Core.
---

# Install with Docker Compose

<!-- source: docker-compose.yml -->

Compose is the normal way to try Core and the stack shape the project documents for self-hosting. Docker is not required. The [PHP install](/getting-started/install-php) is a supported path when you are deliberately running your own web server.

This builds from the local tree. It is not a pull of a private image.

```bash
git clone https://github.com/EdwardSoaresJr/ark-core.git
cd ark-core
docker compose up -d --build
```

Then open:

```text
http://localhost:8088/setup
```

Host port **8088** maps to container port 80. Port 8080 is avoided because other local tools often bind it.

## What comes up

| Service | Role |
| --- | --- |
| `mysql` | Application database. Volume `ark_mysql`. MySQL 8.4. |
| `redis` | Cache, sessions, Horizon, Reverb support. Volume `ark_redis`. Ephemeral. Not shop truth. |
| `app` | nginx, PHP-FPM, Horizon, Reverb, and the scheduler. Volume `ark_storage`. |

First boot generates `APP_KEY`, the application database password, the MySQL root password, and Reverb secrets when they are not already stored. Recreating containers keeps those secrets. `docker compose down -v` deletes volumes and starts a new installation with new secrets.

The setup wizard should show the database as connected. You should not type Docker-internal MySQL credentials.

## Durable state

Back up these together:

| Volume | What it holds |
| --- | --- |
| `ark_mysql` | Shop data |
| `ark_secrets` | `APP_KEY`, database passwords, Reverb credentials |
| `ark_storage` | Uploaded files and installation files under `storage/` |

MySQL without the matching `APP_KEY` cannot decrypt data that was encrypted with that key. Details are on [Updates, backup, and restore](/getting-started/updates-and-backup).

## After the stack is up

Finish [first-run setup](/getting-started/first-run) in the browser. `/setup` locks when installation completes. There is no query-string override to reopen it.

Compose sets `APP_URL` to `http://localhost:8088`. A public shop needs HTTPS and a real `APP_URL`. See [Production](/getting-started/production).
