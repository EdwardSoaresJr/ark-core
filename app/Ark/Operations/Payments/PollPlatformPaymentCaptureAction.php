<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Platform\Payments\ArkPaymentsClient;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class PollPlatformPaymentCaptureAction
{
    public function __construct(
        private readonly ArkPaymentsClient $payments,
    ) {}

    public function execute(PaymentGatewayAttempt $attempt, ?User $actor = null): PaymentGatewayAttempt
    {
        if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
            return $attempt;
        }

        $result = $this->payments->captureStatus($attempt->idempotency_key);

        if (($result['ok'] ?? false) !== true) {
            Log::warning('ark_payments.poll.unavailable', [
                'attempt_id' => $attempt->id,
                'reason_code' => $result['reason_code'] ?? null,
            ]);

            return $attempt;
        }

        $status = strtolower((string) ($result['status'] ?? ''));

        if ($status === 'succeeded') {
            $attempt->forceFill([
                'status' => PaymentGatewayAttemptStatus::Completed,
                'completed_at' => now(),
            ])->save();
        } elseif ($status === 'failed') {
            $attempt->forceFill([
                'status' => PaymentGatewayAttemptStatus::Failed,
                'failure_reason' => (string) ($result['message'] ?? $result['reason_code'] ?? 'Card capture failed.'),
                'completed_at' => now(),
            ])->save();
        } elseif (in_array($status, ['cancelled', 'canceled'], true)) {
            $attempt->forceFill([
                'status' => PaymentGatewayAttemptStatus::Canceled,
                'failure_reason' => (string) ($result['message'] ?? $result['reason_code'] ?? 'Card capture canceled.'),
                'completed_at' => now(),
            ])->save();
        }

        return $attempt->refresh();
    }
}
