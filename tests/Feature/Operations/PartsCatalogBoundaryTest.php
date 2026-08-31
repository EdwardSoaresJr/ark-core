<?php

use App\Ark\Operations\Parts\Contracts\PartsCatalogLauncher;
use App\Ark\Operations\Parts\NotConfiguredPartsCatalogLauncher;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Vehicles\VehicleIntelligenceManager;
use Illuminate\Support\Facades\Http;

test('stock core cannot activate parts catalog via shop settings credentials', function () {
    expect(app(PartsCatalogLauncher::class))
        ->toBeInstanceOf(NotConfiguredPartsCatalogLauncher::class)
        ->and(app(PartsCatalogLauncher::class)->configured())->toBeFalse();

    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->partsTechUsername())->toBeNull()
        ->and($credentials->partsTechApiKey())->toBeNull()
        ->and($credentials->partsTechPassword())->toBeNull()
        ->and($credentials->partsTechCatalogConfigured())->toBeFalse()
        ->and($credentials->partsTechQuoteImportConfigured())->toBeFalse()
        ->and($credentials->partsTechCredentialSource())->toBe('none');
});

test('parts catalog routes are not registered in stock core', function () {
    expect(\Illuminate\Support\Facades\Route::has('operations.repair-orders.partstech'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Route::has('operations.settings.shop.partstech.update'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Route::has('profile.partstech.update'))->toBeFalse();
});

test('vehicle intelligence decodes vin via nhtsa only in stock core', function () {
    Http::fake([
        'vpic.nhtsa.dot.gov/*' => Http::response([
            'Results' => [[
                'ModelYear' => '2019',
                'Make' => 'Toyota',
                'Model' => 'RAV4',
                'Trim' => 'XLE',
                'EngineModel' => '2.5L',
                'DriveType' => 'All-Wheel Drive',
                'TransmissionStyle' => 'Automatic',
                'FuelTypePrimary' => 'Gasoline',
                'BodyClass' => 'Sport Utility Vehicle',
            ]],
        ]),
    ]);

    $identity = app(VehicleIntelligenceManager::class)->decodeVin('2T3RFREV6KW020202');

    expect($identity)->not->toBeNull()
        ->and($identity->source)->toBe('nhtsa')
        ->and($identity->make)->toBe('Toyota')
        ->and($identity->model)->toBe('Rav4');

    Http::assertSentCount(1);
});

test('plate decode is unavailable in stock core', function () {
    Http::fake();

    $identity = app(VehicleIntelligenceManager::class)->decodePlate('ABC123', 'CO');

    expect($identity)->toBeNull();
    Http::assertNothingSent();
});
