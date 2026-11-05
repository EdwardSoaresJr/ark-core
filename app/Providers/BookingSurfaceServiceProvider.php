<?php

namespace App\Providers;

use App\Ark\Runtime\Booking\BookingSurfaceGuard;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\ServiceProvider;

final class BookingSurfaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The booking form posts from inside other sites' iframes, where the browser sends no
        // session cookie. It carries an encrypted render stamp instead (PublicBookingController).
        VerifyCsrfToken::except(['book']);

        if ($this->app->runningUnitTests()) {
            return;
        }

        BookingSurfaceGuard::assertCutoverSafe();
    }
}
