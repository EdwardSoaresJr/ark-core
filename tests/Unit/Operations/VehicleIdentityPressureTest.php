<?php

use App\Ark\Operations\Vehicles\VehicleIdentityPressure;

test('no vin pressure shows chip and advisor hint', function () {
    expect(VehicleIdentityPressure::NoVin->showsChip())->toBeTrue()
        ->and(VehicleIdentityPressure::NoVin->label())->toBe('VIN missing')
        ->and(VehicleIdentityPressure::NoVin->visibilityHint())->toContain('before sending the estimate');
});

test('vin present and decoded pressures hide chip', function () {
    expect(VehicleIdentityPressure::VinPresent->showsChip())->toBeFalse()
        ->and(VehicleIdentityPressure::VinDecoded->showsChip())->toBeFalse();
});

test('estimate send block message names missing vin', function () {
    expect(VehicleIdentityPressure::NoVin->estimateSendBlockedMessage())
        ->toContain('before sending the estimate');
});
