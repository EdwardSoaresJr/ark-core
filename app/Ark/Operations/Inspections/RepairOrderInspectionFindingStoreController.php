<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderInspectionFindingStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EnsureInspectionAction $ensureInspection,
        StoreInspectionFindingAction $storeFinding,
    ): RedirectResponse {
        abort_unless(InspectionCaptureLinks::canRecord($request->user(), $repairOrder), 403);

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
            'photo' => InspectionEvidenceStore::uploadRules(),
            'add_another' => ['sometimes', 'boolean'],
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

        if ($request->boolean('add_another')) {
            return redirect()
                ->to(InspectionWorkspaceUrl::capture($repairOrder, $item->repair_order_concern_id))
                ->with('status', 'Finding saved. Record another.')
                ->with('finding_intent', $data['intent']);
        }

        return redirect()
            ->to(InspectionWorkspaceUrl::finding($repairOrder, $item))
            ->with('status', 'Finding saved.');
    }
}
