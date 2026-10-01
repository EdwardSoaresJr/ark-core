<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportPaymentReconciliation;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\Reports\Standards\ReportingStandardsV1;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;

test('ro 1699 recommended work stays out of posted sales and the end of day summary foots once', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');
    $repairOrder = reportingStandardsRepairOrder($from);

    $approved = reportingStandardsConcern($repairOrder, RepairOrderConcernDisposition::Approved, 1);
    reportingStandardsLine($repairOrder, $approved, RepairOrderLineType::Labor, subtotalCents: 10_000, discountCents: 1_000, taxCents: 800);
    reportingStandardsLine($repairOrder, $approved, RepairOrderLineType::Part, subtotalCents: 4_000, taxCents: 320, shopFeeCents: 300);
    reportingStandardsLine($repairOrder, $approved, RepairOrderLineType::Fee, subtotalCents: 500);
    reportingStandardsLine($repairOrder, $approved, RepairOrderLineType::Sublet, subtotalCents: 1_500);

    $recommended = reportingStandardsConcern($repairOrder, RepairOrderConcernDisposition::Recommended, 2);
    reportingStandardsLine($repairOrder, $recommended, RepairOrderLineType::Labor, subtotalCents: 39_161);
    freezePostedInvoiceSnapshot($repairOrder, 15_300, 1_120);

    $standardPreTax = ReportingStandardsV1::preTaxServiceSalesCents(10_000, 4_000, 1_500, 800, 1_000);
    $standardPosted = ReportingStandardsV1::postedSalesCents(10_000, 4_000, 1_500, 800, 1_000, 1_120);

    expect($repairOrder->repair_order_id)->toBe(1699)
        ->and(ReportingStandardsV1::countsAsApprovedRevenue(RepairOrderConcernDisposition::Recommended))->toBeFalse()
        ->and($standardPreTax)->toBe(15_300)
        ->and($standardPosted)->toBe(16_420)
        ->and(OperationalReportTotals::postedSalesCents([$repairOrder->id]))->toBe($standardPosted)
        ->and(OperationalReportTotals::postedSalesCents([$repairOrder->id]))->not->toBe($standardPosted + 39_161);

    $summary = collect(EndOfDayReportProjection::resolve($from, $to)->roSummary);

    expect($summary->firstWhere('label', 'Posted invoice sales')['value'])->toBe('$153.00')
        ->and($summary->firstWhere('label', 'Other')['value'])->toBe('$8.00')
        ->and($summary->firstWhere('label', 'Discounts')['value'])->toBe('-$10.00')
        ->and($summary->firstWhere('label', 'Sales tax')['value'])->toBe('$11.20')
        ->and($summary->firstWhere('label', 'Invoice total')['value'])->toBe('$164.20')
        ->and(collect($summary)->firstWhere('label', 'Posted invoice sales')['value'])->not->toBe('$544.61');
});

test('cash collected and the cashiered total subtract refunds', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');
    $repairOrder = reportingStandardsRepairOrder($from);

    reportingStandardsLedgerEntry($repairOrder, LedgerEntryType::Payment, 10_000, $from);
    reportingStandardsLedgerEntry($repairOrder, LedgerEntryType::Deposit, 2_500, $from);
    reportingStandardsLedgerEntry($repairOrder, LedgerEntryType::Refund, 400, $from);
    reportingStandardsLedgerEntry($repairOrder, LedgerEntryType::Payment, 99_999, $from, voided: true);

    $cashiered = collect((new OperationalReportPaymentReconciliation($from, $to))->summary()['rows'])
        ->firstWhere('key', 'total_cashiered');

    expect(OperationalReportTotals::cashCollectedCents($from, $to))->toBe(12_100)
        ->and(ReportingStandardsV1::cashCollectedCents(12_500, 400))->toBe(12_100)
        ->and($cashiered['amount'])->toBe('$121.00');
});

test('a posted part with no cost does not publish a parts margin', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');
    $repairOrder = reportingStandardsRepairOrder($from);
    $approved = reportingStandardsConcern($repairOrder, RepairOrderConcernDisposition::Approved, 1);
    reportingStandardsLine($repairOrder, $approved, RepairOrderLineType::Part, subtotalCents: 4_000);

    $margin = collect((new OperationalReportRangeMetrics($from, $to))->kpis())
        ->firstWhere('label', 'Parts gross profit margin');

    expect($margin['value'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and($margin['hint'])->toBe('$40.00 parts sales have no cost');
});

function reportingStandardsRepairOrder(Carbon $from): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Reporting',
        'last_name' => 'Standard',
        'phone' => '5551699',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Honda',
        'model' => 'CR-V',
    ]);

    return RepairOrder::query()->create([
        'repair_order_id' => 1699,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Invoiced,
        'concern_summary' => 'Reporting standards integrity case.',
        'opened_at' => $from->copy()->addHour(),
        'posted_at' => $from->copy()->addHours(4),
    ]);
}

function reportingStandardsConcern(
    RepairOrder $repairOrder,
    RepairOrderConcernDisposition $disposition,
    int $position,
): RepairOrderConcern {
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $disposition->label().' work',
        'disposition' => $disposition,
        'position' => $position,
    ]);
}

function reportingStandardsLine(
    RepairOrder $repairOrder,
    RepairOrderConcern $concern,
    RepairOrderLineType $type,
    int $subtotalCents,
    int $discountCents = 0,
    int $taxCents = 0,
    int $shopFeeCents = 0,
): RepairOrderLine {
    $netSubtotalCents = max(0, $subtotalCents - $discountCents);

    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => $type,
        'description' => $type->value.' line',
        'quantity' => '1.00',
        'unit_price_cents' => $subtotalCents,
        'procurement_state' => PartProcurementState::None,
        'subtotal_cents' => $subtotalCents,
        'standing_discount_cents' => $discountCents,
        'tax_cents' => $taxCents,
        'shop_fee_cents' => $shopFeeCents,
        'total_cents' => $netSubtotalCents + $taxCents + $shopFeeCents,
    ]);
}

function reportingStandardsLedgerEntry(
    RepairOrder $repairOrder,
    LedgerEntryType $type,
    int $amountCents,
    Carbon $from,
    bool $voided = false,
): void {
    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'entry_type' => $type,
        'amount_cents' => $amountCents,
        'recorded_at' => $from->copy()->addHours(5),
        'voided_at' => $voided ? $from->copy()->addHours(6) : null,
    ]);
}
