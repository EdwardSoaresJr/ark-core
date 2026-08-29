<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the real Square SDK client when this optional package is installed.
 * Core AppServiceProvider binds FakeSquarePaymentsClient by default.
 */
final class SquarePaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SquarePaymentsClient::class, function ($app): SquarePaymentsClient {
            if ($app->environment('testing')) {
                return $app->make(FakeSquarePaymentsClient::class);
            }

            if (! $app->make(SquareConfiguration::class)->configured()) {
                return $app->make(FakeSquarePaymentsClient::class);
            }

            return $app->make(SquareApiPaymentsClient::class);
        });
    }
}
