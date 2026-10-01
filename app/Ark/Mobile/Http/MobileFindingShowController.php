<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileFindingShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $finding,
        MobileStaffAccess $access,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewRepairOrder($request->user(), $repairOrder), 403);

        return response()->json([
            'finding' => $projection->forFinding($repairOrder, $finding),
        ]);
    }
}
