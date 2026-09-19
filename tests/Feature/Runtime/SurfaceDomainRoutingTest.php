<?php

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

test('portal estimate links use the Core application origin when a public website domain is set', function (): void {
    $url = route('portal.estimates.show', ['token' => str_repeat('a', 64)]);

    expect($url)->toStartWith('https://app.lugsnplugs.test/')
        ->and($url)->not->toStartWith('https://lugsnplugs.test/');
});

test('staff login uses the app host when surface domains are enabled', function (): void {
    expect(route('login'))->toBe('https://app.lugsnplugs.test/app/login');
});

test('portal paths on the app host redirect to the customer apex host', function (): void {
    $this->get('http://app.lugsnplugs.test/portal/access')
        ->assertRedirect('https://lugsnplugs.test/portal/access');
});

test('legacy portal subdomain redirects to the customer apex host', function (): void {
    $this->get('http://portal.lugsnplugs.test/portal/access')
        ->assertRedirect('https://lugsnplugs.test/portal/access');
});

test('public www host redirects permanently to the apex host', function (): void {
    $this->get('http://www.lugsnplugs.test/')
        ->assertStatus(301)
        ->assertRedirect('https://lugsnplugs.test/');

    $this->get('http://www.lugsnplugs.test/book')
        ->assertStatus(301)
        ->assertRedirect('https://lugsnplugs.test/book');
});

test('portal access is served on the customer apex host', function (): void {
    $this->get('http://lugsnplugs.test/portal/access')
        ->assertOk();
});

test('learn host redirects to staff learn on the app host', function (): void {
    $this->get('http://learn.lugsnplugs.test/')
        ->assertRedirect('https://app.lugsnplugs.test/app/learn');
});

test('app host root redirects guests to staff login', function (): void {
    $this->get('http://app.lugsnplugs.test/')
        ->assertRedirect(route('login'));
});

test('surface routing helper reports enabled state', function (): void {
    expect(SurfaceRouting::enabled())->toBeTrue()
        ->and(SurfaceRouting::portalOnPublicHost())->toBeTrue()
        ->and(SurfaceRouting::customerHost())->toBe('lugsnplugs.test');
});
