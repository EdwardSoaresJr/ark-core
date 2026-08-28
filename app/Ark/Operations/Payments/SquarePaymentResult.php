<?php

namespace App\Ark\Operations\Payments;

final class SquarePaymentResult
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $status,
        public readonly int $amountCents,
        public readonly ?int $processingFeeCents = null,
    ) {}

    public function isCompleted(): bool
    {
        return strtoupper($this->status) === 'COMPLETED';
    }
}
