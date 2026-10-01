<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Shop-facing reference strings for repair orders (e.g. R1522 on documents and handoffs).
 */
final class RepairOrderShopReference
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
}
