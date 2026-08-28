<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\UpdateConcernDispositionAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * In-place estimate approvals from the phone. Reuses the authoritative
 * UpdateConcernDispositionAction — the same path the desktop worksheet uses —
 * so approve/decline/defer behaves identically (event, version bump, totals
 * recalc, lifecycle retreat) regardless of surface.
 */
final class MobileConcernDispositionController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        MobileStaffAccess $access,
        UpdateConcernDispositionAction $updateDisposition,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($access->canViewRepairOrder($user, $repairOrder), 403);
        abort_unless($access->canSetConcernDisposition($user, $repairOrder), 403);
        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'disposition' => ['required', Rule::enum(RepairOrderConcernDisposition::class)],
        ]);

        $updateDisposition->execute(
            $repairOrder,
            $concern,
            RepairOrderConcernDisposition::from($data['disposition']),
            $user,
        );

        $concern->refresh();

        return response()->json([
            'concern' => $projection->forConcern($repairOrder->fresh(), $concern, $user, $access),
        ]);
    }
}
