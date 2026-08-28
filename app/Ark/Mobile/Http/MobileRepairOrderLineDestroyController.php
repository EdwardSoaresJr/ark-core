<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\DestroyRepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderLineDestroyController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderLine $line,
        MobileStaffAccess $access,
        DestroyRepairOrderLine $destroyLine,
        RepairOrderConcurrency $concurrency,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($access->canViewRepairOrder($user, $repairOrder), 403);
        abort_unless($access->canSetConcernDisposition($user, $repairOrder), 403);
        abort_unless((int) $line->repair_order_id === (int) $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $destroyLine->destroy($repairOrder, $line, $user);

        $repairOrder->refresh()->loadMissing([
            'customer',
            'vehicle',
            'assignedTechnician:id,name',
            'concerns.lines',
            'inspection.items.measurements',
            'inspection.items.photos',
            'inspection.items.concern',
        ]);

        return response()->json([
            'repair_order' => $projection->forRepairOrder($repairOrder, $user),
            'message' => 'Line deleted.',
        ]);
    }
}
