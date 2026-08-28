<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

/**
 * @return array{0: RepairOrder}
 */
function identityHeaderRepairOrderFixture(): array
{
    $advisor = User::factory()->create(['name' => 'Lane Advisor'])->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Amber',
        'last_name' => 'Adams',
        'phone' => '719-229-7105',
        'email' => 'amber@example.com',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2011,
        'make' => 'Acura',
        'model' => 'ZDX',
        'vin' => '2HNYD2H66BH530414',
        'private_notes' => 'Legacy odometer: 165604',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Noise on acceleration',
        'drop_off' => true,
    ]);

    EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_type' => FinancialDocumentType::Estimate,
        'document_number' => 1,
        'snapshot_json' => ['generated_by' => ['name' => $advisor->name]],
        'status' => 'draft',
        'created_by' => $advisor->id,
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Drivetrain noise',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Inspect driveshaft',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 15000,
    ]);

    return [$repairOrder->fresh(['customer', 'vehicle', 'estimateDocuments.creator', 'concerns'])];
}
