<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderCollectionDisposition;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
use App\Ark\Operations\Reports\Standards\ReportingStandardsV1;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Support\Carbon;

test('waiver tickets keep the posted invoice and show the write-off separately', function () {
    $technician = User::factory()->create(['labor_cost_cents' => 4_000]);
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-07-01', '2026-08-31');

    $tickets = [
        [1647, '2026-07-26 17:07:00', RepairOrderCollectionDisposition::Courtesy, 90_201, 5_076, 26_129, LedgerEntryType::Deposit, 69_148],
        [1631, '2026-07-16 19:23:51', RepairOrderCollectionDisposition::Trade, 58_122, 2_473, 60_595, null, 0],
        [1691, '2026-08-19 10:47:39', RepairOrderCollectionDisposition::Courtesy, 15_525, 0, 15_525, null, 0],
        [1719, '2026-08-25 17:44:29', RepairOrderCollectionDisposition::Goodwill, 45_683, 1_311, 46_994, null, 0],
    ];

    foreach ($tickets as [$number, $postedAt, $disposition, $preTaxCents, $taxCents, $writeOffCents, $cashType, $cashCents]) {
        $postedAt = Carbon::parse($postedAt, OperationalReportDateScope::displayTimezone());
        $repairOrder = invoiceAuthorityRepairOrder($number, $postedAt, $technician->id, $disposition);
        invoiceAuthorityLabor($repairOrder, $preTaxCents);
        freezePostedInvoiceSnapshot($repairOrder, $preTaxCents, $taxCents);
        invoiceAuthorityLedger($repairOrder, LedgerEntryType::WriteOff, $writeOffCents, $postedAt);
        if ($cashType !== null && $cashCents > 0) {
            invoiceAuthorityLedger($repairOrder, $cashType, $cashCents, $postedAt);
        }
    }

    $metrics = new OperationalReportRangeMetrics($from, $to);
    $kpis = collect($metrics->kpis());
    $eod = EndOfDayReportProjection::resolve($from, $to);
    $summary = collect($eod->roSummary);

    expect($kpis->firstWhere('label', 'Car count')['value'])->toBe('4')
        ->and($kpis->firstWhere('label', 'Posted invoice sales')['value'])->toBe('$2095.31')
        ->and($kpis->firstWhere('label', 'Invoice total')['value'])->toBe('$2183.91')
        ->and($kpis->firstWhere('label', 'Write-offs')['value'])->toBe('$1492.43')
        ->and($kpis->firstWhere('label', 'Cash Collected')['value'])->toBe('$691.48')
        ->and($kpis->firstWhere('label', 'ARO')['value'])->toBe('$523.83')
        ->and($summary->firstWhere('label', 'Posted invoice sales')['value'])->toBe('$2095.31')
        ->and($summary->firstWhere('label', 'Invoice total')['value'])->toBe('$2183.91')
        ->and($eod->reconciliation['write_offs'])->toBe('$1492.43')
        ->and($eod->reconciliation['cash_collected'])->toBe('$691.48')
        ->and(collect($eod->shopMetrics)->firstWhere('label', 'Gross profit margin')['value'])->toBe('92%')
        ->and(collect($metrics->ownerPlSummary()['pl_lines'])->firstWhere('label', 'Gross profit')['amount'])->toBe('$1935.31');
});

test('retail line changes after the invoice do not rewrite posted invoice sales', function () {
    $technician = User::factory()->create(['labor_cost_cents' => 4_000]);
    $cases = [
        [1552, '2026-06-12 12:31:33', 154_813, 5_571, 146_364, false],
        [1554, '2026-06-09 16:59:25', 105_596, 2_017, 109_122, true],
        [1571, '2026-06-18 11:37:30', 49_490, 983, 47_990, true],
        [1633, '2026-07-18 13:49:06', 351_938, 12_899, 351_937, true],
    ];

    foreach ($cases as [$number, $postedAt, $preTaxCents, $taxCents, $lineCents, $includePreTaxField]) {
        $postedAt = Carbon::parse($postedAt, OperationalReportDateScope::displayTimezone());
        $repairOrder = invoiceAuthorityRepairOrder($number, $postedAt, $technician->id, RepairOrderCollectionDisposition::Retail);
        invoiceAuthorityLabor($repairOrder, $lineCents);
        freezePostedInvoiceSnapshot($repairOrder, $preTaxCents, $taxCents, $includePreTaxField);
    }

    [$retailFrom, $retailTo] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-07-18');
    $metrics = new OperationalReportRangeMetrics($retailFrom, $retailTo);
    $kpis = collect($metrics->kpis());
    $summary = collect(EndOfDayReportProjection::resolve($retailFrom, $retailTo)->roSummary);

    expect($kpis->firstWhere('label', 'Car count')['value'])->toBe('4')
        ->and($kpis->firstWhere('label', 'Posted invoice sales')['value'])->toBe('$6618.37')
        ->and($kpis->firstWhere('label', 'Invoice total')['value'])->toBe('$6833.07')
        ->and($kpis->firstWhere('label', 'Write-offs')['value'])->toBe('$0.00')
        ->and($summary->firstWhere('label', 'Posted invoice sales')['value'])->toBe('$6618.37')
        ->and($summary->firstWhere('label', 'Posted invoice sales')['value'])->not->toBe('$6554.13')
        ->and(collect($metrics->kpis())->firstWhere('label', 'ARO')['value'])->toBe('$1654.59')
        ->and(collect(EndOfDayReportProjection::resolve($retailFrom, $retailTo)->shopMetrics)->firstWhere('label', 'Gross profit margin')['value'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and(collect($metrics->ownerPlSummary()['pl_lines'])->firstWhere('label', 'Gross profit')['amount'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and(collect($metrics->ownerPlSummary()['pl_lines'])->firstWhere('label', 'Gross profit')['percent'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA);
});

function invoiceAuthorityRepairOrder(
    int $number,
    Carbon $postedAt,
    int $technicianId,
    RepairOrderCollectionDisposition $disposition,
): RepairOrder {
    $customer = Customer::query()->create([
        'first_name' => 'Invoice',
        'last_name' => (string) $number,
        'phone' => '555'.$number,
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    return RepairOrder::query()->create([
        'repair_order_id' => $number,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'assigned_technician_id' => $technicianId,
        'status' => RepairOrderStatus::Closed,
        'collection_disposition' => $disposition,
        'concern_summary' => 'Invoice authority case '.$number,
        'opened_at' => $postedAt->copy()->subHour(),
        'posted_at' => $postedAt,
    ]);
}

function invoiceAuthorityLabor(RepairOrder $repairOrder, int $subtotalCents): void
{
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved work',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Labor',
        'quantity' => '1.00',
        'unit_price_cents' => $subtotalCents,
        'subtotal_cents' => $subtotalCents,
        'standing_discount_cents' => 0,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => $subtotalCents,
    ]);
}

function invoiceAuthorityLedger(RepairOrder $repairOrder, LedgerEntryType $type, int $amountCents, Carbon $recordedAt): void
{
    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'entry_type' => $type,
        'amount_cents' => $amountCents,
        'recorded_at' => $recordedAt,
    ]);
}
