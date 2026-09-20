<?php

namespace App\Ark\Operations\Appointments;

/**
 * Disposable schedule entry projection — never appointment authority.
 *
 * Conversation / Hub / RO / Vehicle / Lead all resolve into this same shape.
 */
final readonly class ScheduleContext
{
    public function __construct(
        public ?int $customerId = null,
        public ?int $vehicleId = null,
        public ?int $repairOrderId = null,
        public ?int $conversationId = null,
        public ?int $leadId = null,
        public ?string $defaultConcern = null,
        public ?string $contactName = null,
        public ?string $contactPhone = null,
        public ?string $contactEmail = null,
        public ?string $vehicleContextLabel = null,
        public bool $needsCustomerIdentification = false,
        public string $entry = 'blank',
    ) {}

    public function hasCustomer(): bool
    {
        return $this->customerId !== null;
    }

    public function hasBookingContact(): bool
    {
        return filled($this->contactName) || filled($this->contactPhone) || filled($this->contactEmail);
    }

    /** True when the create form can proceed without first finding a Customer. */
    public function canScheduleWithoutCustomer(): bool
    {
        return $this->hasCustomer()
            || $this->hasBookingContact()
            || $this->entry === 'blank'
            || $this->leadId !== null
            || $this->conversationId !== null;
    }
}
