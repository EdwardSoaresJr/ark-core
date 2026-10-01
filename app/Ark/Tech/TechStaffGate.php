<?php

namespace App\Ark\Tech;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

final class TechStaffGate
{
    public function __construct(
        private readonly MobileStaffAccess $mobileAccess,
    ) {}

    public function canUseTech(User $user): bool
    {
        if (! $this->mobileAccess->canUseMobile($user)) {
            return false;
        }

        return $user->hasRole(ArkRole::Technician->value)
            || $user->hasRole(ArkRole::Admin->value);
    }

    public function canViewRepairOrder(User $user, RepairOrder $repairOrder): bool
    {
        if (! $this->canUseTech($user)) {
            return false;
        }

        return $this->mobileAccess->canViewRepairOrder($user, $repairOrder);
    }

    public function canRecordFinding(User $user, RepairOrder $repairOrder): bool
    {
        if (! $this->canUseTech($user)) {
            return false;
        }

        return $this->mobileAccess->canRecordFinding($user, $repairOrder);
    }
}
