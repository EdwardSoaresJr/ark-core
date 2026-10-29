---
title: A Composer package
description: A minimal Laravel package that listens to ARK Core without forking the shop.
---

# A Composer package

<!-- source: bootstrap/providers.php -->

This package logs when `ConversationMessageReceived` fires. It does not modify Core files. It does not add a plugin row.

## composer.json

```json
{
  "name": "acme/shop-notes",
  "description": "Example listener for ARK Core. Not an official ARK package.",
  "type": "library",
  "license": "MIT",
  "require": {
    "php": "^8.3",
    "illuminate/support": "^13.0"
  },
  "autoload": {
    "psr-4": {
      "Acme\\ShopNotes\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "Acme\\ShopNotes\\ShopNotesServiceProvider"
      ]
    }
  }
}
```

The MIT example applies to this tiny package, not to Core. Core remains AGPL-3.0-only. Do not present the package as official ARK.

## Provider

```php
<?php

namespace Acme\ShopNotes;

use App\Ark\Operations\Messaging\Events\ConversationMessageReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ShopNotesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ConversationMessageReceived::class, NoteInboundMessage::class);
    }
}
```

Use the listener class from [Listen for a domain event](/tutorials/listen-for-a-domain-event), moved into `Acme\ShopNotes`.

## Install

From the Core checkout, point Composer at the package and require it. Deployment must include that package every time you deploy Core. A package that is present on your laptop and absent on the server is not installed.

## Prove it

`Event::assertListening()` exists on Laravel's event fake, and `Event::fake()` replaces the dispatcher. Do not use that pair to prove a provider registered a listener. Read the real dispatcher instead:

```php
public function test_listener_is_registered(): void
{
    $listeners = Event::getRawListeners()[ConversationMessageReceived::class] ?? [];

    $this->assertTrue(
        collect($listeners)->contains(
            fn ($listener): bool => $listener === NoteInboundMessage::class
                || (is_string($listener) && str_contains($listener, NoteInboundMessage::class)),
        ),
    );
}
```

Run it from a Core checkout that has the package installed:

```bash
composer test:serial -- --filter=listener_is_registered
```

If the assertion fails, the provider was not discovered. Check `php artisan package:discover` and `bootstrap/cache/packages.php`.

## What this package is not

It does not add a dashboard widget, a settings screen, or a webhook URL. Those are [proposed](/tutorials/proposed).
