<?php

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderFinancialPresenter;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('presenter pre-invoice with deposit exposes owe today without claiming settlement paid', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    app(RecordLedgerEntryAction::class)->recordDeposit(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Cash,
        $advisor,
    );

    $repairOrder = $repairOrder->fresh();
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $presenter = app(RepairOrderFinancialPresenter::class)->for($repairOrder, $totals);

    expect($presenter['hasIssuedInvoice'])->toBeFalse()
        ->and($presenter['oweTodayCents'])->toBe(10000)
        ->and($presenter['oweToday'])->toBe('$100.00')
        ->and($presenter['settlementBalanceDueCents'])->toBe(0)
        ->and($presenter['settlementBalanceDue'])->toBeNull()
        ->and($presenter['isPaid'])->toBeFalse()
        ->and($presenter['canRecordPayment'])->toBeFalse()
        ->and($presenter['canWaiveBalance'])->toBeFalse()
        ->and($presenter['canClose'])->toBeFalse()
        ->and($presenter['canPost'])->toBeFalse()
        ->and($presenter['canRecordDeposit'])->toBeTrue()
        ->and($presenter['unappliedDepositsCents'])->toBe(5000)
        ->and($presenter['balanceDue'])->toBeNull();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Owe today')
        ->assertSee('$100.00')
        ->assertDontSee('Settlement paid')
        ->assertSee('Invoice not issued');
});

test('presenter issued unpaid invoice exposes settlement balance and isPaid false', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder);
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $presenter = app(RepairOrderFinancialPresenter::class)->for($repairOrder, $totals);

    expect($presenter['hasIssuedInvoice'])->toBeTrue()
        ->and($presenter['settlementBalanceDueCents'])->toBe($balance->balanceDueCents)
        ->and($presenter['settlementBalanceDueCents'])->toBe(15000)
        ->and($presenter['settlementBalanceDue'])->toBe('$150.00')
        ->and($presenter['isPaid'])->toBeFalse()
        ->and($presenter['canRecordPayment'])->toBeTrue()
        ->and($presenter['canWaiveBalance'])->toBeTrue()
        ->and($presenter['balanceDueCents'])->toBe($presenter['settlementBalanceDueCents'])
        ->and($presenter['balanceDue'])->toBe($presenter['settlementBalanceDue']);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Settlement balance')
        ->assertSee('$150.00')
        ->assertSee('Record Payment');
});

test('presenter approved-work drift keeps named axes and settlement gates', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();

    $concern = $repairOrder->concerns()->first();
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Post-invoice approved add',
        'quantity' => '1.00',
        'unit_price_cents' => 7000,
        'subtotal_cents' => 7000,
        'total_cents' => 7000,
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    $repairOrder = $repairOrder->fresh();

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder);
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $presenter = app(RepairOrderFinancialPresenter::class)->for($repairOrder, $totals);

    expect($presenter['invoiceNeedsRefresh'])->toBeTrue()
        ->and($presenter['invoiceDriftCents'])->not->toBe(0)
        ->and($presenter['settlementBalanceDueCents'])->toBe($balance->balanceDueCents)
        ->and($presenter['oweTodayCents'])->toBe($presenter['customerOwesTodayCents'])
        ->and(array_key_exists('oweToday', $presenter))->toBeTrue()
        ->and(array_key_exists('settlementBalanceDue', $presenter))->toBeTrue()
        ->and($presenter['canRecordPayment'])->toBe($balance->balanceDueCents > 0)
        ->and($presenter['isPaid'])->toBe($balance->isPaid())
        ->and($presenter['balanceDueCents'])->toBe($presenter['settlementBalanceDueCents']);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Settlement balance')
        ->assertSee('Approved work changed');
});

test('presenter fully paid issued invoice exposes zero settlement and isPaid true', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);
    $repairOrder = $repairOrder->fresh();

    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $presenter = app(RepairOrderFinancialPresenter::class)->for($repairOrder, $totals);

    expect($presenter['settlementBalanceDueCents'])->toBe(0)
        ->and($presenter['settlementBalanceDue'])->toBe('$0.00')
        ->and($presenter['isPaid'])->toBeTrue()
        ->and($presenter['canRecordPayment'])->toBeFalse()
        ->and($presenter['canWaiveBalance'])->toBeFalse()
        ->and($presenter['canClose'])->toBeTrue()
        ->and($presenter['canPost'])->toBeTrue()
        ->and($presenter['workflowPosture'])->toBe('paid_ready_to_close');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Settlement balance')
        ->assertSee('$0.00')
        ->assertSee('Paid / ready to close');
});

test('presenter stale payment mirror does not change settlement or isPaid', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();

    $repairOrder->forceFill([
        'payment_status' => RepairOrderPaymentStatus::Paid,
        'paid_at' => now(),
    ])->save();

    $repairOrder = $repairOrder->fresh();
    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder);
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $presenter = app(RepairOrderFinancialPresenter::class)->for($repairOrder, $totals);

    expect($repairOrder->paymentStatus())->toBe(RepairOrderPaymentStatus::Paid)
        ->and($balance->isPaid())->toBeFalse()
        ->and($presenter['isPaid'])->toBeFalse()
        ->and($presenter['settlementBalanceDueCents'])->toBe(15000)
        ->and($presenter['canRecordPayment'])->toBeTrue()
        ->and($presenter['canPost'])->toBeFalse();
});

test('presenter with preloaded balance projection does not add BalanceDueCalculator queries', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $projection = app(BalanceDueCalculator::class)->projectForRepairOrder($repairOrder);
    $presenter = app(RepairOrderFinancialPresenter::class);

    $invoiceQueries = 0;
    $ledgerQueries = 0;
    $listener = function (QueryExecuted $query) use (&$invoiceQueries, &$ledgerQueries): void {
        $sql = strtolower($query->sql);
        if (str_contains($sql, 'estimate_documents')) {
            $invoiceQueries++;
        }
        if (str_contains($sql, 'repair_order_ledger_entries')) {
            $ledgerQueries++;
        }
    };

    Event::listen(QueryExecuted::class, $listener);
    $withPreload = $presenter->for($repairOrder, $totals, $projection);
    $preloadInvoice = $invoiceQueries;
    $preloadLedger = $ledgerQueries;

    $invoiceQueries = 0;
    $ledgerQueries = 0;
    $withoutPreload = $presenter->for($repairOrder, $totals, null);
    $freshInvoice = $invoiceQueries;
    $freshLedger = $ledgerQueries;

    Event::forget(QueryExecuted::class);

    expect($withPreload['settlementBalanceDueCents'])->toBe($withoutPreload['settlementBalanceDueCents'])
        ->and($withPreload['isPaid'])->toBe($withoutPreload['isPaid'])
        ->and($withPreload['oweTodayCents'])->toBe($withoutPreload['oweTodayCents'])
        // Preload skips BalanceDue invoice+ledger reads; F1 + ledger history remain.
        ->and($preloadInvoice)->toBeLessThan($freshInvoice)
        ->and($preloadLedger)->toBeLessThan($freshLedger);
});
