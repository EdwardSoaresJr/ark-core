<?php

use App\Ark\Import\LegacyArkSmsValueMapper;

test('repair order mileage resolver reads configured and alternate legacy keys', function () {
    $mapper = new LegacyArkSmsValueMapper;

    expect($mapper->repairOrderMileage([
        'mileage_in' => 120000,
        'mileage_out' => 120412,
    ]))->toBe([
        'mileage_in' => 120000,
        'mileage_out' => 120412,
    ]);

    expect($mapper->repairOrderMileage([
        'odometer_in' => '145,500',
        'checkout_mileage' => 145892,
    ]))->toBe([
        'mileage_in' => 145500,
        'mileage_out' => 145892,
    ]);

    expect($mapper->repairOrderMileage([
        'mileage' => 88000,
    ]))->toBe([
        'mileage_in' => 88000,
        'mileage_out' => null,
    ]);
});
