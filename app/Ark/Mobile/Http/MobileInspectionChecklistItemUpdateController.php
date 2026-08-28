<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Operations\Inspections\InspectionChecklistItems;
use App\Ark\Operations\Inspections\InspectionItemLivingRecordProjection;
use App\Ark\Mobile\MobileInspectionChecklistProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionEvidenceStore;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileInspectionChecklistItemUpdateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        MobileStaffAccess $access,
        EnsureInspectionAction $ensureInspection,
        UpdateInspectionChecklistItemAction $updateItem,
        InspectionItemLivingRecordProjection $projection,
    ): JsonResponse {
        abort_unless($access->canRecordFinding($request->user(), $repairOrder), 403);

        $repairOrder->ensureOpenForEditing();
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $data = $request->validate([
            'status' => ['required', Rule::enum(InspectionChecklistStatus::class)],
            'note' => ['nullable', 'string', 'max:5000'],
            'measurement_value' => ['nullable', 'string', 'max:64'],
            'measurement_unit' => ['nullable', 'string', 'max:32'],
            'measurements' => ['nullable', 'array'],
            'measurements.*.key' => ['nullable', 'string', 'max:64'],
            'measurements.*.name' => ['nullable', 'string', 'max:64'],
            'measurements.*.value' => ['nullable', 'string', 'max:64'],
            'measurements.*.unit' => ['nullable', 'string', 'max:32'],
            'rear_axle_brake_type' => ['nullable', 'string', Rule::in(['disc', 'drum'])],
            'photo' => InspectionEvidenceStore::uploadRules(),
        ]);

        $result = $updateItem->execute(
            repairOrder: $repairOrder,
            inspection: $inspection,
            item: $item,
            status: InspectionChecklistStatus::from($data['status']),
            actor: $request->user(),
            notes: array_key_exists('note', $data) ? $data['note'] : null,
            measurementValue: $data['measurement_value'] ?? null,
            measurementUnit: $data['measurement_unit'] ?? null,
            photo: $data['photo'] ?? null,
            measurements: $data['measurements'] ?? null,
            rearAxleBrakeType: $data['rear_axle_brake_type'] ?? null,
        );

        $livingRecord = InspectionChecklistItems::livingRecordForItem(
            $repairOrder,
            $inspection,
            $result['item'],
            $projection,
            'mobile',
        );

        return response()->json([
            'item' => $livingRecord,
            'living_record' => $livingRecord,
            'summary' => MobileInspectionChecklistProjection::itemRow(
                $result['item'],
                $repairOrder,
                $result['item']->inspection_template_item_id !== null
                    ? \App\Ark\Operations\Inspections\InspectionTemplateItem::query()->find($result['item']->inspection_template_item_id)
                    : null,
            ),
            'follow_up' => [
                'requires_photo' => $result['requires_photo'],
                'requires_measurement' => $result['requires_measurement'],
            ],
        ]);
    }
}
