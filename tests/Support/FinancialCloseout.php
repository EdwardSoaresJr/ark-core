<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;

function financialCloseoutRepairOrder(
    RepairOrderStatus $status = RepairOrderStatus::ReadyPickup,
): RepairOrder {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);

    $customer = Customer::query()->create([
        'first_name' => 'Closeout',
        'last_name' => 'Customer',
        'phone' => '555-0200',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Financial closeout test.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake service',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return $repairOrder->fresh();
}

function repairOrderWithSuggestedPartDeposit(
    RepairOrderStatus $status = RepairOrderStatus::Approved,
): RepairOrder {
    $repairOrder = financialCloseoutRepairOrder($status);
    $concern = $repairOrder->concerns()->first();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'part_cost_cents' => 5000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return $repairOrder->fresh();
}

function issueFinalInvoiceFor(RepairOrder $repairOrder): void
{
    $repairOrder->forceFill(['status' => RepairOrderStatus::ReadyPickup])->save();

    $repairOrder = $repairOrder->fresh();
    $repairOrder->load(['lines.concern', 'concerns']);

    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
}

function payRepairOrderInFull(RepairOrder $repairOrder): void
{
    $balance = $repairOrder->fresh()->balanceDue();

    if ($balance->balanceDueCents <= 0) {
        return;
    }

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        $balance->balanceDueCents,
        PaymentMethod::Cash,
    );
}

function zeroBalanceCourtesyRepairOrder(
    RepairOrderStatus $status = RepairOrderStatus::ReadyPickup,
): RepairOrder {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);

    $customer = Customer::query()->create([
        'first_name' => 'Courtesy',
        'last_name' => 'Customer',
        'phone' => '555-0300',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Corolla',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Courtesy diagnostic follow-up.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Courtesy follow-up',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Courtesy diagnostic follow-up',
        'labor_category_key' => 'courtesy',
        'quantity' => '0.50',
        'unit_price_cents' => 0,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return $repairOrder->fresh();
}
