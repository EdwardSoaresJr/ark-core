<?php

use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Runtime\Preferences\UiLayoutModule;
use App\Ark\Runtime\Preferences\UiLayoutPreference;
use App\Ark\Runtime\Preferences\UiPreferenceScope;
use Illuminate\Http\Request;

test('ui layout preference defaults to auto without cookie or query', function () {
    $resolved = UiLayoutPreference::resolve(Request::create('/app', 'GET'), UiLayoutModule::JobBoard);

    expect($resolved['mode'])->toBe(UiLayoutPreference::MODE_AUTO)
        ->and($resolved['layout'])->toBeNull()
        ->and($resolved['explicit'])->toBeFalse()
        ->and($resolved['module'])->toBe(UiLayoutModule::JobBoard)
        ->and($resolved['scope'])->toHaveKeys(['tenant', 'user']);
});

test('ui layout preference honors view query and queues legacy cookie', function () {
    $fromQuery = UiLayoutPreference::resolve(
        Request::create('/app', 'GET', ['view' => 'cards']),
        UiLayoutModule::JobBoard,
    );

    expect($fromQuery['mode'])->toBe('cards')
        ->and($fromQuery['layout'])->toBe(JobBoardLayout::Cards)
        ->and($fromQuery['explicit'])->toBeTrue();
});

test('ui layout modules keep independent legacy cookie names', function () {
    expect(UiLayoutModule::JobBoard->legacyCookieName())->toBe('ark_job_board_layout')
        ->and(UiLayoutModule::Customers->legacyCookieName())->toBe('ark_collection_layout_customers')
        ->and(UiLayoutModule::Vehicles->legacyCookieName())->toBe('ark_collection_layout_vehicles')
        ->and(UiLayoutModule::RepairOrders->legacyCookieName())->toBe('ark_collection_layout_repair_orders')
        ->and(UiLayoutPreference::legacyCookieNames())->toContain('ark_job_board_layout');
});

test('ui preference scope falls back to request host', function () {
    $request = Request::create('https://shop.example.test/app', 'GET');

    expect(UiPreferenceScope::tenantKey($request))->toBe('shop.example.test')
        ->and(UiPreferenceScope::userKey(null))->toBe('anon');
});
