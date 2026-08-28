<?php

use App\Ark\Vehicles\RawVehicleIdentity;
use App\Ark\Vehicles\VinNormalizer;

test('vin normalizer coerces numeric json payloads without dropping trailing zero', function () {
    $normalizer = new VinNormalizer;

    expect($normalizer->coerceInput('4S3BP616X76430010'))->toBe('4S3BP616X76430010')
        ->and($normalizer->coerceInput('2T3RFREV6KW020202'))->toBe('2T3RFREV6KW020202')
        ->and($normalizer->coerceInput(76430010))->toBe('76430010');
});

test('raw vehicle identity preserves vin ending in zero from numeric input', function () {
    $raw = RawVehicleIdentity::fromArray([
        'vin' => 76430010,
        'year' => '2019',
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    expect($raw->vin)->toBe('76430010');
});
