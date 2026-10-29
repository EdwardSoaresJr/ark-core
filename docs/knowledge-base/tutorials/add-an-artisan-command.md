---
title: Add an Artisan command
description: Add a console command to an ARK Core checkout.
---

# Add an Artisan command

<!-- source: app/Console/Commands/ArkInstallStatusCommand.php -->

Laravel discovers command classes under `app/Console/Commands`. Core already keeps install, station-token, and maintenance commands there. A new command in that directory is available as `php artisan your:name` after the autoloader sees it. You do not register it in `routes/console.php`. That file is for closures. The stock closure there is the framework `inspire` example.

This is a change inside the Core checkout. It will show up in git. A package that should survive pulls belongs in [A Composer package](/tutorials/composer-package) instead.

## Command

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ShopNoteCommand extends Command
{
    protected $signature = 'shop:note {text : Short operator note}';

    protected $description = 'Print an operator note. Does not write shop data.';

    public function handle(): int
    {
        $this->line((string) $this->argument('text'));

        return self::SUCCESS;
    }
}
```

```bash
php artisan shop:note "Compressor service is next week"
```

Keep commands that mutate shop data behind an explicit option and a test. `ark:install-recover` is the pattern for a command that refuses to do more than it says: read its signature before copying its style.

## Scheduling it

Core's schedule closure is in `bootstrap/app.php`. Adding your command there is a Core edit. From a package, call `Schedule::command()` in the provider, as described in [Extension mechanisms](/extending/mechanisms).

## Update risk

A command in `app/Console/Commands` merges cleanly until someone else adds the same filename. It does not hook the repair-order screen. It cannot see a request. It runs as the server user, with the server's `.env`.
