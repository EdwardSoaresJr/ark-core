<?php

use App\Ark\Vehicles\VehicleIntelligenceManager;
use Illuminate\Support\Facades\Http;

test('vehicle intelligence decodes vin via nhtsa', function () {
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
        ->and($identity->drivetrain?->value)->toBe('awd')
        ->and($identity->transmission?->label())->toBe('Automatic')
        ->and($identity->normalizedVehicleKey)->toBe('2019-toyota-rav4-xle-2-5l-awd-automatic');
});

test('vehicle intelligence returns null for invalid vin', function () {
    Http::fake();

    expect(app(VehicleIntelligenceManager::class)->decodeVin('abc'))->toBeNull();

    Http::assertNothingSent();
});

test('vehicle intelligence does not call external services for plate decode', function () {
    Http::fake();

    expect(app(VehicleIntelligenceManager::class)->decodePlate('abc123', 'co'))->toBeNull();

    Http::assertNothingSent();
});
