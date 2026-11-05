<?php

namespace App\Providers;

use App\Ark\Operations\Booking\Http\PublicBookingController;
use App\Ark\Runtime\Booking\BookingSurfaceGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

final class BookingSurfaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The booking form posts from inside other sites' iframes, where the browser sends no
        // session cookie. It carries an encrypted render stamp instead (PublicBookingController).
        VerifyCsrfToken::except(['book']);

        // Code requests, code checks and sends from one address. The refusal is framed like the form,
        // so a popup on another site shows it instead of a blocked frame.
        RateLimiter::for(PublicBookingController::SUBMIT_LIMITER, fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) $request->ip())
            ->response(fn (Request $request, array $headers): Response => app(PublicBookingController::class)->tooManyTries($request, $headers)));

        if ($this->app->runningUnitTests()) {
            return;
        }

        BookingSurfaceGuard::assertCutoverSafe();
    }
}
