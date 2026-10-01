<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderInspectionMeasurementStoreController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        EnsureInspectionAction $ensureInspection,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $item = $this->itemForRepairOrder($repairOrder, $item);
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'value' => ['required', 'string', 'max:64'],
            'unit' => ['nullable', 'string', 'max:32'],
        ]);

        $templateItem = $item->inspection_template_item_id !== null
            ? InspectionTemplateItem::query()->find($item->inspection_template_item_id)
            : null;
        $slots = InspectionMeasurementSlots::fromTemplateItem($templateItem);
        $slot = collect($slots)->first(function (array $candidate) use ($data): bool {
            return strcasecmp((string) ($candidate['name'] ?? ''), $data['name']) === 0
                || strcasecmp((string) ($candidate['key'] ?? ''), $data['name']) === 0;
        });

        $unit = filled($data['unit'] ?? null)
            ? trim((string) $data['unit'])
            : (filled($slot['unit'] ?? null)
                ? trim((string) $slot['unit'])
                : (filled($templateItem?->measurement_unit) ? trim((string) $templateItem->measurement_unit) : null));

        $nextPosition = (int) $item->measurements()->max('position') + 1;

        $item->measurements()->create([
            'name' => $data['name'],
            'value' => $data['value'],
            'unit' => $unit,
            'position' => $nextPosition,
        ]);

        if ($item->observed_state === InspectionObservedState::NotChecked) {
            $item->update(['observed_state' => InspectionObservedState::Measure]);
        }

        $this->touchInspectionRecorded($inspection, $request->user());

        return $this->redirectToFinding($repairOrder, $item, 'Measurement recorded.');
    }
}
