<?php

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\VoidLedgerEntryAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGateway;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttemptStatus;
use Illuminate\Support\Str;

test('cash ledger payments can still be voided', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $entry = $repairOrder->ledgerEntries()->where('entry_type', LedgerEntryType::Payment)->firstOrFail();

    app(VoidLedgerEntryAction::class)->execute($entry);

    expect($entry->fresh()->isVoided())->toBeTrue()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(15000);
});

test('processor-settled capture payments cannot be voided as if refunded', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    $entries = app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        $amount,
        PaymentMethod::Card,
        null,
        'capture:test-public',
    );
    $entry = $entries[0];

    PaymentCaptureAttempt::query()->create([
        'public_id' => (string) Str::uuid(),
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'amount_cents' => $amount,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::Succeeded,
        'idempotency_key' => 'core-'.Str::uuid(),
        'provider' => 'square',
        'provider_payment_id' => 'sq_pay_live_1',
        'ledger_entry_id' => $entry->id,
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    expect(fn () => app(VoidLedgerEntryAction::class)->execute($entry->fresh()))
        ->toThrow(RuntimeException::class, 'card processor');

    expect($entry->fresh()->isVoided())->toBeFalse()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('manual external card without a processor capture can still be voided', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    $entries = app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        $amount,
        PaymentMethod::Card,
        null,
        'manual-external-card',
    );
    $entry = $entries[0];

    app(VoidLedgerEntryAction::class)->execute($entry);

    expect($entry->fresh()->isVoided())->toBeTrue()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe($amount);
});

test('pending processor capture cannot be voided as a manual correction', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    $entries = app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        $amount,
        PaymentMethod::Card,
        null,
        'capture:pending-public',
    );
    $entry = $entries[0];

    PaymentCaptureAttempt::query()->create([
        'public_id' => (string) Str::uuid(),
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'amount_cents' => $amount,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::Pending,
        'idempotency_key' => 'core-'.Str::uuid(),
        'provider' => 'square',
        'provider_payment_id' => null,
        'ledger_entry_id' => $entry->id,
        'initiated_at' => now(),
    ]);

    expect(fn () => app(VoidLedgerEntryAction::class)->execute($entry->fresh()))
        ->toThrow(RuntimeException::class, 'card processor');

    expect($entry->fresh()->isVoided())->toBeFalse();
});

test('succeeded capture with missing processor payment id cannot be voided', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    $entries = app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        $amount,
        PaymentMethod::Card,
        null,
        'capture:missing-ref',
    );
    $entry = $entries[0];

    PaymentCaptureAttempt::query()->create([
        'public_id' => (string) Str::uuid(),
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'amount_cents' => $amount,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => PaymentCaptureAttemptStatus::Succeeded,
        'idempotency_key' => 'core-'.Str::uuid(),
        'provider' => 'square',
        'provider_payment_id' => null,
        'ledger_entry_id' => $entry->id,
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    expect(fn () => app(VoidLedgerEntryAction::class)->execute($entry->fresh()))
        ->toThrow(RuntimeException::class, 'card processor');

    expect($entry->fresh()->isVoided())->toBeFalse()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('accepted and reconciliation-required captures cannot be voided', function (PaymentCaptureAttemptStatus $status) {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    $entries = app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        $amount,
        PaymentMethod::Card,
        null,
        'capture:'.$status->value,
    );
    $entry = $entries[0];

    PaymentCaptureAttempt::query()->create([
        'public_id' => (string) Str::uuid(),
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'amount_cents' => $amount,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'status' => $status,
        'idempotency_key' => 'core-'.Str::uuid(),
        'provider' => 'square',
        'provider_payment_id' => $status === PaymentCaptureAttemptStatus::Accepted ? 'sq_pay_accepted' : null,
        'ledger_entry_id' => $entry->id,
        'initiated_at' => now(),
    ]);

    expect(fn () => app(VoidLedgerEntryAction::class)->execute($entry->fresh()))
        ->toThrow(RuntimeException::class);

    expect($entry->fresh()->isVoided())->toBeFalse()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
})->with([
    PaymentCaptureAttemptStatus::Accepted,
    PaymentCaptureAttemptStatus::ReconciliationRequired,
]);

test('duplicate void of an already-voided cash payment is refused', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $entry = $repairOrder->ledgerEntries()->where('entry_type', LedgerEntryType::Payment)->firstOrFail();
    app(VoidLedgerEntryAction::class)->execute($entry);

    expect(fn () => app(VoidLedgerEntryAction::class)->execute($entry->fresh()))
        ->toThrow(RuntimeException::class, 'already voided');

    expect($entry->fresh()->isVoided())->toBeTrue()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(15000);
});

test('legacy gateway payment reference cannot be voided as a ledger-only refund', function () {
    $repairOrder = financialCloseoutRepairOrder();
    app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);
    $amount = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents;

    $entries = app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        $amount,
        PaymentMethod::Card,
        null,
        'legacy-gateway',
    );
    $entry = $entries[0];

    PaymentGatewayAttempt::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'gateway' => PaymentGateway::Square,
        'capture_surface' => PaymentCaptureSurface::Keyed,
        'amount_cents' => $amount,
        'currency' => 'USD',
        'idempotency_key' => 'legacy-'.Str::uuid(),
        'square_payment_id' => 'sq_legacy_pay_1',
        'status' => PaymentGatewayAttemptStatus::Completed,
        'ledger_entry_id' => $entry->id,
        'initiated_at' => now(),
        'completed_at' => now(),
        'public_id' => (string) Str::uuid(),
    ]);

    expect(fn () => app(VoidLedgerEntryAction::class)->execute($entry->fresh()))
        ->toThrow(RuntimeException::class, 'card processor');

    expect($entry->fresh()->isVoided())->toBeFalse()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});
