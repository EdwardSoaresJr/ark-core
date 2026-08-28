<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateBroadcast;
use App\Ark\Operations\RepairOrders\RepairOrderFinancialChanged;
use App\Models\User;

final class NotifyRepairOrderFinancialChange
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    public function notify(RepairOrder $repairOrder, string $reason = 'updated', ?User $actor = null): void
    {
        if (! RepairOrderEstimateBroadcast::enabled()) {
            return;
        }

        $repairOrder = $repairOrder->fresh();
        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        RepairOrderFinancialChanged::dispatch(
            $repairOrder,
            $reason,
            $actor?->id,
            $balance->balanceDueCents,
        );
    }
}
