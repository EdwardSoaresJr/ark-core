<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Booking\Contracts\BookingProof;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadIngressContext;

/**
 * What a booking product hands Core when a customer sends a booking. The product has already
 * validated its own form, its catalog choices, and the customer's identity; Core checks scheduling.
 */
final class OnlineBookingRequest
{
    /**
     * @param  list<array{year?: int|null, make?: string|null, model?: string|null, vin?: string|null}>  $vehicles
     * @param  list<array{label: string, answer: string}>  $answers
     * @param  array<string, scalar|null>  $context  where it came from (page, host, embedded) and product details for staff
     * @param  list<string>  $spamSignals
     * @param  BookingProof|null  $proof  spent inside the booking transaction; a resubmitted submission key returns the first outcome without it
     */
    public function __construct(
        public readonly BookingContext $booking,
        public readonly string $contactName,
        public readonly string $contactPhone,
        public readonly ?string $contactEmail,
        public readonly string $preferredDate,
        public readonly ?string $preferredPeriod = null,
        public readonly ?string $preferredTime = null,
        public readonly string $concern = '',
        public readonly array $answers = [],
        public readonly array $vehicles = [],
        public readonly LeadContactPreference $contactPreference = LeadContactPreference::Text,
        public readonly bool $unsafeToDrive = false,
        public readonly bool $needsTow = false,
        public readonly ?int $customerId = null,
        public readonly ?int $vehicleId = null,
        public readonly ?string $submissionKey = null,
        public readonly array $context = [],
        public readonly ?LeadIngressContext $ingress = null,
        public readonly bool $spam = false,
        public readonly array $spamSignals = [],
        public readonly ?BookingProof $proof = null,
    ) {}
}
