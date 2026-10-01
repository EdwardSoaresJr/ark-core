<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileInspectionChecklistProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileInspectionChecklistShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        EnsureInspectionAction $ensureInspection,
        ApplyInspectionTemplateAction $applyTemplate,
    ): JsonResponse {
        abort_unless($access->canRecordFinding($request->user(), $repairOrder), 403);

        DefaultInspectionTemplateCatalog::seedIfMissing();

        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $hasChecklistItems = $inspection->items()
            ->whereNotNull('inspection_template_item_id')
            ->exists();

        if (! $hasChecklistItems) {
            $applyTemplate->execute($repairOrder, $inspection, actor: $request->user());
            $inspection->refresh();
        }

        return response()->json([
            'checklist' => MobileInspectionChecklistProjection::forRepairOrder($repairOrder, $inspection),
        ]);
    }
}
