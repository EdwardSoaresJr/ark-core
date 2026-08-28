<?php

use App\Ark\Runtime\Surfaces\SurfaceRouting;

beforeEach(function (): void {
    $_ENV['SURFACE_DOMAINS_ENABLED'] = 'true';
    $_ENV['APP_DOMAIN'] = 'app.demo-auto.test';
    $_ENV['PORTAL_DOMAIN'] = 'portal.demo-auto.test';
    $_ENV['PUBLIC_DOMAIN'] = 'demo-auto.test';
    $_ENV['LEARN_DOMAIN'] = 'learn.demo-auto.test';
    $_ENV['APP_URL'] = 'https://app.demo-auto.test';
    $_ENV['BOOKSTACK_CUTOVER'] = 'false';

    putenv('SURFACE_DOMAINS_ENABLED=true');
    putenv('APP_DOMAIN=app.demo-auto.test');
    putenv('PORTAL_DOMAIN=portal.demo-auto.test');
    putenv('PUBLIC_DOMAIN=demo-auto.test');
    putenv('LEARN_DOMAIN=learn.demo-auto.test');
    putenv('APP_URL=https://app.demo-auto.test');
    putenv('BOOKSTACK_CUTOVER=false');

    $this->refreshApplication();

    config(['bookstack.cutover' => false]);
});

test('portal estimate links use the customer apex host when public surface is enabled', function (): void {
    $url = route('portal.estimates.show', ['token' => str_repeat('a', 64)]);

    expect($url)->toStartWith('https://demo-auto.test/');
});

test('staff login uses the app host when surface domains are enabled', function (): void {
    expect(route('login'))->toBe('https://app.demo-auto.test/app/login');
});

test('portal paths on the app host redirect to the customer apex host', function (): void {
    $this->get('http://app.demo-auto.test/portal/access')
        ->assertRedirect('https://demo-auto.test/portal/access');
});

test('legacy portal subdomain redirects to the customer apex host', function (): void {
    $this->get('http://portal.demo-auto.test/portal/access')
        ->assertRedirect('https://demo-auto.test/portal/access');
});

test('portal access is served on the customer apex host', function (): void {
    $this->get('http://demo-auto.test/portal/access')
        ->assertOk();
});

test('learn host redirects to staff learn on the app host', function (): void {
    $this->get('http://learn.demo-auto.test/')
        ->assertRedirect('https://app.demo-auto.test/app/learn');
});

test('app host root redirects guests to staff login', function (): void {
    $this->get('http://app.demo-auto.test/')
        ->assertRedirect(route('login'));
});

test('surface routing helper reports enabled state', function (): void {
    expect(SurfaceRouting::enabled())->toBeTrue()
        ->and(SurfaceRouting::portalOnPublicHost())->toBeTrue()
        ->and(SurfaceRouting::customerHost())->toBe('demo-auto.test');
});
