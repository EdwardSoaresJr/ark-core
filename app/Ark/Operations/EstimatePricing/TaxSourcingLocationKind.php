<?php

namespace App\Ark\Operations\EstimatePricing;

/**
 * Where the shop is sourcing tax jurisdiction from for this RO.
 * Legal applicability is confirmed by profile selection — not by this enum alone.
 */
enum TaxSourcingLocationKind: string
{
    case Shop = 'shop';
    case CustomerBilling = 'customer_billing';
    case ServiceAddress = 'service_address';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Shop => 'Shop location',
            self::CustomerBilling => 'Customer billing address',
            self::ServiceAddress => 'Service location',
            self::Manual => 'Manual jurisdiction selection',
        };
    }
}
