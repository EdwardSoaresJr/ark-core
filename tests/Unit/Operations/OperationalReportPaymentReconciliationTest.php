<?php

use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportPaymentReconciliation;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Tests\TestCase;


test('total cashiered matches cash collected kpi', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $fixture = paymentReconciliationFixture('Cash KPI');
    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $fixture['repairOrder']->id,
        'customer_id' => $fixture['customer']->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 12500,
        'recorded_at' => $from->copy()->addHours(9),
    ]);

    $summary = (new OperationalReportPaymentReconciliation($from, $to))->summary();
    $totalRow = collect($summary['rows'])->firstWhere('key', 'total_cashiered');

    expect($totalRow['amount'])->toBe('$125.00')
        ->and(OperationalReportTotals::cashCollectedCents($from, $to))->toBe(12500)
        ->and($totalRow['details'])->toHaveCount(1);
});

test('cleared from a/r lists contributing repair orders', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $fixture = paymentReconciliationFixture('Prior Posted');
    $postedAt = $from->copy()->subDays(3);
    $fixture['repairOrder']->forceFill([
        'status' => RepairOrderStatus::Closed,
        'opened_at' => $postedAt->copy()->subDay(),
        'closed_at' => $postedAt,
        'posted_at' => $postedAt,
    ])->save();

    addApprovedSoldLine($fixture['repairOrder'], $fixture['concern'], 69077);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $fixture['repairOrder']->id,
        'customer_id' => $fixture['customer']->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 69077,
        'recorded_at' => $from->copy()->addHours(10),
    ]);

    $summary = (new OperationalReportPaymentReconciliation($from, $to))->summary();
    $clearedRow = collect($summary['rows'])->firstWhere('key', 'cleared_from_ar');

    expect($clearedRow)->not->toBeNull()
        ->and($clearedRow['details'])->toHaveCount(1)
        ->and($clearedRow['details'][0]['amount'])->toBe('$690.77')
        ->and($clearedRow['details'][0]['repair_order_pk'])->toBe($fixture['repairOrder']->id);
});

test('cleared from a/r subtracts payments on ro posted before range', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $fixture = paymentReconciliationFixture('Prior Posted');
    $postedAt = $from->copy()->subDays(3);
    $fixture['repairOrder']->forceFill([
        'status' => RepairOrderStatus::Closed,
        'opened_at' => $postedAt->copy()->subDay(),
        'closed_at' => $postedAt,
        'posted_at' => $postedAt,
    ])->save();

    addApprovedSoldLine($fixture['repairOrder'], $fixture['concern'], 69077);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $fixture['repairOrder']->id,
        'customer_id' => $fixture['customer']->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 69077,
        'recorded_at' => $from->copy()->addHours(10),
    ]);

    $summary = (new OperationalReportPaymentReconciliation($from, $to))->summary();
    $totalRow = collect($summary['rows'])->firstWhere('key', 'total_cashiered');
    $clearedRow = collect($summary['rows'])->firstWhere('key', 'cleared_from_ar');

    expect($totalRow['amount'])->toBe('$690.77')
        ->and($clearedRow['amount'])->toBe('−$690.77')
        ->and($summary['reconciled_cents'])->toBe(0)
        ->and($summary['posted_ro_summary_total_cents'])->toBe(0)
        ->and($summary['reconciles'])->toBeTrue();
});

test('previous advanced pay adds pre-range payments on ro posted in range', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $fixture = paymentReconciliationFixture('Posted Today');
    $fixture['repairOrder']->forceFill([
        'status' => RepairOrderStatus::Closed,
        'opened_at' => $from->copy()->subDays(2),
        'closed_at' => $from->copy()->addHours(8),
        'posted_at' => $from->copy()->addHours(8),
    ])->save();

    addApprovedSoldLine($fixture['repairOrder'], $fixture['concern'], 37757);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $fixture['repairOrder']->id,
        'customer_id' => $fixture['customer']->id,
        'entry_type' => LedgerEntryType::Deposit,
        'amount_cents' => 10000,
        'recorded_at' => $from->copy()->subDay()->addHours(15),
    ]);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $fixture['repairOrder']->id,
        'customer_id' => $fixture['customer']->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 27757,
        'recorded_at' => $from->copy()->addHours(11),
    ]);

    $summary = (new OperationalReportPaymentReconciliation($from, $to))->summary();
    $previousRow = collect($summary['rows'])->firstWhere('key', 'previous_advanced_pay');

    expect(collect($summary['rows'])->firstWhere('key', 'total_cashiered')['amount'])->toBe('$277.57')
        ->and($previousRow['amount'])->toBe('$100.00')
        ->and($previousRow['details'])->toHaveCount(1)
        ->and($summary['reconciled_cents'])->toBe(37757)
        ->and($summary['posted_ro_summary_total_cents'])->toBe(37757)
        ->and($summary['reconciles'])->toBeTrue();
});

test('advance pay subtracts payments on unposted repair orders', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $fixture = paymentReconciliationFixture('Still Open');
    $fixture['repairOrder']->forceFill([
        'status' => RepairOrderStatus::Approved,
        'opened_at' => $from->copy()->subDay(),
        'posted_at' => null,
    ])->save();

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $fixture['repairOrder']->id,
        'customer_id' => $fixture['customer']->id,
        'entry_type' => LedgerEntryType::Deposit,
        'amount_cents' => 50000,
        'recorded_at' => $from->copy()->addHours(9),
    ]);

    $summary = (new OperationalReportPaymentReconciliation($from, $to))->summary();
    $advanceRow = collect($summary['rows'])->firstWhere('key', 'advance_pay');

    expect(collect($summary['rows'])->firstWhere('key', 'total_cashiered')['amount'])->toBe('$500.00')
        ->and($advanceRow['amount'])->toBe('−$500.00')
        ->and($advanceRow['details'])->toHaveCount(1)
        ->and($summary['reconciled_cents'])->toBe(0)
        ->and($summary['reconciles'])->toBeTrue();
});

test('future-posted payments appear only in advance pay never in cleared from a/r', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $priorPosted = paymentReconciliationFixture('Prior Posted Ar');
    $priorPostedAt = $from->copy()->subDays(3);
    $priorPosted['repairOrder']->forceFill([
        'status' => RepairOrderStatus::Closed,
        'opened_at' => $priorPostedAt->copy()->subDay(),
        'closed_at' => $priorPostedAt,
        'posted_at' => $priorPostedAt,
    ])->save();
    addApprovedSoldLine($priorPosted['repairOrder'], $priorPosted['concern'], 15000);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $priorPosted['repairOrder']->id,
        'customer_id' => $priorPosted['customer']->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 15000,
        'recorded_at' => $from->copy()->addHours(9),
    ]);

    $futurePosted = paymentReconciliationFixture('Future Posted');
    $futurePostedAt = $to->copy()->addDays(5);
    $futurePosted['repairOrder']->forceFill([
        'status' => RepairOrderStatus::Closed,
        'opened_at' => $from->copy()->subDay(),
        'closed_at' => $futurePostedAt,
        'posted_at' => $futurePostedAt,
    ])->save();
    addApprovedSoldLine($futurePosted['repairOrder'], $futurePosted['concern'], 42808);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $futurePosted['repairOrder']->id,
        'customer_id' => $futurePosted['customer']->id,
        'entry_type' => LedgerEntryType::Deposit,
        'amount_cents' => 42808,
        'recorded_at' => $from->copy()->addHours(10),
    ]);

    $summary = (new OperationalReportPaymentReconciliation($from, $to))->summary();
    $advanceRow = collect($summary['rows'])->firstWhere('key', 'advance_pay');
    $clearedRow = collect($summary['rows'])->firstWhere('key', 'cleared_from_ar');

    $advancePks = collect($advanceRow['details'])->pluck('repair_order_pk');
    $clearedPks = collect($clearedRow['details'])->pluck('repair_order_pk');

    expect($advanceRow['amount'])->toBe('−$428.08')
        ->and($advancePks->all())->toBe([$futurePosted['repairOrder']->id])
        ->and($clearedRow['amount'])->toBe('−$150.00')
        ->and($clearedPks->all())->toBe([$priorPosted['repairOrder']->id])
        ->and($advancePks->intersect($clearedPks)->all())->toBe([])
        ->and($summary['reconciled_cents'])->toBe(0);
});

test('tekmetric jun 8 style day reconciles posted sales to cashiered cash', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $priorPosted = paymentReconciliationFixture('Prior Posted');
    $priorPosted['repairOrder']->forceFill([
        'status' => RepairOrderStatus::Closed,
        'opened_at' => $from->copy()->subDays(5),
        'closed_at' => $from->copy()->subDays(3),
        'posted_at' => $from->copy()->subDays(3),
    ])->save();
    addApprovedSoldLine($priorPosted['repairOrder'], $priorPosted['concern'], 69077);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $priorPosted['repairOrder']->id,
        'customer_id' => $priorPosted['customer']->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 69077,
        'recorded_at' => $from->copy()->addHours(9),
    ]);

    $postedToday = paymentReconciliationFixture('Posted Today');
    $postedToday['repairOrder']->forceFill([
        'status' => RepairOrderStatus::Closed,
        'opened_at' => $from->copy()->subDay(),
        'closed_at' => $from->copy()->addHours(8),
        'posted_at' => $from->copy()->addHours(8),
    ])->save();
    addApprovedSoldLine($postedToday['repairOrder'], $postedToday['concern'], 37757);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $postedToday['repairOrder']->id,
        'customer_id' => $postedToday['customer']->id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 37757,
        'recorded_at' => $from->copy()->addHours(10),
    ]);

    $summary = (new OperationalReportPaymentReconciliation($from, $to))->summary();
    $clearedRow = collect($summary['rows'])->firstWhere('key', 'cleared_from_ar');

    expect(collect($summary['rows'])->firstWhere('key', 'total_cashiered')['amount'])->toBe('$1068.34')
        ->and($clearedRow['amount'])->toBe('−$690.77')
        ->and($clearedRow['details'])->toHaveCount(1)
        ->and($summary['posted_ro_summary']['details'])->toHaveCount(1)
        ->and($summary['reconciled_cents'])->toBe(37757)
        ->and($summary['posted_ro_summary_total_cents'])->toBe(37757)
        ->and($summary['reconciles'])->toBeTrue();
});

/**
 * @return array{customer: \App\Ark\Operations\Customers\Customer, repairOrder: RepairOrder, concern: RepairOrderConcern}
 */
function paymentReconciliationFixture(string $customerName): array
{
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-03'.random_int(10, 99),
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Invoiced,
        'concern_summary' => 'Payment reconciliation fixture.',
        'opened_at' => now()->subDay(),
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved work',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    return compact('customer', 'repairOrder', 'concern');
}

function addApprovedSoldLine(RepairOrder $repairOrder, RepairOrderConcern $concern, int $totalCents): RepairOrderLine
{
    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Labor',
        'quantity' => '1.00',
        'unit_price_cents' => $totalCents,
        'subtotal_cents' => $totalCents,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => $totalCents,
    ]);
}
