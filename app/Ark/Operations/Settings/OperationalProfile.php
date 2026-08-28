<?php

namespace App\Ark\Operations\Settings;

/**
 * Onboarding profile — configuration defaults only.
 * Not a product. Not an authority. SoloShopOperations stays staff-derived.
 */
enum OperationalProfile: string
{
    case RepairShop = 'repair_shop';
    case SoloShop = 'solo_shop';
    case MobileMechanic = 'mobile_mechanic';

    public function label(): string
    {
        return match ($this) {
            self::RepairShop => 'Repair Shop',
            self::SoloShop => 'Solo Shop',
            self::MobileMechanic => 'Mobile Mechanic',
        };
    }

    public function summary(): string
    {
        return match ($this) {
            self::RepairShop => 'Bays, appointments, and printing-friendly defaults for a full shop floor.',
            self::SoloShop => 'Owner-operator defaults — light training gate, appointments off until you turn them on.',
            self::MobileMechanic => 'Route-friendly scheduling on; label printing off; waiting-here intake default.',
        };
    }
}
