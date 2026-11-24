<?php

namespace App\Ark\Runtime\Preferences;

/**
 * Preference scope for shared collection layouts.
 * Legacy cookie names match {@see UiLayoutModule::legacyCookieName()}.
 */
enum CollectionLayoutModule: string
{
    case Customers = 'customers';
    case Vehicles = 'vehicles';
    case RepairOrders = 'repair_orders';

    public function cookieName(): string
    {
        return match ($this) {
            self::Customers => (string) UiLayoutModule::Customers->legacyCookieName(),
            self::Vehicles => (string) UiLayoutModule::Vehicles->legacyCookieName(),
            self::RepairOrders => (string) UiLayoutModule::RepairOrders->legacyCookieName(),
        };
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
