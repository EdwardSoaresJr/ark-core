<?php

namespace Database\Seeders\Concerns;

use App\Ark\Operations\RepairOrders\RepairOrder;

trait AssignsRepairOrderShopNumber
{
    protected function assignRepairOrderShopNumber(RepairOrder $repairOrder): RepairOrder
    {
        if ($repairOrder->repair_order_id !== null) {
            return $repairOrder;
        }

        $repairOrder->forceFill([
            'repair_order_id' => RepairOrder::nextShopRepairOrderId(),
        ])->saveQuietly();

        return $repairOrder->refresh();
    }
}
