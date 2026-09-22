<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\RepairOrders\UpdateConcernProductionStatusAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileConcernProductionStatusController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        MobileStaffAccess $access,
        MobileRepairOrderProjection $projection,
        UpdateConcernProductionStatusAction $updateProductionStatus,
    ): JsonResponse {
        abort_unless($access->canViewRepairOrder($request->user(), $repairOrder), 403);
        abort_unless($access->canUpdateConcernProductionStatus($request->user(), $repairOrder), 403);
        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'production_status' => ['required', Rule::enum(ScopeProductionStatus::class)],
        ]);

        $result = $updateProductionStatus->execute(
            $repairOrder,
            $concern,
            ScopeProductionStatus::from($data['production_status']),
            $request->user(),
            ['surface' => 'mobile'],
        );
        $concern->refresh();
        $recognition = $result['recognition'];

        return response()->json([
            'concern' => $projection->forConcern($repairOrder, $concern, $request->user(), $access),
            'flag_recognition' => [
                'status' => $recognition['status'],
                'reason' => $recognition['reason'],
                'recognition_id' => $recognition['recognition']?->id,
                'flag_hours_total' => $recognition['recognition'] !== null
                    ? (float) $recognition['recognition']->flag_hours_total
                    : null,
            ],
        ]);
    }
}
