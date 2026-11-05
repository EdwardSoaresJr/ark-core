<?php

namespace App\Ark\Operations\Booking\Contracts;

/**
 * Extension point for rules Core does not own, such as travel time between mobile stops,
 * route-aware windows, or a mobile unit's own calendar (ARK Shop: mobile #95, fleet #96).
 *
 * Tag an implementation with BookingConstraints::TAG. Every constraint sees each day the form
 * might offer and, in instant mode, each exact start; submission asks again before saving.
 * A constraint that throws is reported and ignored, so booking keeps working when an outside
 * service (maps, routing) is down. Staff still approve requests.
 */
interface BookingAvailabilityConstraint
{
    /**
     * Null to allow the candidate, or a short reason the customer can read ("Outside our service area").
     */
    public function refuse(BookingCandidate $candidate): ?string;
}
