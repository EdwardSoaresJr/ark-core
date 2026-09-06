<?php

use App\Ark\Runtime\Booking\BookingSurface;
use App\Ark\Runtime\Booking\BookingSurfaceGuard;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config([
        'booking_surface.base_url' => '',
        'booking_surface.enforce' => false,
        'booking_surface.protected_hosts' => ['lugsnplugs.com', 'www.lugsnplugs.com'],
        'surfaces.public' => 'demo-auto.test',
        'surfaces.app' => 'app.demo-auto.test',
        'surfaces.portal' => 'portal.demo-auto.test',
        'app.url' => 'https://app.demo-auto.test',
    ]);
});

test('public.book route name stays unregistered', function (): void {
    expect(Route::has('public.book'))->toBeFalse();
});

test('book is absent when booking surface is not configured', function (): void {
    $this->get('/book')->assertNotFound();
});

test('book redirects to external booking surface when configured', function (): void {
    config(['booking_surface.base_url' => 'https://lugsnplugs.com']);

    // Route registration reads config at boot; re-register for this process.
    Route::middleware('web')->get('/book', \App\Ark\Runtime\Booking\BookingSurfaceRedirectController::class);

    $this->get('/book?apikey=test')
        ->assertRedirect('https://lugsnplugs.com/book?apikey=test');
});

test('guard passes when protected marketing hosts are not claimed', function (): void {
    config(['booking_surface.enforce' => true]);

    BookingSurfaceGuard::assertCutoverSafe();

    expect(BookingSurface::claimedProtectedHosts())->toBe([]);
});

test('guard fails when protected host is claimed without booking base url', function (): void {
    config([
        'booking_surface.enforce' => true,
        'booking_surface.base_url' => '',
        'surfaces.public' => 'lugsnplugs.com',
    ]);

    expect(fn () => BookingSurfaceGuard::assertCutoverSafe())
        ->toThrow(RuntimeException::class, 'BOOKING_SURFACE_BASE_URL');
});

test('guard passes when protected host is claimed with booking base url', function (): void {
    config([
        'booking_surface.enforce' => true,
        'booking_surface.base_url' => 'https://lugsnplugs.com',
        'surfaces.public' => 'lugsnplugs.com',
    ]);

    BookingSurfaceGuard::assertCutoverSafe();

    expect(BookingSurface::isConfigured())->toBeTrue();
});
