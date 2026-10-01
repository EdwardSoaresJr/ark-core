<?php

namespace App\Ark\Tech\Http;

use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionChecklistItems;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemLivingRecordProjection;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Tech\TechStaffGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

final class TechInspectionItemUpdateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        TechStaffGate $gate,
        EnsureInspectionAction $ensureInspection,
        UpdateInspectionChecklistItemAction $updateItem,
        InspectionItemLivingRecordProjection $livingRecord,
    ): JsonResponse {
        abort_unless($gate->canRecordFinding($request->user(), $repairOrder), 403);

        $repairOrder->ensureOpenForEditing();
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $data = $request->validate([
            'status' => ['nullable', Rule::enum(InspectionChecklistStatus::class)],
            'note' => ['nullable', 'string', 'max:5000'],
            'measurement_value' => ['nullable', 'string', 'max:64'],
            'measurement_unit' => ['nullable', 'string', 'max:32'],
            'measurements' => ['nullable', 'array'],
            'measurements.*.name' => ['nullable', 'string', 'max:64'],
            'measurements.*.value' => ['required_with:measurements', 'string', 'max:64'],
            'measurements.*.unit' => ['nullable', 'string', 'max:32'],
            'photo' => ['nullable', 'file', 'max:20480'],
            'source' => ['nullable', 'string', Rule::in(['manual', 'voice_confirmed'])],
            'rear_axle_brake_type' => ['nullable', 'string', Rule::in(['disc', 'drum'])],
        ]);

        $status = isset($data['status'])
            ? InspectionChecklistStatus::from($data['status'])
            : InspectionChecklistStatus::NeedsAttention;

        $measurements = null;
        if (isset($data['measurements'])) {
            $measurements = array_map(static function (array $row): array {
                $name = (string) ($row['name'] ?? '');

                return [
                    'key' => $name !== '' ? $name : null,
                    'name' => $name !== '' ? $name : null,
                    'value' => (string) $row['value'],
                    'unit' => $row['unit'] ?? 'mm',
                ];
            }, $data['measurements']);
        }

        $result = $updateItem->execute(
            $repairOrder,
            $inspection,
            $item,
            $status,
            $request->user(),
            $data['note'] ?? null,
            $data['measurement_value'] ?? null,
            $data['measurement_unit'] ?? null,
            $data['photo'] ?? null,
            $measurements,
            $data['rear_axle_brake_type'] ?? null,
        );

        Log::info('tech.inspection.item.updated', [
            'user_id' => $request->user()?->id,
            'repair_order_id' => $repairOrder->id,
            'item_id' => $item->id,
            'source' => $data['source'] ?? 'manual',
        ]);

        $record = InspectionChecklistItems::livingRecordForItem(
            $repairOrder,
            $inspection,
            $result['item'],
            $livingRecord,
            'mobile',
        );

        return response()->json([
            'item' => $record,
            'saved' => true,
            'source' => $data['source'] ?? 'manual',
        ]);
    }
}
