<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Shop-facing reference strings for PartsTech carts and purchase orders.
 *
 * Repair orders use an R prefix (e.g. R1522). Other prefixes (e.g. W warranty) can be added later.
 */
final class PartsTechShopReference
{
    public const PREFIX_REPAIR = 'R';

    public static function shopNumber(RepairOrder $repairOrder): string
    {
        return $repairOrder->repairOrderId();
    }

    public static function cartReference(RepairOrder $repairOrder, string $prefix = self::PREFIX_REPAIR): string
    {
        return $prefix.self::shopNumber($repairOrder);
    }

    public static function purchaseOrderNumber(RepairOrder $repairOrder): string
    {
        return self::cartReference($repairOrder);
    }

    public static function matchesCartReference(string $cartReference, RepairOrder $repairOrder): bool
    {
        $cartReference = trim($cartReference);

        if ($cartReference === '') {
            return true;
        }

        if ($cartReference === self::cartReference($repairOrder)) {
            return true;
        }

        // Legacy carts created before the R prefix used the numeric shop number only.
        return $cartReference === self::shopNumber($repairOrder);
    }
}
