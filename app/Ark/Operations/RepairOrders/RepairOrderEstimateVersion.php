<?php

namespace App\Ark\Operations\RepairOrders;

use App\Models\User;

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
            rescue(
                fn () => RepairOrderEstimateChanged::dispatch($repairOrder),
                report: false,
            );
        }

        return $repairOrder;
    }
}
