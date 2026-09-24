<?php

namespace App\Ark\Operations\RepairOrders;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RepairOrderEstimateVersion
{
    public function bump(RepairOrder $repairOrder, ?User $actor = null): RepairOrder
    {
        $repairOrder->increment('estimate_version');
        $repairOrder->forceFill([
            'estimate_version_actor_id' => $actor?->id,
            'estimate_version_at' => now(),
        ])->save();

        $repairOrder = $repairOrder->fresh();

        if (RepairOrderEstimateBroadcast::enabled()) {
            $committed = $repairOrder->fresh();
            DB::afterCommit(function () use ($committed): void {
                rescue(
                    fn () => RepairOrderEstimateChanged::dispatch($committed),
                    report: false,
                );
            });
        }

        return $repairOrder;
    }
}
