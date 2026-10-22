<?php

use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction;
use App\Ark\Operations\Payments\Capture\InitiatePaymentCaptureAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);

    $this->repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($this->repairOrder);
    $this->amount = $this->repairOrder->fresh()->balanceDue()->balanceDueCents;
});

function inFlightCapture(RepairOrder $repairOrder, User $advisor, int $amount, PaymentCaptureAttemptStatus $status = PaymentCaptureAttemptStatus::Pending): PaymentCaptureAttempt
{
    $publicId = (string) Str::uuid();

    return PaymentCaptureAttempt::query()->create([
        'public_id' => $publicId,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'amount_cents' => $amount,
        'currency' => 'USD',
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Terminal,
        'status' => $status,
        'idempotency_key' => 'core-'.$publicId,
        'device_ref' => 'reader-1',
        'initiated_by' => $advisor->id,
        'initiated_at' => now(),
    ]);
}

function confirmedCapture(int $amount): array
{
    return [
        'status' => PaymentCaptureAttemptStatus::Succeeded->value,
        'capture_id' => 'capture-1',
        'provider' => 'processor',
        'provider_payment_id' => 'payment-1',
        'amount_cents' => $amount,
    ];
}

function closeRepairOrder(RepairOrder $repairOrder): void
{
    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->save();
}

function ledgerCount(RepairOrder $repairOrder): int
{
    return RepairOrderLedgerEntry::query()->where('repair_order_id', $repairOrder->id)->count();
}

test('a capture in flight when the order closed records once and the order stays closed', function () {
    $attempt = inFlightCapture($this->repairOrder, $this->advisor, $this->amount);
    closeRepairOrder($this->repairOrder);

    $applied = app(ApplyPaymentCaptureResultAction::class)->apply($attempt, confirmedCapture($this->amount));

    $entry = RepairOrderLedgerEntry::query()->findOrFail($applied->ledger_entry_id);
    expect($applied->status)->toBe(PaymentCaptureAttemptStatus::Succeeded)
        ->and($entry->repair_order_id)->toBe($this->repairOrder->id)
        ->and($entry->amount_cents)->toBe($this->amount)
        ->and(ledgerCount($this->repairOrder))->toBe(1)
        ->and($this->repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::Closed->value)
        ->and($this->repairOrder->fresh()->balanceDue()->balanceDueCents)->toBe(0);
});

test('repeated confirmation of the same capture records once', function () {
    $attempt = inFlightCapture($this->repairOrder, $this->advisor, $this->amount);
    closeRepairOrder($this->repairOrder);

    foreach (range(1, 3) as $repeat) {
        app(ApplyPaymentCaptureResultAction::class)->apply($attempt->fresh(), confirmedCapture($this->amount));
    }

    expect(ledgerCount($this->repairOrder))->toBe(1)
        ->and($attempt->fresh()->status)->toBe(PaymentCaptureAttemptStatus::Succeeded);
});

test('a capture held for reconciliation can still complete after the order closed', function () {
    $attempt = inFlightCapture($this->repairOrder, $this->advisor, $this->amount, PaymentCaptureAttemptStatus::ReconciliationRequired);
    closeRepairOrder($this->repairOrder);

    app(ApplyPaymentCaptureResultAction::class)->apply($attempt, confirmedCapture($this->amount));

    expect(ledgerCount($this->repairOrder))->toBe(1);
});

test('a failed or cancelled capture cannot be revived into money on a closed order', function (PaymentCaptureAttemptStatus $finished) {
    $attempt = inFlightCapture($this->repairOrder, $this->advisor, $this->amount, $finished);
    closeRepairOrder($this->repairOrder);

    expect(fn () => app(ApplyPaymentCaptureResultAction::class)->apply($attempt, confirmedCapture($this->amount)))
        ->toThrow(HttpException::class);

    expect(ledgerCount($this->repairOrder))->toBe(0)
        ->and($attempt->fresh()->status)->toBe($finished);
})->with([
    'failed' => PaymentCaptureAttemptStatus::Failed,
    'cancelled' => PaymentCaptureAttemptStatus::Cancelled,
]);

test('failed and cancelled results never record money on a closed order', function (PaymentCaptureAttemptStatus $result) {
    $attempt = inFlightCapture($this->repairOrder, $this->advisor, $this->amount);
    closeRepairOrder($this->repairOrder);

    $applied = app(ApplyPaymentCaptureResultAction::class)->apply($attempt, ['status' => $result->value]);

    expect($applied->status)->toBe($result)
        ->and(ledgerCount($this->repairOrder))->toBe(0);
})->with([
    'failed' => PaymentCaptureAttemptStatus::Failed,
    'cancelled' => PaymentCaptureAttemptStatus::Cancelled,
]);

test('a confirmed amount that disagrees still needs reconciliation on a closed order', function () {
    $attempt = inFlightCapture($this->repairOrder, $this->advisor, $this->amount);
    closeRepairOrder($this->repairOrder);

    $applied = app(ApplyPaymentCaptureResultAction::class)->apply($attempt, confirmedCapture($this->amount + 100));

    expect($applied->status)->toBe(PaymentCaptureAttemptStatus::ReconciliationRequired)
        ->and(ledgerCount($this->repairOrder))->toBe(0);
});

test('a new capture cannot start on a closed order', function () {
    closeRepairOrder($this->repairOrder);

    expect(fn () => app(InitiatePaymentCaptureAction::class)->execute($this->repairOrder->fresh(), $this->advisor, [
        'amount_cents' => $this->amount,
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Terminal,
        'device_ref' => 'reader-1',
    ]))->toThrow(HttpException::class);

    expect(PaymentCaptureAttempt::query()->count())->toBe(0)
        ->and(ledgerCount($this->repairOrder))->toBe(0);
});

test('a manual payment on a closed order is not recorded', function () {
    closeRepairOrder($this->repairOrder);
    $repairOrder = $this->repairOrder->fresh();

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
    ])->assertStatus(423);

    expect(ledgerCount($this->repairOrder))->toBe(0);
});
