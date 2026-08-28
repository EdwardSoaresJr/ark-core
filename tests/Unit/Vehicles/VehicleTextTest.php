<?php

use App\Ark\Vehicles\RawVehicleIdentity;
use App\Ark\Vehicles\VehicleNormalizer;
use App\Ark\Vehicles\VehicleText;

test('vehicle display labels normalize all caps decode values', function () {
    expect(VehicleText::displayMake('FORD'))->toBe('Ford')
        ->and(VehicleText::displayMake('BMW'))->toBe('BMW')
        ->and(VehicleText::displayModel('MUSTANG'))->toBe('Mustang')
        ->and(VehicleText::displayModel('RAV4'))->toBe('Rav4')
        ->and(VehicleText::displayTrim('XLE'))->toBe('XLE')
        ->and(VehicleText::displayTrim('LIMITED'))->toBe('Limited')
        ->and(VehicleText::displayMake('Ford'))->toBe('Ford');
});

test('vehicle normalizer applies display labels for manual intake saves', function () {
    $identity = (new VehicleNormalizer)->normalize(new RawVehicleIdentity(
        year: '2020',
        make: 'FORD',
        model: 'F-150',
        trim: 'XLT',
        source: 'manual',
    ));

    expect($identity->make)->toBe('Ford')
        ->and($identity->model)->toBe('F-150')
        ->and($identity->trim)->toBe('XLT');
});
