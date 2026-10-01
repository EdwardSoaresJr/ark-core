<?php

namespace App\Ark\Platform\Website;

use Illuminate\Support\ServiceProvider;

final class PlatformWebsiteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Website records and signed management API live in Core.
        // The editor UI belongs in Platform, not this repository.
    }
}
