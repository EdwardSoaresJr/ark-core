<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Mobile API routes bind RepairOrder by shop number ({repair_order_id} column).
 */
final class MobileRepairOrderRouteId
{
    public static function normalize(?int $id): ?int
    {
        if ($id === null) {
            return null;
        }

        $shopNumber = RepairOrder::query()
            ->whereKey($id)
            ->orWhere('repair_order_id', $id)
            ->value('repair_order_id');

        return $shopNumber !== null ? (int) $shopNumber : $id;
    }

    /**
     * Accepts shop RO number ({repair_order_id} column) or internal PK; returns internal PK for authority writes.
     */
    public static function resolveInternalId(?int $shopNumberOrInternalId): ?int
    {
        if ($shopNumberOrInternalId === null) {
            return null;
        }

        $internalId = RepairOrder::query()
            ->whereKey($shopNumberOrInternalId)
            ->orWhere('repair_order_id', $shopNumberOrInternalId)
            ->value('id');

        return $internalId !== null ? (int) $internalId : null;
    }
}
