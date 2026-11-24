<?php

namespace App\Ark\Runtime\Preferences;

/**
 * Preference scope for shared collection layouts.
 * Cookie keys stay module-scoped until durable user+tenant storage lands.
 */
enum CollectionLayoutModule: string
{
    case Customers = 'customers';
    case Vehicles = 'vehicles';
    case RepairOrders = 'repair_orders';

    public function cookieName(): string
    {
        return 'ark_collection_layout_'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Customers => 'Customers',
            self::Vehicles => 'Vehicles',
            self::RepairOrders => 'Repair Orders',
        };
    }
}
