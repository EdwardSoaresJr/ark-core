<?php

namespace App\Ark\Operations\Appointments;

/**
 * Disposable schedule entry projection — never appointment authority.
 *
 * Conversation / Hub / RO / Vehicle all resolve into this same shape.
 */
final readonly class ScheduleContext
{
    public function __construct(
        public ?int $customerId = null,
        public ?int $vehicleId = null,
        public ?int $repairOrderId = null,
        public ?int $conversationId = null,
        public ?string $defaultConcern = null,
        public bool $needsCustomerIdentification = false,
        public string $entry = 'blank',
    ) {}

    public function hasCustomer(): bool
    {
        return $this->customerId !== null;
    }
}
