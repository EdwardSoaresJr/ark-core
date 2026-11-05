<?php

namespace App\Ark\Operations\Booking;

/**
 * Whether an online booking asks the shop for a time or takes a confirmed appointment.
 */
enum BookingMode: string
{
    case Request = 'request';
    case Instant = 'instant';

    public function label(): string
    {
        return match ($this) {
            self::Request => 'Customers request a time; staff confirm it',
            self::Instant => 'Customers book an open time; it is confirmed at once',
        };
    }
}
