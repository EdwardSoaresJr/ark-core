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

test('public.book is a core route', function (): void {
    expect(Route::has('public.book'))->toBeTrue();
});

test('book does not redirect to an external booking origin', function (): void {
    config(['booking_surface.base_url' => 'https://lugsnplugs.com']);

    $this->get('/book')
        ->assertNotFound()
        ->assertSee('This website is not published.');
});

test('guard passes when protected marketing hosts are not claimed', function (): void {
    config(['booking_surface.enforce' => true]);

    BookingSurfaceGuard::assertCutoverSafe();

    expect(BookingSurface::claimedProtectedHosts())->toBe([]);
});

test('guard allows a protected host without an external booking url', function (): void {
    config([
        'booking_surface.enforce' => true,
        'booking_surface.base_url' => '',
        'surfaces.public' => 'lugsnplugs.com',
    ]);

    BookingSurfaceGuard::assertCutoverSafe();

    expect(BookingSurface::isConfigured())->toBeFalse();
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
