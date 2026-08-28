<?php

use App\Ark\Vehicles\Canonical\CanonicalAspirationType;
use App\Ark\Vehicles\Canonical\CanonicalDrivetrain;
use App\Ark\Vehicles\Canonical\CanonicalFuelType;
use App\Ark\Vehicles\Canonical\CanonicalTransmission;
use App\Ark\Vehicles\DrivetrainNormalizer;
use App\Ark\Vehicles\EngineNormalizer;
use App\Ark\Vehicles\FuelTypeNormalizer;
use App\Ark\Vehicles\RawVehicleIdentity;
use App\Ark\Vehicles\TransmissionNormalizer;
use App\Ark\Vehicles\VehicleNormalizer;

test('engine normalization extracts displacement while preserving useful display text', function (string $raw, string $display, string $liters) {
    $engine = (new EngineNormalizer)->normalize($raw);

    expect($engine->display)->toBe($display)
        ->and($engine->displacementLiters)->toBe($liters);
})->with([
    ['5.3', '5.3L', '5.3'],
    ['5.3L', '5.3L', '5.3'],
    ['Vortec 5.3', 'Vortec 5.3', '5.3'],
    ['5.3L V8', '5.3L V8', '5.3'],
]);

test('drivetrain normalization returns canonical enum values', function (string $raw) {
    expect((new DrivetrainNormalizer)->normalize($raw))->toBe(CanonicalDrivetrain::Awd);
})->with([
    'AWD',
    'All Wheel Drive',
    '4MATIC',
    'quattro',
    'xDrive',
]);

test('transmission normalization returns canonical enum values', function (string $raw, CanonicalTransmission $expected) {
    expect((new TransmissionNormalizer)->normalize($raw))->toBe($expected);
})->with([
    ['CVT Automatic', CanonicalTransmission::Cvt],
    ['Continuously Variable Transmission', CanonicalTransmission::Cvt],
    ['Dual Clutch Transmission', CanonicalTransmission::Dct],
    ['6-speed manual', CanonicalTransmission::Manual],
    ['Automatic', CanonicalTransmission::Automatic],
]);

test('fuel and aspiration normalization use canonical values', function () {
    expect((new FuelTypeNormalizer)->normalize('Gasoline'))->toBe(CanonicalFuelType::Gasoline);

    $identity = (new VehicleNormalizer)->normalize(new RawVehicleIdentity(
        year: '2020',
        make: 'Audi',
        model: 'A4',
        engine: '2.0L BiTurbo',
        fuelType: 'Gasoline',
        aspiration: 'BiTurbo',
        drivetrain: 'quattro',
        transmission: 'DCT',
    ));

    expect($identity->aspiration)->toBe(CanonicalAspirationType::TwinTurbo)
        ->and($identity->drivetrain)->toBe(CanonicalDrivetrain::Awd)
        ->and($identity->transmission)->toBe(CanonicalTransmission::Dct)
        ->and($identity->fuelType)->toBe(CanonicalFuelType::Gasoline)
        ->and($identity->normalizedVehicleKey)->toBe('2020-audi-a4-2l-awd-dct');
});
