---
title: Composer packages
description: How a Laravel package can extend ARK Core without a plugin manager.
---

# Composer packages

<!-- source: composer.json -->

Core's `composer.json` does not list a package path repository or a plugin installer. You add a package the way any Laravel app does: Composer installs it, and Laravel discovers its service provider.

## Minimum package

A package needs:

- `composer.json` with `extra.laravel.providers` set to your provider class
- A service provider whose `register()` and `boot()` use only the mechanisms in [Extension mechanisms](/extending/mechanisms)
- A Pest or PHPUnit test that boots the Core application and asserts your binding or route exists

Require `laravel/framework` with a constraint compatible with Core (`^13.8` at the time of this writing). Do not vendor a second copy of Core.

Install it from the shop checkout:

```bash
composer require your-vendor/your-ark-package
```

If the package is path-repository local development:

```json
{
  "repositories": [
    { "type": "path", "url": "../your-ark-package" }
  ]
}
```

That `repositories` block is a local composer change. It is still a modification of the shop's `composer.json`. Track it deliberately.

## What the package may do

- Listen for `ConversationMessageReceived`
- Register an Artisan command
- Load its own migrations and views
- Bind `PdfRenderer` or another interface Core already type-hints
- Add routes under a prefix you own

## What the package may not assume

- A `plugins` table, enable flag, or admin screen
- A stable database schema for tables you did not create
- That `AppServiceProvider` will call your class
- That ARK Shop credentials exist in the environment

A worked skeleton is [A Composer package](/tutorials/composer-package).

## Shipping it

You can sell services around Core. The AGPL covers the Core program you distribute or run as a network service for users. Your package's license is your decision until it is a derivative work of Core. Read [License](/contributing/license) and `LICENSE`. This page is not legal advice.

Do not name the package in a way that implies it is an official ARK distribution. `TRADEMARKS.md` is the project policy.
