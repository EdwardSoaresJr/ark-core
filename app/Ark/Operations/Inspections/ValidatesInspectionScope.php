<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;

trait ValidatesInspectionScope
{
    protected function itemForRepairOrder(RepairOrder $repairOrder, InspectionItem $item): InspectionItem
    {
        $item->loadMissing('inspection');

        abort_unless($item->inspection->repair_order_id === $repairOrder->id, 404);

        return $item;
    }

    protected function measurementForRepairOrder(
        RepairOrder $repairOrder,
        InspectionItemMeasurement $measurement,
    ): InspectionItemMeasurement {
        $measurement->loadMissing('item.inspection');

        abort_unless($measurement->item->inspection->repair_order_id === $repairOrder->id, 404);

        return $measurement;
    }

    protected function photoForRepairOrder(RepairOrder $repairOrder, InspectionItemPhoto $photo): InspectionItemPhoto
    {
        $photo->loadMissing('item.inspection');

        abort_unless($photo->item->inspection->repair_order_id === $repairOrder->id, 404);

        return $photo;
    }

    protected function resolveConcernIdForRepairOrder(RepairOrder $repairOrder, ?int $concernId): ?int
    {
        if ($concernId === null) {
            return null;
        }

        abort_unless(
            RepairOrderConcern::query()
                ->where('repair_order_id', $repairOrder->id)
                ->whereKey($concernId)
                ->exists(),
            422,
        );

        return $concernId;
    }

    protected function touchInspectionRecorded(Inspection $inspection, ?\App\Models\User $actor): void
    {
        $inspection->update([
            'recorded_by_user_id' => $actor?->id ?? $inspection->recorded_by_user_id,
        ]);
    }

    protected function redirectToFinding(
        RepairOrder $repairOrder,
        InspectionItem $item,
        string $status,
    ): \Illuminate\Http\RedirectResponse {
        $surface = InspectionWorkspaceUrl::normalizeSurface(request()->query('surface'))
            ?? InspectionWorkspaceUrl::normalizeSurface(request()->input('surface'));

        $return = (string) (request()->query('return') ?? request()->input('return') ?? '');
        if ($return === 'sections') {
            $query = array_filter([
                'surface' => $surface,
                'section' => request()->query('section') ?? request()->input('section'),
            ], fn ($value): bool => $value !== null && $value !== '');

            return redirect()
                ->to(InspectionWorkspaceUrl::show($repairOrder, $query))
                ->with('status', $status);
        }

        return redirect()
            ->to(InspectionWorkspaceUrl::finding($repairOrder, $item, $surface))
            ->with('status', $status);
    }
}
