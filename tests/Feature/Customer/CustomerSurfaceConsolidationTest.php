<?php

use App\Ark\Customer\CustomerSurfaceNavigation;
use App\Ark\Customer\CustomerSurfaceUrls;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Public\PublicLeadFormPlaceholders;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer surface urls build cross host links when surface domains are enabled', function () {
    config()->set('surfaces.enabled', true);
    config()->set('surfaces.public', 'demo-auto.test');
    config()->set('surfaces.portal', 'portal.demo-auto.test');
    config()->set('surfaces.app', 'app.demo-auto.test');

    expect(CustomerSurfaceUrls::publicHome())->toBe('https://demo-auto.test/')
        ->and(CustomerSurfaceUrls::commonProblems())->toBe('https://demo-auto.test/common-problems')
        ->and(CustomerSurfaceUrls::portalAccess())->toBe('https://demo-auto.test/portal/access')
        ->and(CustomerSurfaceUrls::portalHome())->toBe('https://demo-auto.test/portal/home');
});

test('customer surface navigation includes public and account links for guests', function () {
    config()->set('surfaces.enabled', false);

    $items = app(CustomerSurfaceNavigation::class)->items();

    $labels = collect($items)->pluck('label')->all();

    expect($labels)
        ->toContain('Home', 'Common Problems', 'Contact', 'Sign In')
        ->and($labels)->not->toContain('Book');
});

test('portal home renders shared customer navigation', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Rivera',
        'phone' => '555-0144',
        'email' => 'jordan@example.test',
        'customer_type' => 'Retail',
    ]);

    $this->actingAs($customer, 'portal')
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Common Problems', false)
        ->assertSee('My Vehicles', false)
        ->assertSee('Text', false);
});

test('customer theme init shares ecosystem display cookie across hosts', function () {
    config(['ark-ecosystem.cookie_domain' => '.demo-auto.test']);

    $this->get(route('portal.access'))
        ->assertOk()
        ->assertSee('ark_display_theme', false)
        ->assertSee('.demo-auto.test', false)
        ->assertSee('ark-customer-theme', false);
});

test('portal access uses the same full-width customer shell as the public surface', function () {
    config()->set('surfaces.enabled', false);

    $response = $this->get(route('portal.access'))
        ->assertOk()
        ->assertSee('customer-shell', false)
        ->assertSee('public-surface', false)
        ->assertSee('public-trust-strip', false)
        ->assertSee('RepairPal Certified', false)
        ->assertSee('Financing', false)
        ->assertSee('Home', false)
        ->assertSee('Sign In', false)
        ->assertSee('Sign in', false)
        ->assertSee('6-digit code', false)
        ->assertDontSee('you@example.com or 719-555-1212', false)
        ->assertSee('customer-footer__columns', false)
        ->assertDontSee('Why customers choose us', false)
        ->assertSee('Helpful links', false)
        ->assertSee('Follow us', false)
        ->assertDontSee('Still having trouble finding the problem?', false)
        ->assertDontSee('Customer Portal</a>', false);

    $content = $response->getContent();
    $showsRotatingPlaceholder = collect(PublicLeadFormPlaceholders::sets())
        ->contains(fn (array $set) => str_contains($content, $set['sign_in']));

    expect($showsRotatingPlaceholder)->toBeTrue();
});

test('authenticated account pages use the same trust chrome as the public site', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Rivera',
        'phone' => '555-0144',
        'email' => 'jordan@example.test',
        'customer_type' => 'Retail',
    ]);

    $this->actingAs($customer, 'portal')
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('public-trust-strip', false)
        ->assertSee('RepairPal Certified', false)
        ->assertSee('customer-footer__columns', false)
        ->assertDontSee('Why customers choose us', false)
        ->assertSee('Helpful links', false)
        ->assertSee('Follow us', false)
        ->assertDontSee('Still having trouble finding the problem?', false)
        ->assertSee('My Account', false)
        ->assertDontSee('Customer Portal', false)
        ->assertDontSee('customer-footer__grid', false);
});

test('privacy and terms never say portal to customers', function () {
    $this->get(route('public.privacy'))
        ->assertOk()
        ->assertSee('My Account', false)
        ->assertDontSee('customer portal', false)
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('>Privacy</span>', false);

    $this->get(route('public.terms'))
        ->assertOk()
        ->assertSee('My Account', false)
        ->assertDontSee('customer portal', false)
        ->assertSee('>Terms</span>', false);
});
