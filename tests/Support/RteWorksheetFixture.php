<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern}
 */
function createRteWorksheetRepairOrder(int $year, string $make, string $model): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'phone' => '5550100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => $year,
        'make' => $make,
        'model' => $model,
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake concern',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    return [$repairOrder, $concern];
}
