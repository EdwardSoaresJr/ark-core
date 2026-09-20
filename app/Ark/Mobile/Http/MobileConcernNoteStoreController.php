<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\StoreRepairOrderConcernNoteLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileConcernNoteStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        MobileStaffAccess $access,
        StoreRepairOrderConcernNoteLine $store,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewRepairOrder($request->user(), $repairOrder), 403);
        abort_unless($access->canRecordFinding($request->user(), $repairOrder), 403);
        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'is_private' => ['nullable', 'boolean'],
        ]);

        $isPrivate = array_key_exists('is_private', $data)
            ? (bool) $data['is_private']
            : $store->defaultIsPrivate();

        $store->store(
            $repairOrder,
            $concern,
            $data['description'],
            $request->user(),
            $isPrivate,
        );

        return response()->json([
            'concern' => $projection->forConcern($repairOrder, $concern->fresh(['lines']), $request->user(), $access),
        ], 201);
    }
}
