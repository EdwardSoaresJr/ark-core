<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

final class InspectionWorkspaceTabBadgeProjection
{
    /**
     * @return array{recordedFindingCount: int, inspectCaptureUrl: ?string}
     */
    public static function for(RepairOrder $repairOrder, ?User $user = null): array
    {
        return [
            'recordedFindingCount' => InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder),
            'inspectCaptureUrl' => InspectionCaptureLinks::canRecord($user, $repairOrder)
                ? InspectionCaptureLinks::captureUrl($repairOrder)
                : null,
        ];
    }
}
