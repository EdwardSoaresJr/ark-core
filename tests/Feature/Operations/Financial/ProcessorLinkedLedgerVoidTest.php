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
