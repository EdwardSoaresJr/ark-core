<?php

namespace App\Ark\Operations\Booking;

/**
 * Where a booked service is performed. Mobile and fleet work happen at the customer's address.
 */
enum BookingLocationMode: string
{
    case Shop = 'shop';
    case Mobile = 'mobile';
    case Either = 'either';

    public function label(): string
    {
        return match ($this) {
            self::Shop => 'At the shop',
            self::Mobile => 'At the customer\'s location',
            self::Either => 'Customer chooses',
        };
    }
}
