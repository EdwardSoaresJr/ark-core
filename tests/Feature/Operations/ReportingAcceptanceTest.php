<?php

use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingStoryComposer;
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
use App\Ark\Operations\Reports\Standards\ReportingStandardsV1;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\OwnerOperationalPulse;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->persistTrusted([
        'learn_training_gate_enabled' => false,
    ]);
});

test('the same posted range reports one car count sales aro and gross profit', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin);

    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');
    $technician = User::factory()->create(['labor_cost_cents' => 4_000]);

    $posted = acceptanceRepairOrder($from, 1801, RepairOrderStatus::Closed, $technician->id);
    $approved = acceptanceConcern($posted, RepairOrderConcernDisposition::Approved, 1);
    acceptanceLine($posted, $approved, RepairOrderLineType::Labor, 20_000, quantity: '2.00', discountCents: 1_000, taxCents: 800);
    acceptanceLine($posted, $approved, RepairOrderLineType::Part, 8_000, taxCents: 400, partCostCents: 3_000);
    acceptanceLine($posted, $approved, RepairOrderLineType::Fee, 500);
    $recommended = acceptanceConcern($posted, RepairOrderConcernDisposition::Recommended, 2);
    acceptanceLine($posted, $recommended, RepairOrderLineType::Labor, 39_161);
    freezePostedInvoiceSnapshot($posted, 27_500, 1_200);

    $reopened = acceptanceRepairOrder($from, 1802, RepairOrderStatus::InProgress, $technician->id);
    $reopenedWork = acceptanceConcern($reopened, RepairOrderConcernDisposition::Approved, 1);
    acceptanceLine($reopened, $reopenedWork, RepairOrderLineType::Labor, 5_000, quantity: '1.00');
    acceptanceLine($reopened, $reopenedWork, RepairOrderLineType::Part, 4_000, partCostCents: 1_500);
    freezePostedInvoiceSnapshot($reopened, 9_000, 0);

    $lost = acceptanceRepairOrder($from, 1803, RepairOrderStatus::Closed, $technician->id, 'lost');
    $lostWork = acceptanceConcern($lost, RepairOrderConcernDisposition::Approved, 1);
    acceptanceLine($lost, $lostWork, RepairOrderLineType::Labor, 77_777);

    $unposted = acceptanceRepairOrder($from, 1804, RepairOrderStatus::InProgress, $technician->id, posted: false);
    $unpostedWork = acceptanceConcern($unposted, RepairOrderConcernDisposition::Approved, 1);
    acceptanceLine($unposted, $unpostedWork, RepairOrderLineType::Labor, 88_888);

    $outside = acceptanceRepairOrder($from->copy()->subDay(), 1805, RepairOrderStatus::Closed, $technician->id);
    $outsideWork = acceptanceConcern($outside, RepairOrderConcernDisposition::Approved, 1);
    acceptanceLine($outside, $outsideWork, RepairOrderLineType::Labor, 66_666);

    acceptanceLedger($posted, LedgerEntryType::Payment, 10_000, $from);
    acceptanceLedger($posted, LedgerEntryType::Deposit, 2_500, $from);
    acceptanceLedger($posted, LedgerEntryType::Refund, 400, $from);
    acceptanceLedger($posted, LedgerEntryType::WriteOff, 700, $from);
    acceptanceLedger($posted, LedgerEntryType::Payment, 99_999, $from, voided: true);

    $salesCents = 36_500;
    $aroCents = 18_250;
    $grossProfitCents = 20_000;
    $grossProfitPerRepairOrderCents = 10_000;
    $marginPercent = 55;
    $cashCents = 12_100;

    expect(ReportingStandardsV1::postedInvoiceSalesCents(36_500, 37_700, 1_200))->toBe($salesCents)
        ->and(ReportingStandardsV1::aroCents($salesCents, 2))->toBe($aroCents)
        ->and(ReportingStandardsV1::grossProfitCents($salesCents, 16_500))->toBe($grossProfitCents)
        ->and(ReportingStandardsV1::grossMarginPercent($salesCents, 16_500))->toBe($marginPercent)
        ->and(ReportingStandardsV1::cashCollectedCents(12_500, 400))->toBe($cashCents);

    $metrics = new OperationalReportRangeMetrics($from, $to);
    $kpis = collect($metrics->kpis());
    $eod = EndOfDayReportProjection::resolve($from, $to);
    $summary = collect($eod->roSummary);
    $shop = collect($eod->shopMetrics);
    $effectiveness = collect($eod->salesEffectiveness);
    $marginHealth = collect($metrics->marginHealthRows());
    $pl = collect($metrics->ownerPlSummary()['pl_lines']);
    $dayReview = collect($metrics->dayReviewKpis());
    $financial = collect($metrics->financialRows());
    $pulse = app(OwnerOperationalPulse::class)->dailyDigest($from, $to);
    $pulseHeadlines = collect($pulse['headlines']);
    $briefing = (new BriefingStoryComposer)->yesterdaySummary(new BriefingContext(
        $admin,
        $from,
        $from,
        $to,
        $from,
        $to,
    ));
    $cashiered = collect((new OperationalReportPaymentReconciliation($from, $to))->summary()['rows'])
        ->firstWhere('key', 'total_cashiered');

    $sales = '$365.00';
    $aro = '$182.50';
    $grossProfit = '$200.00';
    $grossProfitPerRepairOrder = '$100.00';
    $cash = '$121.00';

    expect($effectiveness->firstWhere('label', 'Car count')['value'])->toBe('2')
        ->and($kpis->firstWhere('label', 'Car count')['value'])->toBe('2')
        ->and($summary->firstWhere('label', 'Posted invoice sales')['value'])->toBe($sales)
        ->and($summary->firstWhere('label', 'Invoice total')['value'])->toBe('$377.00')
        ->and($summary->firstWhere('label', 'Discounts')['value'])->toBe('-$10.00')
        ->and($pl->firstWhere('label', 'Posted invoice sales')['amount'])->toBe($sales)
        ->and($pl->firstWhere('label', 'Write-offs')['amount'])->toBe('$7.00')
        ->and($pl->firstWhere('label', 'Invoice total')['amount'])->toBe('$377.00')
        ->and(collect($briefing)->firstWhere('label', 'Posted invoice sales')['value'])->toBe($sales)
        ->and(collect($briefing)->firstWhere('label', 'Car count')['value'])->toBe('2')
        ->and($shop->firstWhere('label', 'ARO')['value'])->toBe($aro)
        ->and($kpis->firstWhere('label', 'ARO')['value'])->toBe($aro)
        ->and($marginHealth->firstWhere('metric', 'ARO')['actual'])->toBe($aro)
        ->and($pulseHeadlines->firstWhere('label', 'ARO')['value'])->toBe($aro)
        ->and($pulseHeadlines->firstWhere('label', 'Car count')['value'])->toBe('2')
        ->and($pl->firstWhere('label', 'Gross profit')['amount'])->toBe($grossProfit)
        ->and($pl->firstWhere('label', 'Gross profit')['percent'])->toBe($marginPercent.'%')
        ->and($dayReview->firstWhere('label', 'Closed GP')['value'])->toBe($grossProfit)
        ->and($dayReview->firstWhere('label', 'Gross profit margin')['value'])->toBe($marginPercent.'%')
        ->and($shop->firstWhere('label', 'Gross profit / RO')['value'])->toBe($grossProfitPerRepairOrder)
        ->and($shop->firstWhere('label', 'Gross profit margin')['value'])->toBe($marginPercent.'%')
        ->and($kpis->firstWhere('label', 'Cash Collected')['value'])->toBe($cash)
        ->and($eod->reconciliation['cash_collected'])->toBe($cash)
        ->and($cashiered['amount'])->toBe($cash)
        ->and($pl->firstWhere('label', 'Cash collected')['amount'])->toBe($cash)
        ->and($kpis->firstWhere('label', 'Posted invoice sales')['value'])->toBe($sales)
        ->and($kpis->firstWhere('label', 'Invoice total')['value'])->toBe('$377.00')
        ->and($kpis->firstWhere('label', 'Write-offs')['value'])->toBe('$7.00')
        ->and($financial->firstWhere('category', 'Labor')['sales'])->toBe('$250.00')
        ->and($financial->firstWhere('category', 'Parts')['sales'])->toBe('$120.00')
        ->and($financial->firstWhere('category', 'Other')['sales'])->toBe('$5.00')
        ->and($financial->firstWhere('category', 'Parts')['margin'])->toBe('63%')
        ->and($summary->pluck('value')->all())->not->toContain('$391.61', '$777.77', '$888.88', '$666.66');

    $range = ['from' => '2026-06-08', 'to' => '2026-06-08'];

    $this->get(route('operations.reports.end-of-day', ['date' => '2026-06-08']))
        ->assertOk()
        ->assertSee('Car count')
        ->assertSee($sales)
        ->assertSee($aro)
        ->assertSee($grossProfitPerRepairOrder)
        ->assertSee($marginPercent.'%')
        ->assertSee($cash)
        ->assertDontSee('$391.61')
        ->assertDontSee('$777.77');

    $this->get(route('operations.owner.day-review', ['date' => '2026-06-08']))
        ->assertOk()
        ->assertSee($sales)
        ->assertSee($aro)
        ->assertSee($grossProfitPerRepairOrder);

    $this->get(route('operations.reports.operational', $range))
        ->assertOk()
        ->assertSee('Car count')
        ->assertSee($aro)
        ->assertSee($cash);

    $this->get(route('operations.reports.operational', array_merge($range, ['tab' => 'margin-health'])))
        ->assertOk()
        ->assertSee($aro);

    $this->get(route('operations.reports.operational', array_merge($range, ['tab' => 'owner-pl'])))
        ->assertOk()
        ->assertSee('Posted invoice sales')
        ->assertSee($sales)
        ->assertSee($grossProfit)
        ->assertSee($marginPercent.'%');

    $this->get(route('operations.reports.operational', array_merge($range, ['tab' => 'financial'])))
        ->assertOk()
        ->assertSee($cash)
        ->assertSee('$250.00')
        ->assertSee('$120.00');
});

test('missing parts cost keeps gross profit incomplete on every report', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');
    $technician = User::factory()->create(['labor_cost_cents' => 4_000]);
    $posted = acceptanceRepairOrder($from, 1810, RepairOrderStatus::Closed, $technician->id);
    $approved = acceptanceConcern($posted, RepairOrderConcernDisposition::Approved, 1);
    acceptanceLine($posted, $approved, RepairOrderLineType::Labor, 10_000, quantity: '1.00');
    acceptanceLine($posted, $approved, RepairOrderLineType::Part, 4_000);
    freezePostedInvoiceSnapshot($posted, 14_000, 0);

    $metrics = new OperationalReportRangeMetrics($from, $to);
    $kpis = collect($metrics->kpis());
    $eod = EndOfDayReportProjection::resolve($from, $to);
    $shop = collect($eod->shopMetrics);
    $pl = collect($metrics->ownerPlSummary()['pl_lines']);
    $dayReview = collect($metrics->dayReviewKpis());
    $financial = collect($metrics->financialRows());

    expect(collect($eod->salesEffectiveness)->firstWhere('label', 'Car count')['value'])->toBe('1')
        ->and($kpis->firstWhere('label', 'Car count')['value'])->toBe('1')
        ->and(collect($eod->roSummary)->firstWhere('label', 'Posted invoice sales')['value'])->toBe('$140.00')
        ->and($pl->firstWhere('label', 'Posted invoice sales')['amount'])->toBe('$140.00')
        ->and($pl->firstWhere('label', 'Gross profit')['amount'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and(collect($eod->shopMetrics)->firstWhere('label', 'ARO')['value'])->toBe('$140.00')
        ->and($kpis->firstWhere('label', 'ARO')['value'])->toBe('$140.00')
        ->and($shop->firstWhere('label', 'Gross profit margin')['value'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and($shop->firstWhere('label', 'Gross profit / RO')['value'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and($dayReview->firstWhere('label', 'Gross profit margin')['value'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and($dayReview->firstWhere('label', 'Closed GP')['value'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and($pl->firstWhere('label', 'Gross profit')['percent'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and($kpis->firstWhere('label', 'Parts gross profit margin')['value'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and($kpis->firstWhere('label', 'Parts gross profit margin')['hint'])->toBe('$40.00 parts sales have no cost')
        ->and($financial->firstWhere('category', 'Parts')['margin'])->toBe(ReportingStandardsV1::INCOMPLETE_DATA);
});

function acceptanceRepairOrder(
    Carbon $postedAt,
    int $repairOrderId,
    RepairOrderStatus $status,
    int $technicianId,
    ?string $closeVariant = null,
    bool $posted = true,
): RepairOrder {
    $customer = Customer::query()->create([
        'first_name' => 'Acceptance',
        'last_name' => (string) $repairOrderId,
        'phone' => '555'.$repairOrderId,
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    return RepairOrder::query()->create([
        'repair_order_id' => $repairOrderId,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'assigned_technician_id' => $technicianId,
        'status' => $status,
        'close_variant_key' => $closeVariant,
        'concern_summary' => 'Reporting acceptance.',
        'opened_at' => $postedAt->copy()->subHour(),
        'posted_at' => $posted ? $postedAt->copy()->addHours(4) : null,
    ]);
}

function acceptanceConcern(
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

function acceptanceLine(
    RepairOrder $repairOrder,
    RepairOrderConcern $concern,
    RepairOrderLineType $type,
    int $subtotalCents,
    string $quantity = '1.00',
    int $discountCents = 0,
    int $taxCents = 0,
    int $shopFeeCents = 0,
    ?int $partCostCents = null,
): RepairOrderLine {
    $netSubtotalCents = max(0, $subtotalCents - $discountCents);

    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => $type,
        'description' => $type->value.' line',
        'quantity' => $quantity,
        'unit_price_cents' => $subtotalCents,
        'part_cost_cents' => $partCostCents,
        'procurement_state' => PartProcurementState::None,
        'subtotal_cents' => $subtotalCents,
        'standing_discount_cents' => $discountCents,
        'tax_cents' => $taxCents,
        'shop_fee_cents' => $shopFeeCents,
        'total_cents' => $netSubtotalCents + $taxCents + $shopFeeCents,
    ]);
}

function acceptanceLedger(
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
