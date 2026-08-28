<?php

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\Financial\RepairOrderFinancialPresenter;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('remaining suggested deposit subtracts unapplied ledger deposits', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit();
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $ledger = app(RecordLedgerEntryAction::class);

    $remainingBefore = $guard->remainingSuggestedDepositCents($repairOrder);
    expect($remainingBefore)->toBeInt()->toBeGreaterThan(0);

    $ledger->recordDeposit($repairOrder, 5000, PaymentMethod::Cash);

    expect($guard->remainingSuggestedDepositCents($repairOrder->fresh()))
        ->toBe($remainingBefore - 5000);
});

test('additional manual deposit is allowed after suggested deposit is on file', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit();
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $ledger = app(RecordLedgerEntryAction::class);

    $remaining = $guard->remainingSuggestedDepositCents($repairOrder);
    expect($remaining)->toBeInt()->toBeGreaterThan(0);

    $ledger->recordDeposit($repairOrder, $remaining, PaymentMethod::Cash);

    expect($guard->suggestedDepositSatisfied($repairOrder->fresh()))->toBeTrue()
        ->and($guard->remainingAllowedDepositCents($repairOrder->fresh()))->toBeGreaterThan(0);

    $guard->validateAmount($repairOrder->fresh(), 100);
});

test('manual deposit amount cannot exceed remaining suggested deposit', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit();
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $ledger = app(RecordLedgerEntryAction::class);

    $remaining = $guard->remainingSuggestedDepositCents($repairOrder);
    $ledger->recordDeposit($repairOrder, 1000, PaymentMethod::Cash);

    $guard->validateAmount($repairOrder->fresh(), $remaining);
})->throws(ValidationException::class);

test('preloaded balance adds zero BalanceDue invoice or ledger queries for remaining and satisfied', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit();
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder);

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

    $remaining = $guard->remainingSuggestedDepositCents($repairOrder, $balance);
    $satisfied = $guard->suggestedDepositSatisfied($repairOrder, $balance);

    Event::forget(QueryExecuted::class);

    expect($invoiceQueries)->toBe(0)
        ->and($ledgerQueries)->toBe(0)
        ->and($remaining)->toBe($guard->remainingSuggestedDepositCents($repairOrder))
        ->and($satisfied)->toBe($guard->suggestedDepositSatisfied($repairOrder));
});

test('preload and fallback agree for deposit remaining across deposit states', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit();
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $ledger = app(RecordLedgerEntryAction::class);
    $balances = app(BalanceDueCalculator::class);

    $assertParity = function () use ($repairOrder, $guard, $balances): void {
        $ro = $repairOrder->fresh();
        $balance = $balances->forRepairOrder($ro);
        $with = $guard->remainingSuggestedDepositCents($ro, $balance);
        $without = $guard->remainingSuggestedDepositCents($ro);
        $satisfiedWith = $guard->suggestedDepositSatisfied($ro, $balance);
        $satisfiedWithout = $guard->suggestedDepositSatisfied($ro);

        expect($with)->toBe($without)
            ->and($satisfiedWith)->toBe($satisfiedWithout)
            ->and($satisfiedWith)->toBe($with !== null && $with === 0);
    };

    // No deposits
    $assertParity();
    $fullRemaining = $guard->remainingSuggestedDepositCents($repairOrder);
    expect($fullRemaining)->toBeInt()->toBeGreaterThan(5000);

    // Partial deposit
    $ledger->recordDeposit($repairOrder, 5000, PaymentMethod::Cash);
    $assertParity();
    expect($guard->remainingSuggestedDepositCents($repairOrder->fresh()))->toBe($fullRemaining - 5000);

    // Suggested deposit fully satisfied
    $ledger->recordDeposit($repairOrder->fresh(), $fullRemaining - 5000, PaymentMethod::Cash);
    $assertParity();
    expect($guard->suggestedDepositSatisfied($repairOrder->fresh()))->toBeTrue()
        ->and($guard->remainingSuggestedDepositCents($repairOrder->fresh()))->toBe(0);

    // Excess / additional unapplied deposit beyond suggested
    $ledger->recordDeposit($repairOrder->fresh(), 2500, PaymentMethod::Cash);
    $assertParity();
    expect($guard->remainingSuggestedDepositCents($repairOrder->fresh()))->toBe(0)
        ->and($guard->suggestedDepositSatisfied($repairOrder->fresh()))->toBeTrue();
});

test('preload and fallback agree after invoice when deposit recording is unavailable', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();

    $guard = app(RepairOrderDepositRecordingGuard::class);
    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder);
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $presenter = app(RepairOrderFinancialPresenter::class)->for($repairOrder, $totals);

    expect($guard->remainingSuggestedDepositCents($repairOrder, $balance))
        ->toBe($guard->remainingSuggestedDepositCents($repairOrder))
        ->and($guard->suggestedDepositSatisfied($repairOrder, $balance))
        ->toBe($guard->suggestedDepositSatisfied($repairOrder))
        ->and($presenter['canRecordDeposit'])->toBeFalse()
        ->and($presenter['hasIssuedInvoice'])->toBeTrue()
        ->and($presenter['settlementBalanceDueCents'])->toBe($balance->balanceDueCents)
        ->and($presenter['isPaid'])->toBeFalse();
});

test('validateAmount still uses live settlement when no projection is supplied', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit();
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $ledger = app(RecordLedgerEntryAction::class);

    $remaining = $guard->remainingSuggestedDepositCents($repairOrder);
    $ledger->recordDeposit($repairOrder, $remaining, PaymentMethod::Cash);

    $remainingBalance = $guard->remainingAllowedDepositCents($repairOrder->fresh());
    expect($remainingBalance)->toBeGreaterThan(0);

    $guard->validateAmount($repairOrder->fresh(), $remainingBalance + 100);
})->throws(ValidationException::class);

test('presenter deposit remaining and gates match preload and fallback paths', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = repairOrderWithSuggestedPartDeposit();
    $ledger = app(RecordLedgerEntryAction::class);
    $ledger->recordDeposit($repairOrder, 5000, PaymentMethod::Cash);
    $repairOrder = $repairOrder->fresh();

    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $projection = app(BalanceDueCalculator::class)->projectForRepairOrder($repairOrder);
    $presenter = app(RepairOrderFinancialPresenter::class);

    $withPreload = $presenter->for($repairOrder, $totals, $projection);
    $withoutPreload = $presenter->for($repairOrder, $totals, null);

    expect($withPreload['remainingSuggestedDepositCents'])->toBe($withoutPreload['remainingSuggestedDepositCents'])
        ->and($withPreload['suggestedDepositSatisfied'])->toBe($withoutPreload['suggestedDepositSatisfied'])
        ->and($withPreload['oweTodayCents'])->toBe($withoutPreload['oweTodayCents'])
        ->and($withPreload['settlementBalanceDueCents'])->toBe($withoutPreload['settlementBalanceDueCents'])
        ->and($withPreload['canRecordDeposit'])->toBe($withoutPreload['canRecordDeposit'])
        ->and($withPreload['canRecordPayment'])->toBe($withoutPreload['canRecordPayment'])
        ->and($withPreload['canClose'])->toBe($withoutPreload['canClose'])
        ->and($withPreload['canPost'])->toBe($withoutPreload['canPost'])
        ->and($withPreload['suggestedDepositSatisfied'])->toBe(
            $withPreload['remainingSuggestedDepositCents'] !== null
            && $withPreload['remainingSuggestedDepositCents'] === 0
        );
});
