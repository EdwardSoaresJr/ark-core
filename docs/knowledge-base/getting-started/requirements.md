---
title: Requirements
description: PHP, database, Node, Redis, and hardware expectations for ARK Core.
---

# Requirements

<!-- source: composer.json -->

ARK Core is a Laravel application. The supported language floor for the `v1.0.0-rc.1` release train is **PHP 8.4.1** (Composer lock / Symfony 8.1). The container image used by Docker Compose is **PHP 8.4**.

| Requirement | Docker Compose | PHP on the host |
| --- | --- | --- |
| PHP | Inside the image (`php:8.4-fpm-bookworm`) | **8.4.1 or newer**, matching `composer.json` (`"php": "^8.4.1"`) |
| Composer | Image build installs dependencies | Required for source installs; **not** required when deploying a Release ZIP |
| Node.js and npm | Image build compiles Vite assets | Required to compile assets from source (`npm run build`); **not** required for Release ZIP hosts |
| Database | MySQL 8.4 service in Compose | MySQL 8, empty database |
| Redis | Required. Cache, sessions, Horizon queues, Reverb | Not required for a thin first boot or shared hosting. Add it before realtime features and Horizon workers |
| Browser | Used for `/setup` | Used for `/setup` |

<!-- source: Dockerfile -->

PHP extensions must satisfy Composer. A host install needs at least `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`, and `zip`. The Composer platform check is the authority if a package demands another extension. `/setup` fails closed when PHP is older than 8.4.1.

PDF rendering prefers Chromium when Node/Chrome are available. Constrained and shared-host installs use the native PHP PDF engine (mPDF) without Chromium. See `PDF_CHROME_PATH` in [Environment and services](/getting-started/configuration).

## Tests are not the application database

Automated tests use isolated SQLite in memory through Pest. They do not use the shop MySQL database. Do not point `DB_*` at a shop database and expect the test runner to share it. `phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`.

<!-- source: phpunit.xml -->

## Memory

The existing operator notes treat **1 GB RAM** as the starter size for a small shop running the full Compose stack, with swap, and **2 GB** as the size with room for updates, imports, and photos. Those notes are about the Compose stack (MySQL, Redis, PHP-FPM, Horizon, Reverb, and the scheduler together), not a brochure site.

## What you do not need

- An ARK Shop or Landlord account
- Docker, if you intentionally run the PHP path
- A licensed labor-guide dataset
- A card processor, SMS provider, or phone carrier

Next: [Install with Docker Compose](/getting-started/install-docker) or [Install with PHP](/getting-started/install-php).
