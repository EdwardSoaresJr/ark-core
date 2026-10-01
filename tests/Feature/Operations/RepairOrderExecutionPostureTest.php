<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

test('execution posture labels cover every repair order status', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $customer = Customer::query()->create([
        'first_name' => 'Posture',
        'last_name' => 'Coverage',
        'phone' => '555-0199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'POST1',
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    foreach (RepairOrderStatus::cases() as $status) {
        $repairOrder = RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => $status,
            'concern_summary' => 'Execution posture coverage.',
        ]);

        expect($repairOrder->executionPostureLabel())->toBeString()->not->toBe('')
            ->and($repairOrder->executionNextAction())->toBeString()->not->toBe('');
    }
});
