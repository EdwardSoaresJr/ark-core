<?php

namespace App\Ark\Import;

final class LegacyRepairOrderRecord
{
    /** Legacy repair_orders primary key (used for concerns, lines, invoices). */
    public static function internalId(array $legacy): int
    {
        return (int) ($legacy['id'] ?? 0);
    }

    /** Shop-facing RO number shown in ARK v2 (legacy repair_orders.repair_order_id when present). */
    public static function shopNumber(array $legacy): int
    {
        $shopNumber = (int) ($legacy['shop_number'] ?? 0);

        if ($shopNumber > 0) {
            return $shopNumber;
        }

        return self::internalId($legacy);
    }
}
