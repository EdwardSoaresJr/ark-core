<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Booking\Contracts\BookingAvailabilityConstraint;
use App\Ark\Operations\Booking\Contracts\BookingCandidate;
use Throwable;

final class BookingConstraints
{
    public const TAG = 'ark.booking.constraints';

    public function refuse(BookingCandidate $candidate): ?string
    {
        foreach ($this->constraints() as $constraint) {
            try {
                $reason = $constraint->refuse($candidate);
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            if (is_string($reason) && trim($reason) !== '') {
                return trim($reason);
            }
        }

        return null;
    }

    /**
     * @return iterable<BookingAvailabilityConstraint>
     */
    private function constraints(): iterable
    {
        yield app(ServiceAreaConstraint::class);

        foreach (app()->tagged(self::TAG) as $constraint) {
            if ($constraint instanceof BookingAvailabilityConstraint) {
                yield $constraint;
            }
        }
    }
}
