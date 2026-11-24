<?php

namespace App\Ark\Runtime\Preferences;

/**
 * Independent List/Cards modules. Preferences never share storage across modules.
 *
 * Job Board is wired now. Customers / Vehicles / Repair Orders are reserved for the
 * collection List/Cards integration slice — do not invent a second client API there.
 */
enum UiLayoutModule: string
{
    case JobBoard = 'job_board';
    case Customers = 'customers';
    case Vehicles = 'vehicles';
    case RepairOrders = 'repair_orders';

    /**
     * Legacy unscoped cookie read during migration (Job Board only today).
     */
    public function legacyCookieName(): ?string
    {
        return match ($this) {
            self::JobBoard => JobBoardLayoutPreference::COOKIE_NAME,
            self::Customers => 'ark_collection_layout_customers',
            self::Vehicles => 'ark_collection_layout_vehicles',
            self::RepairOrders => 'ark_collection_layout_repair_orders',
        };
    }
}
