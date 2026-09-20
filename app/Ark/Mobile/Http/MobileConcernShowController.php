<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileConcernShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        MobileStaffAccess $access,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewRepairOrder($request->user(), $repairOrder), 403);

        return response()->json([
            'concern' => $projection->forConcern($repairOrder, $concern, $request->user(), $access),
        ]);
    }
}
