<?php

namespace App\Ark\Operations\Booking\Contracts;

use App\Ark\Operations\Booking\BookingContext;
use Carbon\CarbonInterface;

/**
 * One option the booking form might offer: a shop-local day, and in instant mode an exact start.
 */
final class BookingCandidate
{
    public function __construct(
        public readonly string $date,
        public readonly BookingContext $context,
        public readonly ?string $period = null,
        public readonly ?CarbonInterface $startsAt = null,
        public readonly ?CarbonInterface $endsAt = null,
    ) {}
}
