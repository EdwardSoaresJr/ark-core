<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportPaymentReconciliation;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Tests\TestCase;


test('sales posted excludes unpaid repair orders without posted_at', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Unposted',
        'last_name' => 'Paid',
        'phone' => '5550199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Invoiced,
        'concern_summary' => 'Paid but not posted.',
        'opened_at' => $from->copy()->subDay(),
        'closed_at' => $from->copy()->addHours(8),
        'posted_at' => null,
    ]);

    expect(
        OperationalReportDateScope::salesPostedBetween(RepairOrder::query()->whereKey($repairOrder->id), $from, $to)->exists(),
    )->toBeFalse();
});

test('cash collected sums ledger payments and deposits in range', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $customer = Customer::query()->create([
        'first_name' => 'Cash',
        'last_name' => 'Drawer',
        'phone' => '5550200',
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
        'status' => RepairOrderStatus::Invoiced,
        'concern_summary' => 'Cash test.',
        'opened_at' => $from->copy()->subDay(),
    ]);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 50000,
        'recorded_at' => $from->copy()->addHours(9),
    ]);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'entry_type' => LedgerEntryType::Deposit,
        'amount_cents' => 2500,
        'recorded_at' => $from->copy()->addHours(10),
    ]);

    expect(OperationalReportTotals::cashCollectedCents($from, $to))->toBe(52500);
});

test('cash collected can be scoped to repair orders', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $includedCustomer = Customer::query()->create([
        'first_name' => 'Scoped',
        'last_name' => 'Cash',
        'phone' => '5550201',
    ]);

    $excludedCustomer = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Cash',
        'phone' => '5550202',
    ]);

    $includedVehicle = Vehicle::query()->create([
        'customer_id' => $includedCustomer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $excludedVehicle = Vehicle::query()->create([
        'customer_id' => $excludedCustomer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Corolla',
    ]);

    $includedRepairOrder = RepairOrder::query()->create([
        'customer_id' => $includedCustomer->id,
        'vehicle_id' => $includedVehicle->id,
        'status' => RepairOrderStatus::Invoiced,
        'concern_summary' => 'Scoped cash test.',
        'opened_at' => $from->copy()->subDay(),
    ]);

    $excludedRepairOrder = RepairOrder::query()->create([
        'customer_id' => $excludedCustomer->id,
        'vehicle_id' => $excludedVehicle->id,
        'status' => RepairOrderStatus::Invoiced,
        'concern_summary' => 'Excluded cash test.',
        'opened_at' => $from->copy()->subDay(),
    ]);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $includedRepairOrder->id,
        'customer_id' => $includedCustomer->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 40000,
        'recorded_at' => $from->copy()->addHours(9),
    ]);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $excludedRepairOrder->id,
        'customer_id' => $excludedCustomer->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 10000,
        'recorded_at' => $from->copy()->addHours(10),
    ]);

    expect(OperationalReportTotals::cashCollectedCentsForRepairOrders([$includedRepairOrder->id], $from, $to))->toBe(40000)
        ->and(OperationalReportTotals::cashCollectedCents($from, $to))->toBe(50000);
});

test('posted sales includes sublet revenue and matches reconciliation and eod', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);

    [$from, $to] = OperationalReportDateScope::resolveRange('2026-07-01', '2026-07-03');

    $customer = Customer::query()->create([
        'first_name' => 'Sublet',
        'last_name' => 'Posted',
        'phone' => '555-0300',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::ReadyPickup,
        'concern_summary' => 'Alignment sublet posted sales test.',
        'posted_at' => $from->copy()->addHours(10),
        'payment_status' => RepairOrderPaymentStatus::Paid,
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Alignment',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Labor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Sublet,
        'description' => 'Alignment sublet',
        'quantity' => '1.00',
        'unit_price_cents' => 9500,
        'part_cost_cents' => 7500,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder->fresh());

    $postedIds = OperationalReportDateScope::salesPostedBetween(RepairOrder::query(), $from, $to)->pluck('id');

    expect($postedIds)->toHaveCount(1)
        ->and(OperationalReportTotals::postedSalesCents($postedIds))->toBe(19500)
        ->and(OperationalReportTotals::closedRevenueCents($postedIds))->toBe(19500);

    $reconciliation = (new OperationalReportPaymentReconciliation($from, $to))->summary();
    $metrics = new OperationalReportRangeMetrics($from, $to);
    $salesPostedKpi = collect($metrics->kpis())->firstWhere('label', 'Sales Posted');
    $eod = EndOfDayReportProjection::resolve($from, $to);

    expect($reconciliation['posted_ro_summary_total_cents'])->toBe(19500)
        ->and($salesPostedKpi['value'])->toBe('$195.00')
        ->and($eod->reconciliation['sales_posted'])->toBe('$195.00');
});
