---
title: Local development
description: How to work on ARK Core locally, including tests and assets.
---

# Local development

<!-- source: CONTRIBUTING.md -->

Use Compose from the repository root, or the PHP path plus `npm run build`. The shop UI is a Vite build. Without that build, Blade pages that load the compiled bundle have nothing to load.

## Day to day

Host PHP and asset rebuilds:

```bash
composer install
npm install
npm run dev
```

`npm run dev` starts Vite only. `composer dev` starts PHP, a queue listener, Reverb, log tailing, and Vite together. That is a development convenience, not the production process model.

Create `.env` from `.env.example` if you are not using Compose. Generate `APP_KEY`. Point `DB_*` at an empty local MySQL database, then use `/setup`.

## Tests

```bash
composer test:parallel
composer test:serial
```

`composer test:parallel` is the default check for behavior changes. It runs Pest with several processes. `composer test:serial` is the single-process diagnostic. Documentation-only changes do not require the full suite.

Pest is the test runner (`pestphp/pest`). Feature tests live in `tests/Feature`. Unit tests live in `tests/Unit`. The test environment forces SQLite memory, the array cache, the sync queue, and `DRAGON_PROVIDER=fake`.

Style for PHP changes is Laravel Pint (`vendor/bin/pint`). Match the surrounding code.

## What not to commit

- `.env`, credentials, shop data, or licensed labor-guide CSVs
- Provider tokens or model-provider secrets
- Firebase service-account JSON, `google-services.json`, or Apple Google service plists

## Seeder accounts

Development seeders may create `admin@ark.test`. That mailbox is an example. It is not created by `/setup`, and it is not a credential to publish in a client application.

More contributor rules are on [Contribute](/contributing/).
