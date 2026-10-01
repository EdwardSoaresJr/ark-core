<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;

final class InspectionEvidenceUrls
{
    public static function show(RepairOrder $repairOrder, InspectionItemPhoto $photo, string $surface = 'web'): string
    {
        return match ($surface) {
            'mobile' => route('api.mobile.repair-orders.inspection-photos.show', [
                'repairOrder' => $repairOrder,
                'photo' => $photo,
            ]),
            default => route('operations.repair-orders.inspection.photos.show', [
                'repairOrder' => $repairOrder,
                'photo' => $photo,
            ]),
        };
    }
}
