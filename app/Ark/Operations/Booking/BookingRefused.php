<?php

namespace App\Ark\Operations\Booking;

use RuntimeException;

/**
 * Core would not take the booking: the day or time is gone, the area is not served, the shop is full.
 * The message is written for the customer; the field says which choice to change.
 */
final class BookingRefused extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
