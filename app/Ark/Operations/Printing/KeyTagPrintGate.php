<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;

final class KeyTagPrintGate
{
    public const NONE = 'none';

    public const MILEAGE_IN = 'in';

    public static function requirement(): string
    {
        $value = (string) (ShopSettings::current()->key_tag_mileage_requirement ?? self::NONE);

        return $value === self::MILEAGE_IN ? self::MILEAGE_IN : self::NONE;
    }

    public static function blockedReason(?RepairOrder $repairOrder): ?string
    {
        if ($repairOrder === null || self::requirement() !== self::MILEAGE_IN) {
            return null;
        }

        if ($repairOrder->mileage_in !== null) {
            return null;
        }

        return 'Enter mileage in before printing the key tag.';
    }
}
