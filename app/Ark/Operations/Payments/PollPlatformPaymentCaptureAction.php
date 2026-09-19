<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction;
use App\Ark\Platform\Payments\ArkPaymentsClient;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class PollPlatformPaymentCaptureAction
{
    public function __construct(
        private readonly ArkPaymentsClient $payments,
        private readonly ApplyPaymentCaptureResultAction $apply,
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

        return $this->apply->apply($attempt, $result);
    }
}
