<?php

namespace App\Ark\Operations\Booking\Contracts;

/**
 * Extension point for rules Core does not own, such as travel time between mobile stops,
 * route-aware windows, or a mobile unit's own calendar (ARK Shop: mobile #95, fleet #96).
 *
 * Tag an implementation with BookingConstraints::TAG. Every constraint sees each day the form
 * might offer and, in instant mode, each exact start; submission asks again before saving.
 *
 * A constraint is required: when it throws, online booking cannot know whether the work can be
 * done, so no time is confirmed. The customer may still send a request, which staff approve, and the
 * request records which check did not run. A constraint that only refines suggestions implements
 * AdvisoryBookingConstraint instead; when it throws, booking continues as if it had allowed the candidate.
 */
interface BookingAvailabilityConstraint
{
    /**
     * Null to allow the candidate, or a short reason the customer can read ("Outside our service area").
     */
    public function refuse(BookingCandidate $candidate): ?string;

    /**
     * What the check is, for staff ("Travel time"), shown on a request when the check could not run.
     */
    public function label(): string;
}
