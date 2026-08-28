<?php

use App\Ark\Vehicles\VehicleIntelligenceManager;
use Illuminate\Support\Facades\Http;

test('vehicle intelligence falls back from unavailable partstech to nhtsa', function () {
    config()->set('services.partstech.username', 'ark');
    config()->set('services.partstech.api_key', 'secret');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    Http::fake([
        'partstech.test/*' => Http::response([], 500),
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
        ->and($identity->drivetrain?->value)->toBe('awd')
        ->and($identity->transmission?->label())->toBe('Automatic')
        ->and($identity->normalizedVehicleKey)->toBe('2019-toyota-rav4-xle-2-5l-awd-automatic');
});

test('vehicle intelligence merges nhtsa only into missing partstech fields', function () {
    config()->set('services.partstech.username', 'ark');
    config()->set('services.partstech.api_key', 'secret');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    Http::fake([
        'partstech.test/*' => Http::response([
            'vehicle' => [
                'year' => '2019',
                'make' => 'Toyota',
                'model' => 'RAV4',
                'trim' => 'XLE',
                'engine' => '2.5L',
            ],
        ]),
        'vpic.nhtsa.dot.gov/*' => Http::response([
            'Results' => [[
                'ModelYear' => '2019',
                'Make' => 'Toyota',
                'Model' => 'Wrong Model',
                'Trim' => 'Wrong Trim',
                'DriveType' => 'All-Wheel Drive',
                'TransmissionStyle' => 'Automatic',
            ]],
        ]),
    ]);

    $identity = app(VehicleIntelligenceManager::class)->decodeVin('2T3RFREV6KW020202');

    expect($identity)->not->toBeNull()
        ->and($identity->source)->toBe('partstech+nhtsa')
        ->and($identity->make)->toBe('Toyota')
        ->and($identity->model)->toBe('Rav4')
        ->and($identity->trim)->toBe('XLE')
        ->and($identity->drivetrain?->label())->toBe('AWD')
        ->and($identity->transmission?->label())->toBe('Automatic');
});

test('partstech and nhtsa payloads normalize to the same canonical key', function () {
    config()->set('services.partstech.username', 'ark');
    config()->set('services.partstech.api_key', 'secret');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    Http::fake([
        'partstech.test/*' => Http::response([
            'vehicle' => [
                'year' => '2019',
                'make' => 'Toyota',
                'model' => 'RAV4',
                'trim' => 'XLE',
                'engine' => '2.5L',
                'drive' => 'AWD',
                'transmission' => 'Auto',
                'fuel_type' => 'Gasoline',
                'body_style' => 'Sport Utility Vehicle',
            ],
        ]),
    ]);

    $partstech = app(VehicleIntelligenceManager::class)->decodeVin('2T3RFREV6KW020202');

    config()->set('services.partstech.username', null);

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

    $nhtsa = app(VehicleIntelligenceManager::class)->decodeVin('2T3RFREV6KW020202');

    expect($partstech?->normalizedVehicleKey)->toBe($nhtsa?->normalizedVehicleKey)
        ->and($partstech?->drivetrain)->toBe($nhtsa?->drivetrain)
        ->and($partstech?->transmission)->toBe($nhtsa?->transmission);
});

test('vehicle intelligence decodes plate via partstech and enriches from vin when returned', function () {
    config()->set('services.partstech.username', 'ark');
    config()->set('services.partstech.api_key', 'secret');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    Http::fake([
        'partstech.test/*' => function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), 'plate=ABC123')) {
                return Http::response([
                    'vehicle' => [
                        'vin' => '2T3RFREV6KW020202',
                        'year' => '2019',
                        'make' => 'Toyota',
                        'model' => 'RAV4',
                    ],
                ]);
            }

            return Http::response([
                'vehicle' => [
                    'year' => '2019',
                    'make' => 'Toyota',
                    'model' => 'RAV4',
                    'trim' => 'XLE',
                    'engine' => '2.5L',
                    'drive' => 'AWD',
                    'transmission' => 'Auto',
                ],
            ]);
        },
    ]);

    $identity = app(VehicleIntelligenceManager::class)->decodePlate('abc123', 'co');

    expect($identity)->not->toBeNull()
        ->and($identity->normalizedVin)->toBe('2T3RFREV6KW020202')
        ->and($identity->make)->toBe('Toyota')
        ->and($identity->model)->toBe('Rav4')
        ->and($identity->trim)->toBe('XLE');
});
