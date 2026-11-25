<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

/**
 * Per-RO inspection obligation override. Preserved when shop settings change later.
 */
final class SetRepairOrderInspectionRequirementAction
{
    public static function canManage(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole([
            ArkRole::Admin->value,
            ArkRole::Advisor->value,
        ]);
    }

    public function execute(
        RepairOrder $repairOrder,
        ?InspectionRequirement $requirement,
        User $actor,
    ): RepairOrder {
        abort_unless(self::canManage($actor), 403);
        $repairOrder->ensureOpenForEditing();

        // Clearing override returns the RO to current shop policy.
        $repairOrder->forceFill([
            'inspection_lifecycle_policy' => $requirement?->value,
        ])->save();

        return $repairOrder->fresh();
    }
}
