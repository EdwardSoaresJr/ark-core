<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Mobile\RepairOrderWorkspaceProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        MobileRepairOrderProjection $projection,
        RepairOrderWorkspaceProjection $workspaceProjection,
    ): JsonResponse {
        abort_unless($access->canViewRepairOrder($request->user(), $repairOrder), 403);

        $viewer = $request->user();

        return response()->json([
            'repair_order' => $projection->forRepairOrder($repairOrder, $viewer),
            'workspace' => $workspaceProjection->forRepairOrder($repairOrder, $viewer),
        ]);
    }
}
