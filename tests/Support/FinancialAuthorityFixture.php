<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;

function repairOrderForFinancialAuthority(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Financial',
        'last_name' => 'Authority',
        'phone' => '555-0199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'MATH01',
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Financial authority test estimate.',
    ]);
}

function concernForFinancialAuthority(RepairOrder $repairOrder): RepairOrderConcern
{
    return RepairOrderConcern::query()->firstOrCreate(
        [
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Financial concern',
        ],
        [
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'recommendation_intent' => 'maintenance',
            'position' => 1,
        ],
    );
}
