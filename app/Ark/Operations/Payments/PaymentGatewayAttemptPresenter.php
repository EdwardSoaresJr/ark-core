<?php

namespace App\Ark\Operations\Payments;

use Brick\Money\Money;

final class PaymentGatewayAttemptPresenter
{
    public function __construct(
        private readonly SquareConfiguration $configuration,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAttempt(PaymentGatewayAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'status' => $attempt->status->value,
            'capture_surface' => $attempt->capture_surface->value,
            'amount_cents' => $attempt->amount_cents,
            'amount' => $this->formatCents($attempt->amount_cents),
            'square_checkout_id' => $attempt->square_checkout_id,
            'square_payment_id' => $attempt->square_payment_id,
            'failure_reason' => $attempt->failure_reason,
            'completed_at' => $attempt->completed_at?->toIso8601String(),
            'square' => $this->configuration->publicConfig(),
        ];
    }

    private function formatCents(int $cents): string
    {
        return Money::ofMinor($cents, 'USD')->formatTo('en_US');
    }
}
