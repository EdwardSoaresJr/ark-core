<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CallSessionTimeline
{
    /**
     * @return EloquentCollection<int, CallSession>
     */
    public function forCustomer(Customer $customer, int $limit = 12): EloquentCollection
    {
        return CallSession::query()
            ->where('customer_id', $customer->id)
            ->with(['owner', 'repairOrder'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Calls explicitly linked to this repair order — no customer time-window inference.
     *
     * @return EloquentCollection<int, CallSession>
     */
    public function forRepairOrder(RepairOrder $repairOrder, int $limit = 12): EloquentCollection
    {
        return CallSession::query()
            ->where('repair_order_id', $repairOrder->id)
            ->with(['owner', 'repairOrder'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
