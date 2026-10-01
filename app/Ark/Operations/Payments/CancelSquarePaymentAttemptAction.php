<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Platform\Payments\ArkPaymentsClient;
use App\Ark\Platform\Payments\ManagedPaymentsGate;

final class CancelSquarePaymentAttemptAction
{
    public function __construct(
        private readonly ArkPaymentsClient $payments,
    ) {}

    public function execute(PaymentGatewayAttempt $attempt): PaymentGatewayAttempt
    {
        if ($attempt->status->isTerminal()) {
            return $attempt;
        }

        if (ManagedPaymentsGate::platformCapture() || $attempt->gateway === PaymentGateway::Managed) {
            $this->payments->cancelCapture($attempt->idempotency_key);
        }

        $attempt->forceFill([
            'status' => PaymentGatewayAttemptStatus::Canceled,
            'completed_at' => now(),
        ])->save();

        return $attempt->refresh();
    }
}
