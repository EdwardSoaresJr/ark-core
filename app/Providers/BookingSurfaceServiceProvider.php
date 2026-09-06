<?php

namespace App\Providers;

use App\Ark\Runtime\Booking\BookingSurfaceGuard;
use Illuminate\Support\ServiceProvider;

final class BookingSurfaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        BookingSurfaceGuard::assertCutoverSafe();
    }
}
