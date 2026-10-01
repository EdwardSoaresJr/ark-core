<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class EnsureInspectionAction
{
    public function execute(RepairOrder $repairOrder, ?User $actor = null): Inspection
    {
        return DB::transaction(function () use ($repairOrder, $actor): Inspection {
            return Inspection::query()->firstOrCreate(
                ['repair_order_id' => $repairOrder->id],
                [
                    'recorded_by_user_id' => $actor?->id,
                    'started_at' => now(),
                ],
            );
        });
    }
}
