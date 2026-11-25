<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RecordApprovedWorkScopeAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Str;

function financialSubmissionKey(): string
{
    return (string) Str::uuid();
}

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
        'mileage_in' => 120000,
        'mileage_out' => 120050,
        'concern_summary' => 'Financial closeout test.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'production_status' => ScopeProductionStatus::Completed,
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

/**
 * Isolated fixture matching the original failure: $2,500 authorized work,
 * $500 parts-only suggested deposit, In Progress, no deposits/invoice.
 */
function repairOrderWithTwentyFiveHundredAuthorizedFiveHundredSuggested(
    RepairOrderStatus $status = RepairOrderStatus::InProgress,
): RepairOrder {
    ShopSettings::current()->update([
        'shop_fee_enabled' => false,
        'tax_enabled' => false,
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => false,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Advance',
        'last_name' => 'TwentyFive',
        'phone' => '555-2500',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'mileage_in' => 45000,
        'concern_summary' => 'Full authorized advance acceptance fixture.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved multi-system repair',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'production_status' => ScopeProductionStatus::InProgress,
        'recommendation_intent' => 'repair',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Major assembly',
        'quantity' => '1.00',
        'unit_price_cents' => 50000,
        'part_cost_cents' => 32000,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Install and diagnose',
        'quantity' => '1.00',
        'unit_price_cents' => 200000,
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

/**
 * @return array{0: RepairOrder, 1: \App\Ark\Operations\Documents\EstimateDocument|null}
 */
function arOnAccountInvoiceForCustomer(?Customer $customer = null): array
{
    $repairOrder = financialCloseoutRepairOrder();

    if ($customer !== null) {
        $repairOrder->forceFill([
            'customer_id' => $customer->id,
            'vehicle_id' => $customer->vehicles()->first()?->id ?? $repairOrder->vehicle_id,
        ])->save();
    }

    issueFinalInvoiceFor($repairOrder->fresh());

    $closed = app(\App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition::class)->move(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed,
        auth()->user(),
        'on_account',
    );

    $invoice = app(\App\Ark\Operations\Financial\BalanceDueCalculator::class)->issuedInvoice($closed);

    return [$closed->fresh(), $invoice];
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
        'production_status' => ScopeProductionStatus::Completed,
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

/** Records approval of the current amounts on the repair order's approved concerns, as an approval does. */
function approveCurrentAmounts(RepairOrder $repairOrder): void
{
    $repairOrder->fresh(['concerns'])->concerns
        ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved)
        ->each(fn (RepairOrderConcern $concern) => app(RecordApprovedWorkScopeAction::class)->execute($concern));
}
