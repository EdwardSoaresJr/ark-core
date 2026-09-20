<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;

/**
 * Operational scan colors for advisor home status chips only.
 */
final class AdvisorHomeAttentionStatusChipTone
{
    public static function forRepairOrder(RepairOrder $repairOrder): string
    {
        $status = $repairOrder->status;

        if ($status->is(RepairOrderStatus::WaitingApproval)) {
            return 'waiting-approval';
        }

        if ($status->is(RepairOrderStatus::WaitingParts)) {
            return 'waiting-parts';
        }

        if ($status->isOneOf([RepairOrderStatus::Draft, RepairOrderStatus::Estimate])) {
            return 'building-estimate';
        }

        if ($status->is(RepairOrderStatus::ReadyPickup)) {
            return 'ready-pickup';
        }

        return 'in-progress';
    }
}
