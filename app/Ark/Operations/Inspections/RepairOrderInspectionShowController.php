<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\OperationalIdentityPresenter;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RepairOrderInspectionShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EnsureInspectionAction $ensureInspection,
        InspectionWalkWorkspaceProjection $walk,
    ): View {
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersView->value)
                || $request->user()?->can(ArkCapability::RepairOrdersManage->value)
                || $request->user()?->can(ArkCapability::RepairOrdersLifecycle->value),
            403,
        );

        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician', 'concerns']);

        $inspection = $ensureInspection->execute($repairOrder, $request->user());
        $walkData = $walk->for($request, $repairOrder, $inspection);
        $coverage = InspectionCoverageProjection::for($repairOrder, $request->user());
        $canRecord = InspectionCaptureLinks::canRecord($request->user(), $repairOrder);

        return view('operations.repair-orders.inspection.show', [
            'repairOrder' => $repairOrder,
            'identity' => OperationalIdentityPresenter::forRepairOrder($repairOrder, includeStaffPosture: true),
            'coverage' => $coverage,
            'canRecordFindings' => $canRecord,
            ...$walkData,
        ]);
    }
}
