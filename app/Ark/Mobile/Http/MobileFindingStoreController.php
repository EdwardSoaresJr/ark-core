<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\StoreInspectionFindingAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileFindingStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        EnsureInspectionAction $ensureInspection,
        StoreInspectionFindingAction $storeFinding,
    ): JsonResponse {
        abort_unless($access->canRecordFinding($request->user(), $repairOrder), 403);

        $repairOrder->ensureOpenForEditing();
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $data = $request->validate([
            'intent' => ['required', Rule::enum(InspectionFindingIntent::class)],
            'label' => ['required', 'string', 'max:191'],
            'measurement_value' => ['nullable', 'string', 'max:64'],
            'measurement_unit' => ['nullable', 'string', 'max:32'],
            'measurement_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'repair_order_concern_id' => ['nullable', 'integer'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $item = $storeFinding->execute(
            repairOrder: $repairOrder,
            inspection: $inspection,
            actor: $request->user(),
            intent: InspectionFindingIntent::from($data['intent']),
            label: $data['label'],
            measurementValue: $data['measurement_value'] ?? null,
            measurementUnit: $data['measurement_unit'] ?? null,
            measurementName: $data['measurement_name'] ?? null,
            notes: $data['notes'] ?? null,
            concernId: isset($data['repair_order_concern_id']) ? (int) $data['repair_order_concern_id'] : null,
            photo: $data['photo'] ?? null,
        );

        $item->loadMissing(['measurements', 'photos']);

        return response()->json([
            'finding' => [
                'id' => $item->id,
                'label' => $item->label,
                'intent' => InspectionFindingIntent::tryFromNotes($item->notes)?->value,
                'notes' => InspectionFindingIntent::stripNotesPrefix($item->notes),
                'measurements' => $item->measurements->map(fn ($measurement): array => [
                    'name' => $measurement->name,
                    'value' => $measurement->value,
                    'unit' => $measurement->unit,
                ])->all(),
                'photos' => $item->photos->map(fn ($photo): array => [
                    'id' => $photo->id,
                    'url' => route('api.mobile.repair-orders.inspection-photos.show', [
                        'repairOrder' => $repairOrder,
                        'photo' => $photo,
                    ]),
                ])->all(),
            ],
        ], 201);
    }
}
