<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderInspectionPointUpdateController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        EnsureInspectionAction $ensureInspection,
        UpdateInspectionChecklistItemAction $updateItem,
        InspectionItemLivingRecordProjection $livingRecord,
    ): JsonResponse {
        abort_unless(InspectionCaptureLinks::canRecord($request->user(), $repairOrder), 403);

        $repairOrder->ensureOpenForEditing();
        $item = $this->itemForRepairOrder($repairOrder, $item);
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(InspectionChecklistStatus::class)],
            'note' => ['nullable', 'string', 'max:5000'],
            'measurement_value' => ['nullable', 'string', 'max:64'],
            'measurement_unit' => ['nullable', 'string', 'max:32'],
            'measurements' => ['nullable', 'array'],
            'measurements.*.key' => ['nullable', 'string', 'max:64'],
            'measurements.*.name' => ['nullable', 'string', 'max:64'],
            'measurements.*.value' => ['nullable', 'string', 'max:64'],
            'measurements.*.unit' => ['nullable', 'string', 'max:32'],
            'selected_observations' => ['nullable', 'array'],
            'selected_observations.*' => ['string', 'max:64'],
            'selected_observations_json' => ['nullable', 'string', 'max:4000'],
            'rear_axle_brake_type' => ['nullable', 'string', Rule::in([Inspection::REAR_AXLE_DISC, Inspection::REAR_AXLE_DRUM])],
            'photo' => InspectionEvidenceStore::uploadRules(),
        ]);

        if (isset($data['selected_observations_json']) && ! isset($data['selected_observations'])) {
            $decoded = json_decode((string) $data['selected_observations_json'], true);
            $data['selected_observations'] = is_array($decoded)
                ? array_values(array_filter($decoded, fn (mixed $key): bool => is_string($key) && $key !== ''))
                : [];
        }

        if (! isset($data['status'])) {
            abort_unless(
                isset($data['note'])
                || isset($data['measurement_value'])
                || isset($data['measurements'])
                || array_key_exists('selected_observations', $data)
                || isset($data['selected_observations_json'])
                || isset($data['rear_axle_brake_type'])
                || isset($data['photo']),
                422,
                'Condition or evidence update required.',
            );
        }

        $currentState = $item->observed_state instanceof InspectionObservedState
            ? $item->observed_state
            : InspectionObservedState::NotChecked;

        $status = isset($data['status'])
            ? InspectionChecklistStatus::from($data['status'])
            : (InspectionChecklistStatus::fromObservedState($currentState) ?? InspectionChecklistStatus::Good);

        $result = $updateItem->execute(
            repairOrder: $repairOrder,
            inspection: $inspection,
            item: $item,
            status: $status,
            actor: $request->user(),
            notes: array_key_exists('note', $data) ? $data['note'] : null,
            measurementValue: $data['measurement_value'] ?? null,
            measurementUnit: $data['measurement_unit'] ?? null,
            photo: $data['photo'] ?? null,
            measurements: $data['measurements'] ?? null,
            rearAxleBrakeType: $data['rear_axle_brake_type'] ?? null,
            selectedObservations: array_key_exists('selected_observations', $data)
                ? array_values($data['selected_observations'] ?? [])
                : null,
        );

        $record = InspectionChecklistItems::livingRecordForItem(
            $repairOrder,
            $inspection->fresh(),
            $result['item'],
            $livingRecord,
            'web',
        );

        return response()->json([
            'living_record' => $record,
            'follow_up' => [
                'requires_photo' => $result['requires_photo'],
                'requires_measurement' => $result['requires_measurement'],
                'missing_measurement_slots' => $result['missing_measurement_slots'],
                'addressed' => $result['addressed'],
            ],
            'brake_prompts' => $result['brake_prompts'],
        ]);
    }
}
