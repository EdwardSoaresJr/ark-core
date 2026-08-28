<?php

use App\Ark\Operations\Financial\FinancialContractSource;
use App\Ark\Operations\Financial\FinancialPositionProjection;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('financial position tracks estimate changes before any invoice exists', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $position = FinancialPositionProjection::for($repairOrder);

    expect($position->contractSource)->toBe(FinancialContractSource::Estimate)
        ->and($position->approvedWorkCents)->toBe(15000)
        ->and($position->coverageCents)->toBe(0)
        ->and($position->customerOwesTodayCents)->toBe(15000)
        ->and($position->hasIssuedFinalInvoice())->toBeFalse();

    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    app(RecordLedgerEntryAction::class)->recordDeposit(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Cash,
        $actor,
    );

    $afterDeposit = FinancialPositionProjection::for($repairOrder->fresh());

    expect($afterDeposit->depositsCents)->toBe(5000)
        ->and($afterDeposit->customerOwesTodayCents)->toBe(10000);

    $concern = $repairOrder->concerns()->first();
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Extra labor',
        'quantity' => '1.00',
        'unit_price_cents' => 4000,
        'subtotal_cents' => 4000,
        'total_cents' => 4000,
    ]);
    app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)
        ->recalculateRepairOrder($repairOrder->fresh());

    $afterEstimateChange = FinancialPositionProjection::for($repairOrder->fresh());

    expect($afterEstimateChange->approvedWorkCents)->toBe(19000)
        ->and($afterEstimateChange->customerOwesTodayCents)->toBe(14000)
        ->and($afterEstimateChange->contractSource)->toBe(FinancialContractSource::Estimate);
});

test('after final invoice living estimate may drift but position stays on immutable invoice', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    issueFinalInvoiceFor($repairOrder);

    $invoice = $repairOrder->fresh()->balanceDue();
    expect($invoice->hasIssuedInvoice)->toBeTrue();

    $issuedInvoice = app(\App\Ark\Operations\Financial\BalanceDueCalculator::class)
        ->issuedInvoice($repairOrder->fresh());
    $issuedTotal = InvoiceSnapshotBuilder::invoiceTotalCents($issuedInvoice->snapshot_json ?? []);
    $issuedSnapshotJson = $issuedInvoice->snapshot_json;

    $before = FinancialPositionProjection::for($repairOrder->fresh());
    expect($before->contractSource)->toBe(FinancialContractSource::Invoice)
        ->and($before->approvedWorkCents)->toBe($issuedTotal)
        ->and($before->customerOwesTodayCents)->toBe($issuedTotal);

    $concern = $repairOrder->concerns()->first();
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Post-invoice estimate edit',
        'quantity' => '1.00',
        'unit_price_cents' => 7000,
        'subtotal_cents' => 7000,
        'total_cents' => 7000,
    ]);
    app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)
        ->recalculateRepairOrder($repairOrder->fresh());

    $livingEstimateTotal = app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)
        ->approvedTotalsForRead($repairOrder->fresh())
        ->totalCents();

    $after = FinancialPositionProjection::for($repairOrder->fresh());
    $invoiceDoc = app(\App\Ark\Operations\Financial\BalanceDueCalculator::class)
        ->issuedInvoice($repairOrder->fresh());
    $invoiceTotalAfter = InvoiceSnapshotBuilder::invoiceTotalCents($invoiceDoc->snapshot_json ?? []);

    // Living estimate may change — that is not a second financial contract.
    expect($livingEstimateTotal)->toBe($issuedTotal + 7000)
        ->and($livingEstimateTotal)->not->toBe($issuedTotal);

    // Final Invoice is immutable evidence — never silently refreshed.
    expect($invoiceTotalAfter)->toBe($issuedTotal)
        ->and($invoiceDoc->snapshot_json)->toBe($issuedSnapshotJson);

    // Position remains the owe-today authority from Invoice + Ledger — not living estimate.
    expect($after->contractSource)->toBe(FinancialContractSource::Invoice)
        ->and($after->approvedWorkCents)->toBe($issuedTotal)
        ->and($after->customerOwesTodayCents)->toBe($issuedTotal)
        ->and($after->approvedWorkCents)->not->toBe($livingEstimateTotal);
});

test('before final invoice estimate changes move financial position', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);

    $before = FinancialPositionProjection::for($repairOrder);
    expect($before->contractSource)->toBe(FinancialContractSource::Estimate)
        ->and($before->customerOwesTodayCents)->toBe(15000);

    $concern = $repairOrder->concerns()->first();
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Pre-invoice estimate edit',
        'quantity' => '1.00',
        'unit_price_cents' => 2500,
        'subtotal_cents' => 2500,
        'total_cents' => 2500,
    ]);
    app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)
        ->recalculateRepairOrder($repairOrder->fresh());

    $after = FinancialPositionProjection::for($repairOrder->fresh());

    expect($after->contractSource)->toBe(FinancialContractSource::Estimate)
        ->and($after->approvedWorkCents)->toBe(17500)
        ->and($after->customerOwesTodayCents)->toBe(17500)
        ->and($after->customerOwesTodayCents)->not->toBe($before->customerOwesTodayCents);
});

test('financial position projection is pure identical inputs yield identical answers with zero writes', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    app(RecordLedgerEntryAction::class)->recordDeposit(
        $repairOrder->fresh(),
        2500,
        PaymentMethod::Cash,
        $actor,
    );

    $repairOrder = $repairOrder->fresh();
    $first = FinancialPositionProjection::for($repairOrder)->toArray();

    $writeCount = 0;
    $listener = function (QueryExecuted $query) use (&$writeCount): void {
        $sql = strtolower($query->sql);
        if (str_starts_with($sql, 'insert ')
            || str_starts_with($sql, 'update ')
            || str_starts_with($sql, 'delete ')
            || str_contains($sql, ' insert ')
            || str_contains($sql, ' update ')
            || str_contains($sql, ' delete ')) {
            $writeCount++;
        }
    };

    Event::listen(QueryExecuted::class, $listener);

    $last = null;
    for ($i = 0; $i < 100; $i++) {
        $last = FinancialPositionProjection::for($repairOrder)->toArray();
        expect($last)->toBe($first);
    }

    Event::forget(QueryExecuted::class);

    expect($writeCount)->toBe(0)
        ->and($last['customer_owes_today_cents'])->toBe(12500)
        ->and($last['contract_source'])->toBe(FinancialContractSource::Estimate->value)
        ->and($last['coverage_cents'])->toBe(0);
});
