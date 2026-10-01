<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Intake\RepairOrderIntakeConcerns;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;

test('repair order intake concerns parses multiline customer hub text into scopes', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'placeholder',
        'opened_at' => now(),
    ]);

    $concerns = app(RepairOrderIntakeConcerns::class)->seed(
        $repairOrder,
        "Brake noise\nOil change",
        intakeSource: 'customer_hub',
    );

    expect($concerns)->toHaveCount(2)
        ->and($repairOrder->fresh()->concern_summary)->toBe('Brake noise')
        ->and(RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->orderBy('position')->pluck('summary')->all())
        ->toBe(['Brake noise', 'Oil change']);
});
