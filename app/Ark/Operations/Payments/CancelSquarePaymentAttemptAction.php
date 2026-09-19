<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction;
use App\Ark\Platform\Payments\ArkPaymentsClient;
use App\Ark\Platform\Payments\ManagedPaymentsGate;

final class CancelSquarePaymentAttemptAction
{
    public function __construct(
        private readonly SquarePaymentsClient $square,
        private readonly ArkPaymentsClient $payments,
        private readonly ApplyPaymentCaptureResultAction $apply,
    ) {}

    public function execute(PaymentGatewayAttempt $attempt): PaymentGatewayAttempt
    {
        if ($attempt->status->isTerminal()) {
            return $attempt;
        }

        if (ManagedPaymentsGate::platformCapture() || $attempt->gateway === PaymentGateway::Managed) {
            $result = $this->payments->cancelCapture($attempt->idempotency_key);

            if (($result['ok'] ?? false) === true) {
                return $this->apply->apply($attempt, $result);
            }
        } elseif ($attempt->capture_surface === PaymentCaptureSurface::Terminal && $attempt->square_checkout_id !== null) {
            $this->square->cancelTerminalCheckout($attempt->square_checkout_id);
        }

        $attempt->forceFill([
            'status' => PaymentGatewayAttemptStatus::Canceled,
            'completed_at' => now(),
        ])->save();

        return $attempt->refresh();
    }
}
