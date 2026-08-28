<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Collection;

final class WorkboardTriageRepairOrderQuery
{
    /**
     * @return Collection<int, RepairOrder>
     */
    public function forAdvisor(): Collection
    {
        return RepairOrder::query()
            ->with([
                'customer',
                'vehicle',
                'communicationEvents:id,repair_order_id,event_type,channel,direction,summary,occurred_at,created_at',
                'lines.concern:id,disposition,production_status',
                'concerns.workGroups.ownerUser:id,name',
            ])
            ->whereIn('status', WorkboardSwimlaneCatalog::advisorTriageQueueSlugs())
            ->latest()
            ->get();
    }
}
