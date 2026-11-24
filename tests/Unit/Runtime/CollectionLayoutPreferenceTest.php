<?php

use App\Ark\Runtime\Preferences\CollectionLayout;
use App\Ark\Runtime\Preferences\CollectionLayoutModule;
use App\Ark\Runtime\Preferences\CollectionLayoutPreference;
use Illuminate\Http\Request;

it('defaults to auto when no view or cookie is present', function () {
    $resolved = CollectionLayoutPreference::resolve(
        Request::create('/app/customers/search', 'GET'),
        CollectionLayoutModule::Customers,
    );

    expect($resolved['mode'])->toBe(CollectionLayoutPreference::MODE_AUTO)
        ->and($resolved['layout'])->toBeNull()
        ->and($resolved['explicit'])->toBeFalse()
        ->and($resolved['module'])->toBe(CollectionLayoutModule::Customers)
        ->and($resolved['scope'])->toHaveKeys(['tenant', 'user']);
});

it('honors explicit view query and queues a module cookie', function () {
    $fromQuery = CollectionLayoutPreference::resolve(
        Request::create('/app/vehicles/search', 'GET', ['view' => 'list']),
        CollectionLayoutModule::Vehicles,
    );

    expect($fromQuery['mode'])->toBe(CollectionLayout::List->value)
        ->and($fromQuery['layout'])->toBe(CollectionLayout::List)
        ->and($fromQuery['explicit'])->toBeTrue();
});

it('reads the module-scoped cookie when view is absent', function () {
    $request = Request::create('/repair-orders', 'GET');
    $request->cookies->set(CollectionLayoutModule::RepairOrders->cookieName(), 'cards');

    $fromCookie = CollectionLayoutPreference::resolve($request, CollectionLayoutModule::RepairOrders);

    expect($fromCookie['mode'])->toBe(CollectionLayout::Cards->value)
        ->and($fromCookie['layout'])->toBe(CollectionLayout::Cards)
        ->and($fromCookie['explicit'])->toBeTrue();
});

it('exposes cookie names for EncryptCookies exceptions', function () {
    expect(CollectionLayoutPreference::cookieNames())->toBe([
        'ark_collection_layout_customers',
        'ark_collection_layout_vehicles',
        'ark_collection_layout_repair_orders',
    ]);
});
