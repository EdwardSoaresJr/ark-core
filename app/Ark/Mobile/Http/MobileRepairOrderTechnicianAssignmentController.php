<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileIntakeRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\RepairOrders\AssignRepairOrderTechnician;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderTechnicianAssignmentController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        AssignRepairOrderTechnician $assignment,
        RepairOrderConcurrency $concurrency,
        MobileIntakeRepairOrderProjection $projection,
    ): JsonResponse {
        abort_unless($access->canPerformIntake($request->user()), 403);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $technicianId = $assignment->validate($request->all());

        if ((int) $repairOrder->assigned_technician_id === $technicianId) {
            $repairOrder->loadMissing(['assignedTechnician:id,name']);

            return response()->json([
                'repair_order' => $projection->summary($repairOrder),
                'message' => 'Technician assignment unchanged.',
            ]);
        }

        $technician = $assignment->assign($repairOrder, $technicianId, $request->user());

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        $repairOrder->refresh()->loadMissing(['assignedTechnician:id,name']);

        return response()->json([
            'repair_order' => $projection->summary($repairOrder),
            'message' => $technician
                ? 'Technician assigned: '.$technician->name.'.'
                : 'Technician assignment cleared.',
        ]);
    }
}
