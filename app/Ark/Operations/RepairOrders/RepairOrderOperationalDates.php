<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Carbon;

final class RepairOrderOperationalDates
{
    public function openedAt(RepairOrder $repairOrder): Carbon
    {
        return $repairOrder->opened_at ?? $repairOrder->created_at;
    }

    public function closedAt(RepairOrder $repairOrder): ?Carbon
    {
        if (! $this->receivesCloseDate($repairOrder->status)) {
            return null;
        }

        return $repairOrder->closed_at;
    }

    public function applyClose(RepairOrder $repairOrder, ?Carbon $at = null): void
    {
        if ($repairOrder->closed_at !== null) {
            return;
        }

        $repairOrder->forceFill([
            'closed_at' => $at ?? now(),
        ])->save();
    }

    public function applyOpen(RepairOrder $repairOrder, ?Carbon $at = null): void
    {
        if ($repairOrder->opened_at !== null) {
            return;
        }

        $repairOrder->forceFill([
            'opened_at' => $at ?? $repairOrder->created_at ?? now(),
        ])->save();
    }

    public function applyPost(RepairOrder $repairOrder, ?Carbon $at = null): void
    {
        if ($repairOrder->posted_at !== null) {
            return;
        }

        $repairOrder->forceFill([
            'posted_at' => $at ?? now(),
        ])->save();
    }

    public function postedAt(RepairOrder $repairOrder): ?Carbon
    {
        return $repairOrder->posted_at;
    }

    private function receivesCloseDate(RepairOrderStatus|RepairOrderWorkflowStatus $status): bool
    {
        return $status->isTerminal() || $status->is(RepairOrderStatus::ReadyPickup);
    }
}
