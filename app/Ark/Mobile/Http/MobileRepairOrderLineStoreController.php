<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\DestroyRepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\StoreRepairOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderLineStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        StoreRepairOrderLine $storeLine,
        RepairOrderConcurrency $concurrency,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($access->canViewRepairOrder($user, $repairOrder), 403);
        abort_unless($access->canSetConcernDisposition($user, $repairOrder), 403);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $line = $storeLine->store($repairOrder, $request->all(), $user);

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
            'line' => $this->linePayload($line, $repairOrder, $user, $access, $projection),
            'repair_order' => $projection->forRepairOrder($repairOrder, $user),
            'message' => 'Line saved.',
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function linePayload(
        RepairOrderLine $line,
        RepairOrder $repairOrder,
        $user,
        MobileStaffAccess $access,
        MobileRepairOrderProjection $projection,
    ): array {
        $estimate = $projection->forRepairOrder($repairOrder, $user)['estimate'] ?? [];
        $groups = $estimate['groups'] ?? [];

        foreach ($groups as $group) {
            foreach ($group['lines'] ?? [] as $row) {
                if (($row['id'] ?? null) === $line->id) {
                    return $row;
                }
            }
        }

        return [
            'id' => $line->id,
            'type' => $line->type->value,
            'description' => $line->description,
        ];
    }
}
