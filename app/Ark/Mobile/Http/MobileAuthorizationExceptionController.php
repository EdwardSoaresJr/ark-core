<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\AuthorizationExceptionReason;
use App\Ark\Operations\RepairOrders\RecordAuthorizationExceptionAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileAuthorizationExceptionController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        MobileStaffAccess $access,
        RecordAuthorizationExceptionAction $recordException,
    ): JsonResponse {
        abort_unless($access->canViewRepairOrder($request->user(), $repairOrder), 403);
        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'reason' => ['required', Rule::enum(AuthorizationExceptionReason::class)],
            'note' => ['required', 'string', 'max:2000'],
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['integer'],
        ]);

        $exception = $recordException->execute(
            $repairOrder,
            $concern,
            $data['line_ids'],
            AuthorizationExceptionReason::from($data['reason']),
            $data['note'],
            $request->user(),
        );

        return response()->json([
            'exception_id' => $exception->id,
            'concern_id' => $concern->id,
            'line_ids' => $exception->lineIds(),
            'reason' => $exception->reason->value,
            'establishes_customer_consent' => false,
            'disposition' => $concern->fresh()->disposition->value,
        ]);
    }
}
