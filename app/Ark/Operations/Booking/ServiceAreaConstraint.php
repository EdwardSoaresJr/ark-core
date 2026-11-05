<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Booking\Contracts\BookingAvailabilityConstraint;
use App\Ark\Operations\Booking\Contracts\BookingCandidate;

/**
 * Core's service area: on-site work only at the postal codes the shop lists. No list, no limit.
 * Without a postal code yet nothing is refused; submission requires the address for on-site work.
 * Radius and drive-time areas belong to a tagged constraint that knows geography.
 */
final class ServiceAreaConstraint implements BookingAvailabilityConstraint
{
    public function refuse(BookingCandidate $candidate): ?string
    {
        if (! $candidate->context->location->isOnSite() || $candidate->context->postalCode() === null) {
            return null;
        }

        $area = BookingSettings::forShop()['service_area'];

        if (BookingSettings::postalCodeMatches($area['postal_codes'], $candidate->context->postalCode())) {
            return null;
        }

        return $area['message'] !== ''
            ? $area['message']
            : 'That address is outside our service area. Call us and we will see what we can do.';
    }

    public function label(): string
    {
        return 'Service area';
    }
}
