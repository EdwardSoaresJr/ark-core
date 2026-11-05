<?php

namespace App\Ark\Operations\Booking;

/**
 * Where one appointment or request actually happens. Dispatch and routing read this.
 */
enum ServiceLocationType: string
{
    case Shop = 'shop';
    case CustomerSite = 'customer_site';
    case FleetSite = 'fleet_site';

    public function label(): string
    {
        return match ($this) {
            self::Shop => 'At the shop',
            self::CustomerSite => 'At the customer\'s location',
            self::FleetSite => 'At a fleet yard',
        };
    }

    public function isOnSite(): bool
    {
        return $this !== self::Shop;
    }
}
