<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Intake\IntakeQualificationProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\Vehicles\Vehicle;


test('intake qualification projection surfaces missing phone and vin with next action', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Mia',
        'last_name' => 'Lopez',
        'email' => 'mia@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Nissan',
        'model' => 'Altima',
        'plate' => 'ALT2017',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Customer has replaced 19 batteries this year.',
    ]);

    $projection = IntakeQualificationProjection::forRepairOrder($repairOrder);

    expect($projection->completeCount)->toBe(1)
        ->and($projection->totalCount)->toBe(5)
        ->and($projection->qualificationLabel())->toBe('1/5 complete')
        ->and($projection->missingLabels)->toContain('Phone', 'VIN', 'Visit type', 'Scope opened')
        ->and($projection->nextAction)->toBe('Get phone number')
        ->and($projection->concernPreview)->toBe('Customer has replaced 19 batteries this year.')
        ->and($projection->isReady)->toBeFalse();
});

test('intake qualification projection marks estimate ready when checklist is complete', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'phone' => '555-0199',
        'email' => 'jordan@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
        'vin' => '4S4BSANC2J3333333',
        'normalized_vin' => '4S4BSANC2J3333333',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake noise when stopping.',
        'drop_off' => true,
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise when stopping.',
        'customer_states' => 'Grinding when stopping.',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
        'subtotal_cents' => 12000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 12000,
    ]);

    $projection = IntakeQualificationProjection::forRepairOrder($repairOrder->fresh(['customer', 'vehicle', 'concerns', 'lines']));

    expect($projection->isReady)->toBeTrue()
        ->and($projection->missingLabels)->toBe([])
        ->and($projection->nextAction)->toBe('Finish estimate')
        ->and($projection->workspaceUrl)->toBe(route('operations.repair-orders.show', $repairOrder));
});

test('intake qualification next action prioritizes visit type after concern is captured', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Chevrolet',
        'model' => 'Tahoe',
        'vin' => '1GNEN13Z26J109456',
        'normalized_vin' => '1GNEN13Z26J109456',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Check engine light on.',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Check engine light on.',
        'customer_states' => 'Check engine light on.',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 1,
    ]);

    $projection = IntakeQualificationProjection::forRepairOrder($repairOrder->fresh(['customer', 'vehicle', 'concerns', 'lines']));

    expect($projection->missingLabels)->toBe(['Visit type'])
        ->and($projection->nextAction)->toBe('Set visit type');

    RepairOrderVisitMode::DropOff->applyTo($repairOrder);
    $repairOrder->save();

    $readyProjection = IntakeQualificationProjection::forRepairOrder($repairOrder->fresh(['customer', 'vehicle', 'concerns', 'lines']));

    expect($readyProjection->isReady)->toBeTrue()
        ->and($readyProjection->nextAction)->toBe('Build estimate');
});
