<?php

use App\Ark\Customer\CustomerSurfaceBreadcrumbProjection;
use App\Ark\Customer\CustomerSurfaceUrls;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public home has no breadcrumb band', function () {
    $this->get(route('public.home'));

    $breadcrumb = app(CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest();

    expect($breadcrumb)->toBe([]);
});

test('lead thanks breadcrumb shows home and current page', function () {
    $this->get(route('public.leads.thanks'));

    $breadcrumb = app(CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest();

    expect($breadcrumb)->toBe([
        ['label' => 'Home', 'href' => CustomerSurfaceUrls::publicHome()],
        ['label' => 'Request received'],
    ]);
});

test('financing breadcrumb shows home and current page', function () {
    $this->get(route('public.financing'));

    $breadcrumb = app(CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest();

    expect($breadcrumb)->toBe([
        ['label' => 'Home', 'href' => CustomerSurfaceUrls::publicHome()],
        ['label' => 'Financing'],
    ]);
});

test('book appointment has no breadcrumb band', function () {
    $this->get(route('public.book'));

    $breadcrumb = app(CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest();

    expect($breadcrumb)->toBe([]);
});

test('warranty privacy and terms breadcrumbs', function () {
    $this->get(route('public.warranty'));
    expect(app(CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest())->toBe([
        ['label' => 'Home', 'href' => CustomerSurfaceUrls::publicHome()],
        ['label' => 'Warranty'],
    ]);

    $this->get(route('public.privacy'));
    expect(app(CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest())->toBe([
        ['label' => 'Home', 'href' => CustomerSurfaceUrls::publicHome()],
        ['label' => 'Privacy'],
    ]);

    $this->get(route('public.terms'));
    expect(app(CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest())->toBe([
        ['label' => 'Home', 'href' => CustomerSurfaceUrls::publicHome()],
        ['label' => 'Terms'],
    ]);
});

test('portal access breadcrumb projection', function () {
    $this->get(route('portal.access'));

    $breadcrumb = app(CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest();

    expect($breadcrumb)->toBe([
        ['label' => 'Home', 'href' => CustomerSurfaceUrls::publicHome()],
        ['label' => 'Sign In'],
    ]);
});

test('portal vehicle breadcrumb includes vehicle name', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Rivera',
        'phone' => '555-0144',
        'email' => 'jordan@example.test',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $this->actingAs($customer, 'portal')
        ->get(route('portal.vehicles.show', $vehicle));

    $breadcrumb = app(CustomerSurfaceBreadcrumbProjection::class)->forCurrentRequest();

    expect($breadcrumb[2]['label'])->toBe('2015 Ford F-150');
});
