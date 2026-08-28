<?php

use App\Ark\Operations\Parts\CustomerPartDescriptionPresenter;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

test('customer part description presenter uses explicit customer description first', function () {
    $presenter = new CustomerPartDescriptionPresenter;

    expect($presenter->present(partLine(
        description: 'Duralast AWP1234 Water Pump',
        customerDescription: 'Water Pump',
    )))->toBe('Water Pump');
});

test('customer part description presenter derives repair focused labels from inventory descriptions', function (string $inventory, string $expected) {
    $presenter = new CustomerPartDescriptionPresenter;

    expect($presenter->present(partLine(description: $inventory)))->toBe($expected);
})->with([
    ['Duralast Gold Battery H6-DLG', 'Battery'],
    ['Gates 43527 Water Pump', 'Water Pump'],
    ['Duralast Brake Rotor 312mm', 'Brake Rotor'],
    ['Motorcraft SP548 Spark Plug', 'Spark Plug'],
    ['NGK Laser Iridium Plug ILTR5E11', 'Spark Plug'],
    ['Prestone Dex-Cool 50/50 Prediluted Extended Life Antifreeze/Coolant', 'Coolant'],
    ['Brakebest Select Ceramic Disc Brake Pad Set', 'Ceramic Disc Brake Pad Set'],
]);

test('customer part description presenter ignores coolant compatibility boilerplate on real parts', function (string $inventory, string $expected) {
    $presenter = new CustomerPartDescriptionPresenter;

    expect($presenter->present(partLine(description: $inventory)))->toBe($expected);
})->with([
    ['Compatible with all Antifreeze/Coolant formulations - Stant 45359 Thermostat 180F', 'Thermostat'],
    ['Antifreeze/Coolant Compatible Fel-Pro Water Pump Gasket Set', 'Water Pump'],
    ['HOAT coolant compatible seal - Gates 22012 Lower Radiator Hose', 'Radiator Hose'],
    ['Engine Coolant Temperature Sensor', 'Temperature Sensor'],
    ['Gates Upper Radiator Hose', 'Radiator Hose'],
    ['Fel-Pro 35087 Water Pump Gasket', 'Water Pump'],
]);

test('customer part description presenter preserves premium brands when they carry customer value', function (string $inventory, string $expected) {
    $presenter = new CustomerPartDescriptionPresenter;

    expect($presenter->present(partLine(description: $inventory)))->toBe($expected);
})->with([
    ['Bilstein 5100 Shock', 'Bilstein 5100 Shock'],
    ['Brembo Rotor', 'Brembo Brake Rotor'],
    ['Motorcraft Battery', 'Motorcraft Battery'],
]);

test('customer part description presenter keeps catalog brake brands consistent across pads and rotors', function () {
    $presenter = new CustomerPartDescriptionPresenter;

    expect($presenter->present(partLine(description: 'Import Direct Disc Brake Pad Set')))
        ->toBe('Import Direct Disc Brake Pad Set')
        ->and($presenter->present(partLine(description: 'Import Direct Brake Rotor 312mm')))
        ->toBe('Import Direct Brake Rotor');
});

test('customer part description presenter infers rotor brand from branded pad line in same repair action', function () {
    $presenter = new CustomerPartDescriptionPresenter;

    $pad = partLine(description: 'Import Direct Disc Brake Pad Set');
    $pad->forceFill(['id' => 1, 'repair_order_work_group_id' => 10]);

    $rotor = partLine(description: 'Brake Rotor');
    $rotor->forceFill(['id' => 2, 'repair_order_work_group_id' => 10]);
    $rotor->setRelation('workGroup', new \App\Ark\Operations\RepairOrders\RepairOrderWorkGroup([
        'id' => 10,
        'title' => 'Replace Front Disc Pads & Replace Rotors',
    ]));
    $rotor->workGroup->setRelation('lines', collect([$pad, $rotor]));

    expect($presenter->present($rotor))->toBe('Import Direct Brake Rotor');
});

test('customer part description presenter leaves non part lines unchanged', function () {
    $presenter = new CustomerPartDescriptionPresenter;

    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace water pump',
    ]);

    expect($presenter->present($line))->toBe('Replace water pump');
});

function partLine(string $description, ?string $customerDescription = null): RepairOrderLine
{
    return new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'description' => $description,
        'customer_description' => $customerDescription,
    ]);
}
