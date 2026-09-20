<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkflowStatus;

/**
 * Mobile status tones mirror desktop attention / workboard chips so projections feel familiar.
 */
final class MobileRepairOrderStatusTone
{
    public static function forStatus(RepairOrderWorkflowStatus|RepairOrderStatus|string $status): string
    {
        $slug = RepairOrderWorkflowStatus::from($status)->value;

        return match ($slug) {
            RepairOrderStatus::WaitingApproval->value => 'waiting_approval',
            RepairOrderStatus::WaitingParts->value => 'waiting_parts',
            RepairOrderStatus::Estimate->value => 'building_estimate',
            RepairOrderStatus::InProgress->value => 'in_progress',
            RepairOrderStatus::ReadyPickup->value => 'ready_pickup',
            default => 'default',
        };
    }
}
