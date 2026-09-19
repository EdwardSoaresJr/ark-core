<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttemptStatus;
use App\Ark\Operations\Payments\SquareAttemptCompleter;
use Illuminate\Support\Facades\Log;

final class ApplyPaymentCaptureResultAction
{
    public function __construct(
        private readonly SquareAttemptCompleter $completer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apply(PaymentGatewayAttempt $attempt, array $payload): PaymentGatewayAttempt
    {
        $status = strtolower((string) ($payload['status'] ?? ''));
        $providerPaymentId = trim((string) ($payload['provider_payment_id'] ?? ''));
        $amountCents = (int) ($payload['amount_cents'] ?? $attempt->amount_cents);
        $reason = is_string($payload['reason_code'] ?? null) ? (string) $payload['reason_code'] : null;
        $message = is_string($payload['message'] ?? null) ? (string) $payload['message'] : $reason;
        $refs = is_array($payload['provider_refs'] ?? null) ? $payload['provider_refs'] : [];
        $checkoutId = isset($refs['terminal_checkout_id']) ? (string) $refs['terminal_checkout_id'] : null;

        if ($checkoutId !== null && $checkoutId !== '' && $attempt->square_checkout_id === null) {
            $attempt->forceFill(['square_checkout_id' => $checkoutId])->save();
            $attempt = $attempt->refresh();
        }

        return match ($status) {
            'succeeded' => $this->succeed($attempt, $providerPaymentId, $amountCents),
            'failed' => $this->fail($attempt, $message ?? 'Card capture failed.'),
            'cancelled', 'canceled' => $this->cancel($attempt, $message ?? 'Card capture canceled.'),
            default => $attempt->refresh(),
        };
    }

    private function succeed(
        PaymentGatewayAttempt $attempt,
        string $providerPaymentId,
        int $amountCents,
    ): PaymentGatewayAttempt {
        if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
            return $attempt;
        }

        if ($providerPaymentId === '') {
            Log::warning('ark_payments.capture.succeeded_without_provider_id', [
                'attempt_id' => $attempt->id,
            ]);

            return $attempt;
        }

        if ($amountCents !== $attempt->amount_cents) {
            Log::warning('ark_payments.capture.amount_mismatch', [
                'attempt_id' => $attempt->id,
                'expected_cents' => $attempt->amount_cents,
                'reported_cents' => $amountCents,
            ]);

            return $attempt;
        }

        return $this->completer->execute($attempt, $providerPaymentId, $amountCents);
    }

    private function fail(PaymentGatewayAttempt $attempt, string $reason): PaymentGatewayAttempt
    {
        if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
            return $attempt;
        }

        if ($attempt->status->isTerminal()) {
            return $attempt;
        }

        $attempt->forceFill([
            'status' => PaymentGatewayAttemptStatus::Failed,
            'failure_reason' => $reason,
            'completed_at' => now(),
        ])->save();

        return $attempt->refresh();
    }

    private function cancel(PaymentGatewayAttempt $attempt, string $reason): PaymentGatewayAttempt
    {
        if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
            return $attempt;
        }

        if ($attempt->status->isTerminal()) {
            return $attempt;
        }

        $attempt->forceFill([
            'status' => PaymentGatewayAttemptStatus::Canceled,
            'failure_reason' => $reason,
            'completed_at' => now(),
        ])->save();

        return $attempt->refresh();
    }
}
