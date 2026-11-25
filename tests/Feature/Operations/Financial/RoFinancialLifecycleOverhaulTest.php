<?php

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\Financial\RepairOrderFinancialPresenter;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('in-progress RO accepts full authorized advance in one deposit while suggested policy is active', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = repairOrderWithSuggestedPartDeposit(RepairOrderStatus::InProgress);
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $ledger = app(RecordLedgerEntryAction::class);

    $suggested = $guard->remainingSuggestedDepositCents($repairOrder);
    $allowed = $guard->remainingAllowedDepositCents($repairOrder);

    expect($suggested)->toBeInt()->toBeGreaterThan(0)
        ->and($allowed)->toBeGreaterThan($suggested);

    $entry = $ledger->recordDeposit($repairOrder, $allowed, PaymentMethod::Cash);
    $repairOrder = $repairOrder->fresh();
    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder,
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    );

    expect($entry->amount_cents)->toBe($allowed)
        ->and($presenter['canRecordManualDeposit'])->toBeFalse()
        ->and($presenter['oweTodayCents'])->toBe(0)
        ->and($repairOrder->status->is(RepairOrderStatus::InProgress))->toBeTrue()
        ->and($presenter['hasIssuedInvoice'])->toBeFalse();
});

test('waiting-parts and completed ROs accept deposits before invoice', function (RepairOrderStatus $status) {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderWithSuggestedPartDeposit($status);
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $amount = min(5000, $guard->remainingAllowedDepositCents($repairOrder));

    expect($amount)->toBeGreaterThan(0);

    app(RecordLedgerEntryAction::class)->recordDeposit($repairOrder, $amount, PaymentMethod::Cash);

    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder->fresh(),
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh()),
    );

    expect($presenter['canRecordDeposit'])->toBeTrue()
        ->and($presenter['unappliedDepositsCents'])->toBe($amount)
        ->and($presenter['hasIssuedInvoice'])->toBeFalse();
})->with([
    RepairOrderStatus::WaitingParts,
    RepairOrderStatus::Completed,
]);

test('final invoice can be generated from completed without ready pickup', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Completed);
    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder,
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    );

    expect($presenter['canGenerateInvoice'])->toBeTrue()
        ->and($presenter['invoiceBlockingReason'])->toBeNull()
        ->and($presenter['workflowPosture'])->toBe('ready_for_final_invoice');

    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $repairOrder = $repairOrder->fresh();
    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder,
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    );

    expect($invoice)->not->toBeNull()
        ->and($presenter['hasIssuedInvoice'])->toBeTrue()
        ->and($presenter['workflowPosture'])->toBe('invoice_issued')
        ->and($repairOrder->status->is(RepairOrderStatus::Completed))->toBeTrue();
});

test('final invoice still refused while in progress', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::InProgress);
    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder,
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    );

    expect($presenter['canGenerateInvoice'])->toBeFalse()
        ->and($presenter['invoiceBlockingReason'])->toContain('Completed or Ready for Pickup');

    expect(fn () => app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder))
        ->toThrow(RuntimeException::class);
});

test('deposits apply when final invoice is issued after advances', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::InProgress);
    $ledger = app(RecordLedgerEntryAction::class);
    $totals = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder)->totalCents();
    $first = (int) floor($totals / 5);
    $second = (int) floor($totals / 2);

    $ledger->recordDeposit($repairOrder, $first, PaymentMethod::Cash);
    $ledger->recordDeposit($repairOrder->fresh(), $second, PaymentMethod::Check);

    $repairOrder->forceFill(['status' => RepairOrderStatus::ReadyPickup])->save();
    issueFinalInvoiceFor($repairOrder->fresh());

    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder->fresh(),
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh()),
    );

    expect($presenter['hasIssuedInvoice'])->toBeTrue()
        ->and($presenter['depositsAppliedCents'])->toBe($first + $second)
        ->and($presenter['settlementBalanceDueCents'])->toBe($totals - $first - $second)
        ->and($presenter['canRecordPayment'])->toBeTrue()
        ->and($presenter['paymentWorkspaceMode'])->toBe('invoice');
});

test('lifecycle menus hide invoiced and ready for work without deleting transitions', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $projection = RepairOrderLifecycleSelectProjection::forCatalogTargets($repairOrder, $advisor);
    $values = collect($projection->statusOptions)->pluck('value')->all();

    expect($values)->not->toContain(RepairOrderStatus::Invoiced->value)
        ->and($values)->not->toContain(RepairOrderStatus::ReadyForWork->value)
        ->and($values)->toContain(RepairOrderStatus::InProgress->value);
});

test('presenter exposes deposit blocking reason when nothing can be collected', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderWithSuggestedPartDeposit(RepairOrderStatus::InProgress);
    $allowed = app(RepairOrderDepositRecordingGuard::class)->remainingAllowedDepositCents($repairOrder);
    app(RecordLedgerEntryAction::class)->recordDeposit($repairOrder, $allowed, PaymentMethod::Cash);

    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder->fresh(),
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh()),
    );

    expect($presenter['canRecordManualDeposit'])->toBeFalse()
        ->and($presenter['depositBlockingReason'])->toBeString()->not->toBeEmpty();
});

test('over-collection beyond authorized owe today is still refused', function () {
    $repairOrder = repairOrderWithSuggestedPartDeposit(RepairOrderStatus::InProgress);
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $allowed = $guard->remainingAllowedDepositCents($repairOrder);

    expect(fn () => $guard->validateAmount($repairOrder, $allowed + 1))
        ->toThrow(ValidationException::class);
});

test('workspace defaults suggested five hundred while allowing full twenty five hundred authorized advance', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderWithTwentyFiveHundredAuthorizedFiveHundredSuggested();
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder,
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    );

    expect($guard->remainingSuggestedDepositCents($repairOrder))->toBe(50000)
        ->and($guard->remainingAllowedDepositCents($repairOrder))->toBe(250000)
        ->and($presenter['remainingSuggestedDepositDecimal'])->toBe('500.00')
        ->and($presenter['remainingCollectableDepositDecimal'])->toBe('2500.00')
        ->and($presenter['canRecordManualDeposit'])->toBeTrue()
        ->and($presenter['hasIssuedInvoice'])->toBeFalse();

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Collect deposit / advance', false)
        ->assertSee('data-payment-workspace-suggested="500.00"', false)
        ->assertSee('data-payment-workspace-max="2500.00"', false)
        ->assertSee('Suggested deposit remaining: $500.00 (default only — full authorized amount is allowed).', false)
        ->getContent();

    expect($html)->toContain('Up to $2500.00 remaining on authorized work.');

    $entry = app(RecordLedgerEntryAction::class)->recordDeposit(
        $repairOrder,
        250000,
        PaymentMethod::Cash,
    );
    $repairOrder = $repairOrder->fresh();
    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder,
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder),
    );

    expect($entry->amount_cents)->toBe(250000)
        ->and($entry->entry_type->value)->toBe('deposit')
        ->and($presenter['oweTodayCents'])->toBe(0)
        ->and($presenter['canRecordManualDeposit'])->toBeFalse()
        ->and($presenter['hasIssuedInvoice'])->toBeFalse()
        ->and($repairOrder->status->is(RepairOrderStatus::InProgress))->toBeTrue();

    expect(fn () => $guard->validateAmount($repairOrder, 1))
        ->toThrow(ValidationException::class);
});

test('exact twenty five hundred one cent over authorized is refused while partial advances accumulate', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderWithTwentyFiveHundredAuthorizedFiveHundredSuggested();
    $guard = app(RepairOrderDepositRecordingGuard::class);
    $ledger = app(RecordLedgerEntryAction::class);

    expect(fn () => $guard->validateAmount($repairOrder, 250001))
        ->toThrow(ValidationException::class);

    $ledger->recordDeposit($repairOrder, 100000, PaymentMethod::Cash);
    expect($guard->remainingAllowedDepositCents($repairOrder->fresh()))->toBe(150000);

    $ledger->recordDeposit($repairOrder->fresh(), 150000, PaymentMethod::Cash);
    $repairOrder = $repairOrder->fresh();

    expect($guard->remainingAllowedDepositCents($repairOrder))->toBe(0)
        ->and(RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('entry_type', LedgerEntryType::Deposit)
            ->sum('amount_cents'))->toBe(250000);
});
