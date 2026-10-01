<?php

namespace App\Ark\Tech\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Tech\TechStaffGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TechRepairOrderShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        TechStaffGate $gate,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        abort_unless($gate->canViewRepairOrder($request->user(), $repairOrder), 403);

        $row = $projection->forRepairOrder($repairOrder, $request->user());

        return response()->json([
            'repair_order' => [
                'id' => $repairOrder->repair_order_id,
                'number' => (string) $repairOrder->repair_order_id,
                'vehicle_label' => $row['vehicle']['label'] ?? $repairOrder->vehicle?->display_name,
                'mileage' => $repairOrder->mileage_in,
                'concern' => $repairOrder->concern_summary,
                'status' => $repairOrder->status->value,
                'status_label' => $repairOrder->status->label(),
                'assigned_technician' => $repairOrder->assignedTechnician?->name,
            ],
        ]);
    }
}
