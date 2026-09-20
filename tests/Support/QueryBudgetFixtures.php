<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

require_once __DIR__.'/QueryBudget.php';

function seedQueryBudgetCatalog(): void
{
    test()->seed(ArkAuthorizationSeeder::class);
    test()->seed(RepairOrderStatusCatalogSeeder::class);
}

function repairOrderForQueryBudget(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Query',
        'last_name' => 'Budget',
        'phone' => '7195554242',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'QBUD1',
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Query budget estimate workspace.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake inspection',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Inspect front brakes',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8500,
        'position' => 2,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder(
        $repairOrder->fresh(['customer', 'concerns', 'lines.concern']),
    );

    return $repairOrder->fresh(['customer', 'vehicle', 'concerns.lines', 'lines.concern']);
}

function workboardRepairOrdersForQueryBudget(): void
{
    $statuses = [
        RepairOrderStatus::WaitingApproval,
        RepairOrderStatus::WaitingParts,
        RepairOrderStatus::InProgress,
        RepairOrderStatus::ReadyPickup,
    ];

    foreach ($statuses as $index => $status) {
        $customer = Customer::query()->create([
            'first_name' => 'Workboard',
            'last_name' => 'Lane '.$index,
            'phone' => '71955550'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]);

        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'plate' => 'WB'.$index,
            'year' => 2018,
            'make' => 'Honda',
            'model' => 'Civic',
        ]);

        RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => $status,
            'concern_summary' => 'Workboard lane '.$status->value,
        ]);
    }
}

function customerHubCustomerForQueryBudget(): Customer
{
    $customer = Customer::query()->create([
        'first_name' => 'Hub',
        'last_name' => 'Customer',
        'phone' => '7195557878',
        'email' => 'hub.customer@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'HUB1',
        'year' => 2020,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    foreach ([RepairOrderStatus::Closed, RepairOrderStatus::InProgress] as $index => $status) {
        RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => $status,
            'concern_summary' => 'Hub history '.$index,
        ]);
    }

    return $customer;
}

function portalCustomerForQueryBudget(): Customer
{
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.vehicle@example.test',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'JEEP14',
    ]);

    return $customer;
}
