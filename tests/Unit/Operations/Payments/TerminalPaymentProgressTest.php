<?php

use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\Payments\Capture\TerminalPaymentProgress;

function terminalAttempt(PaymentCaptureAttemptStatus $status, array $overrides = []): PaymentCaptureAttempt
{
    $attempt = new PaymentCaptureAttempt(array_merge([
        'capture_method' => PaymentCaptureMethod::Terminal,
        'status' => $status,
        'amount_cents' => 43538,
        'currency' => 'USD',
        'provider_payment_id' => null,
        'failure_reason' => null,
    ], $overrides));

    return $attempt;
}

test('a terminal checkout with no payment yet is waiting on the customer', function () {
    $progress = TerminalPaymentProgress::describe(terminalAttempt(PaymentCaptureAttemptStatus::Pending));

    expect($progress['phase'])->toBe('waiting')
        ->and($progress['headline'])->toBe('Waiting for customer')
        ->and($progress['note'])->toBe('Payment sent to reader')
        ->and($progress['detail'])->toBe('Waiting for the customer to complete payment.')
        ->and($progress['spinning'])->toBeTrue()
        ->and($progress['blocks_form'])->toBeTrue()
        ->and(TerminalPaymentProgress::flash(terminalAttempt(PaymentCaptureAttemptStatus::Pending)))->toBeNull();
});

test('a terminal payment id means the customer has acted', function () {
    $progress = TerminalPaymentProgress::describe(terminalAttempt(PaymentCaptureAttemptStatus::Pending, [
        'provider_payment_id' => 'pay_1',
    ]));

    expect($progress['phase'])->toBe('processing')
        ->and($progress['headline'])->toBe('Processing payment')
        ->and($progress['note'])->toBe('The customer has started payment.')
        ->and($progress['detail'])->toBe('Waiting for the result.');
});

test('terminal results stay on the attempt until the ledger is the record', function () {
    expect(TerminalPaymentProgress::describe(terminalAttempt(PaymentCaptureAttemptStatus::Succeeded))['headline'])->toBe('Payment approved')
        ->and(TerminalPaymentProgress::flash(terminalAttempt(PaymentCaptureAttemptStatus::Succeeded)))->toBe('Payment approved. The payment is recorded.')
        ->and(TerminalPaymentProgress::describe(terminalAttempt(PaymentCaptureAttemptStatus::Cancelled))['headline'])->toBe('Payment canceled')
        ->and(TerminalPaymentProgress::describe(terminalAttempt(PaymentCaptureAttemptStatus::Failed, [
            'failure_reason' => 'declined',
        ]))['headline'])->toBe('Payment declined')
        ->and(TerminalPaymentProgress::describe(terminalAttempt(PaymentCaptureAttemptStatus::Failed, [
            'failure_reason' => 'missing_device',
        ]))['headline'])->toBe('Reader unavailable');
});

test('keyed capture does not get a reader wait state', function () {
    $attempt = terminalAttempt(PaymentCaptureAttemptStatus::Pending, [
        'capture_method' => PaymentCaptureMethod::Keyed,
    ]);

    expect(TerminalPaymentProgress::describe($attempt))->toBeNull()
        ->and(TerminalPaymentProgress::flash($attempt))->toBeNull();
});
