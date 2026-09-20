<?php

use App\Ark\Platform\CoreApplicationOrigin;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Support\Env;

beforeEach(function (): void {
    $vars = [
        'SURFACE_DOMAINS_ENABLED' => 'true',
        'APP_DOMAIN' => 'app.lugsnplugs.test',
        'PORTAL_DOMAIN' => 'portal.lugsnplugs.test',
        'PUBLIC_DOMAIN' => 'lugsnplugs.test',
        'LEARN_DOMAIN' => 'learn.lugsnplugs.test',
        'APP_URL' => 'https://app.lugsnplugs.test',
        'SHOP_BASE_URL' => 'https://app.lugsnplugs.test',
        'BOOKSTACK_CUTOVER' => 'false',
    ];

    foreach ($vars as $key => $value) {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key.'='.$value);
        Env::getRepository()->set($key, $value);
    }

    $this->refreshApplication();

    config(['bookstack.cutover' => false]);
});

test('core origin ignores the public website domain', function (): void {
    expect(CoreApplicationOrigin::host())->toBe(SurfaceRouting::appHost())
        ->and(CoreApplicationOrigin::host())->not->toBe(SurfaceRouting::publicHost())
        ->and(SurfaceRouting::publicHost())->toBe('lugsnplugs.test');
});

test('estimate pay inspection repair and go urls use core origin not the website', function (): void {
    $token = str_repeat('a', 64);
    $core = 'https://'.CoreApplicationOrigin::host().'/';
    $website = 'https://'.SurfaceRouting::publicHost().'/';

    expect(route('portal.estimates.show', ['token' => $token]))->toStartWith($core)
        ->and(route('portal.invoice-pay.show', ['token' => $token]))->toStartWith($core)
        ->and(route('portal.inspections.show', ['token' => $token]))->toStartWith($core)
        ->and(route('portal.repair.show', ['code' => 'abcdefghij']))->toStartWith($core)
        ->and(route('portal.short.redirect', ['code' => 'abcdefghij']))->toStartWith($core.'go/')
        ->and(route('portal.access'))->toStartWith($website);
});

test('operations host go path is not redirected to the website', function (): void {
    $this->get('http://'.CoreApplicationOrigin::host().'/go/notarealcode')
        ->assertNotFound();
});

test('website host still serves go path for already issued links', function (): void {
    $this->get('http://'.SurfaceRouting::publicHost().'/go/notarealcode')
        ->assertNotFound();
});
