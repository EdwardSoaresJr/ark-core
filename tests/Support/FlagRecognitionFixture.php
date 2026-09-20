<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern, 2: \App\Ark\Operations\RepairOrders\RepairOrderLine}
 */
function flagRecognitionFixture(?User $technician, float $laborHours = 1.5): array
{
    static $vinSeq = 0;
    $vinSeq++;

    $customer = Customer::query()->create([
        'first_name' => 'Flag',
        'last_name' => 'Recognition'.$vinSeq,
        'phone' => '555-01'.str_pad((string) ($vinSeq % 100), 2, '0', STR_PAD_LEFT),
    ]);

    $vin = sprintf('3C6UR5CJ0LG%06d', $vinSeq);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Ram',
        'model' => '2500',
        'vin' => $vin,
        'normalized_vin' => $vin,
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'assigned_technician_id' => $technician?->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Flag recognition fixture.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake job',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
        'production_status' => ScopeProductionStatus::InProgress,
    ]);

    $hours = number_format($laborHours, 2, '.', '');

    $laborLine = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Front brake service',
        'quantity' => $hours,
        'labor_billed_hours' => $hours,
        'unit_price_cents' => 15000,
        'subtotal_cents' => (int) round($laborHours * 15000),
        'tax_cents' => 0,
        'total_cents' => (int) round($laborHours * 15000),
    ]);

    return [$repairOrder->fresh(['concerns', 'lines']), $concern->fresh(), $laborLine->fresh()];
}
