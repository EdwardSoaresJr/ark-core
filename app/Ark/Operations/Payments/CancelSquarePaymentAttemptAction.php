<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;

final class CancelSquarePaymentAttemptAction
{
    public function __construct(
        private readonly SquarePaymentsClient $square,
    ) {}

    public function execute(PaymentGatewayAttempt $attempt): PaymentGatewayAttempt
    {
        if ($attempt->status->isTerminal()) {
            return $attempt;
        }

        if ($attempt->capture_surface === PaymentCaptureSurface::Terminal && $attempt->square_checkout_id !== null) {
            $this->square->cancelTerminalCheckout($attempt->square_checkout_id);
        }

        $attempt->forceFill([
            'status' => PaymentGatewayAttemptStatus::Canceled,
            'completed_at' => now(),
        ])->save();

        return $attempt->refresh();
    }
}
