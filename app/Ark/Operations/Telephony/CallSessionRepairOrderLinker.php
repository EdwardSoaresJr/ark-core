<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;

class CallSessionRepairOrderLinker
{
    public function linkMostRecentOpenRepairOrder(CallSession $session, ?Customer $customer): void
    {
        if ($customer === null || $session->repair_order_id !== null) {
            return;
        }

        $repairOrder = RepairOrder::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($repairOrder === null) {
            return;
        }

        $session->forceFill(['repair_order_id' => $repairOrder->id])->saveQuietly();
    }
}
