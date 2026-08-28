<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Accountable party for a Repair Action.
 * R1: Technician only. Team / vendor / unassigned / training pair are later owner types.
 */
enum RepairActionOwnerType: string
{
    case Technician = 'technician';

    public function label(): string
    {
        return match ($this) {
            self::Technician => 'Technician',
        };
    }
}
