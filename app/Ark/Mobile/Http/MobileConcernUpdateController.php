<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\UpdateRepairOrderConcernInspection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileConcernUpdateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        MobileStaffAccess $access,
        UpdateRepairOrderConcernInspection $update,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewRepairOrder($request->user(), $repairOrder), 403);
        abort_unless($access->canRecordFinding($request->user(), $repairOrder), 403);
        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);

        $data = $request->validate([
            'verified_findings' => ['nullable', 'string', 'max:10000'],
            'recommendation' => ['nullable', 'string', 'max:5000'],
            'dtcs_summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $update->update($repairOrder, $concern, $data, $request->user());

        return response()->json([
            'concern' => $projection->forConcern($repairOrder, $concern->fresh(), $request->user(), $access),
        ]);
    }
}
