<?php

use App\Ark\Runtime\Booking\BookingSurface;
use App\Ark\Runtime\Booking\BookingSurfaceRedirectController;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Support\Facades\Route;

SurfaceRouting::publicRoutes(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('/', function () {
            return redirect()->route('login');
        });

        // Safety bridge only when BOOKING_SURFACE_BASE_URL is set.
        // No route name - public.book must stay absent (Website boundary).
        if (BookingSurface::isConfigured()) {
            Route::get('/book', BookingSurfaceRedirectController::class);
        }
    });
});
