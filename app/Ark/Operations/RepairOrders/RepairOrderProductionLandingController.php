<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Technician production RO landing — vehicle, why here, concerns, inspection posture.
 * Walk stays on /inspection (not embedded).
 */
final class RepairOrderProductionLandingController
{
    public function __invoke(Request $request, RepairOrder $repairOrder): View
    {
        $repairOrder->load([
            'customer',
            'vehicle',
            'assignedTechnician',
            'concerns',
        ]);

        $landing = RepairOrderProductionLandingProjection::for($repairOrder, $request->user());
        $coverage = $landing['coverage'];

        return view('operations.repair-orders.technician-landing', [
            'repairOrder' => $repairOrder,
            'landing' => $landing,
            'inspectionCoverage' => $coverage,
            'canRecordFindings' => (bool) ($coverage['can_record'] ?? false),
        ]);
    }
}
