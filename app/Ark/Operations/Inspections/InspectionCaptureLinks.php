<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

final class InspectionCaptureLinks
{
    public static function captureUrl(RepairOrder $repairOrder, ?int $concernId = null): string
    {
        return InspectionWorkspaceUrl::capture($repairOrder, $concernId);
    }

    public static function walkUrl(RepairOrder $repairOrder, ?int $pointId = null): string
    {
        if ($pointId !== null) {
            return InspectionWorkspaceUrl::point($repairOrder, $pointId);
        }

        return InspectionWorkspaceUrl::show($repairOrder);
    }

    public static function tabletUrl(RepairOrder $repairOrder, ?int $pointId = null): string
    {
        return InspectionWorkspaceUrl::tablet($repairOrder, $pointId);
    }

    public static function canRecord(?User $user, RepairOrder $repairOrder): bool
    {
        if ($user === null || $repairOrder->isTerminal()) {
            return false;
        }

        $canManage = $user->can(ArkCapability::RepairOrdersManage->value);
        $canLifecycle = $user->can(ArkCapability::RepairOrdersLifecycle->value);

        if (! $canManage && ! $canLifecycle) {
            return false;
        }

        if ($user->hasAnyRole([ArkRole::Admin->value, ArkRole::Advisor->value])) {
            return true;
        }

        if ($user->hasRole(ArkRole::Technician->value)) {
            return (int) $repairOrder->assigned_technician_id === (int) $user->id;
        }

        return $canManage;
    }
}
