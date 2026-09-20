<?php

namespace App\Ark\Operations\Events;

/**
 * Signed business event verbs — product language, not transport enums.
 *
 * @see docs/mobile/event-contracts-v1.md
 */
enum EventContract: string
{
    case PaymentReceived = 'payment_received';

    public function label(): string
    {
        return match ($this) {
            self::PaymentReceived => 'Payment Received',
        };
    }

    public function authoritativeDomain(): string
    {
        return match ($this) {
            self::PaymentReceived => 'Financial',
        };
    }

    /**
     * Transport event name for persisted authority facts.
     */
    public function operationalEventName(): OperationalEventName
    {
        return match ($this) {
            self::PaymentReceived => OperationalEventName::RepairOrderPaymentReceived,
        };
    }
}
