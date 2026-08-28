<?php

namespace App\Ark\Operations\Payments;

final class SquareTerminalCheckoutResult
{
    /**
     * @param  list<string>  $paymentIds
     */
    public function __construct(
        public readonly string $checkoutId,
        public readonly string $status,
        public readonly array $paymentIds = [],
        public readonly ?string $cancelReason = null,
    ) {}

    public function isCompleted(): bool
    {
        return strtoupper($this->status) === 'COMPLETED';
    }

    public function isCanceled(): bool
    {
        return strtoupper($this->status) === 'CANCELED';
    }

    public function isInFlight(): bool
    {
        return in_array(strtoupper($this->status), ['PENDING', 'IN_PROGRESS', 'CANCEL_REQUESTED'], true);
    }
}
