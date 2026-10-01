<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Operations\Inspections\InspectionChecklistItems;
use App\Ark\Operations\Inspections\InspectionItemLivingRecordProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileInspectionChecklistItemShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        MobileStaffAccess $access,
        EnsureInspectionAction $ensureInspection,
        ApplyInspectionTemplateAction $applyTemplate,
        InspectionItemLivingRecordProjection $projection,
    ): JsonResponse {
        abort_unless($access->canRecordFinding($request->user(), $repairOrder), 403);

        DefaultInspectionTemplateCatalog::seedIfMissing();

        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        if (! $inspection->items()->whereNotNull('inspection_template_item_id')->exists()) {
            $applyTemplate->execute($repairOrder, $inspection, actor: $request->user());
            $inspection->refresh();
        }

        abort_unless(
            $item->inspection_id === $inspection->id,
            404,
        );

        return response()->json([
            'item' => InspectionChecklistItems::livingRecordForItem(
                $repairOrder,
                $inspection,
                $item,
                $projection,
                'mobile',
            ),
        ]);
    }
}
