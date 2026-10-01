<?php

namespace App\Ark\Tech\Http;

use App\Ark\Mobile\MobileInspectionChecklistProjection;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Tech\TechDviTaskProjector;
use App\Ark\Tech\TechStaffGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TechInspectionShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        TechStaffGate $gate,
        EnsureInspectionAction $ensureInspection,
        ApplyInspectionTemplateAction $applyTemplate,
        TechDviTaskProjector $dvi,
    ): JsonResponse {
        abort_unless($gate->canRecordFinding($request->user(), $repairOrder), 403);

        DefaultInspectionTemplateCatalog::seedIfMissing();

        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        if (! $inspection->items()->whereNotNull('inspection_template_item_id')->exists()) {
            $applyTemplate->execute($repairOrder, $inspection, actor: $request->user());
            $inspection->refresh();
        }

        $checklist = MobileInspectionChecklistProjection::forRepairOrder($repairOrder, $inspection);
        $dviPayload = $dvi->present($repairOrder, $inspection);

        return response()->json([
            'inspection' => $checklist,
            'sections' => $dviPayload['sections'],
            'tasks' => $dviPayload['tasks'],
            'brake_items' => $dviPayload['brake_items'],
            'progress' => $checklist['progress'] ?? null,
        ]);
    }
}
