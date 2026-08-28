<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use Illuminate\Support\Carbon;

/**
 * Prior visit snapshots for the same vehicle + checklist item identity.
 */
final class InspectionItemPriorVisitHistory
{
    /**
     * @return array{
     *     available: bool,
     *     empty_label: string,
     *     items: list<array<string, mixed>>,
     * }
     */
    public function forItem(RepairOrder $repairOrder, InspectionItem $item): array
    {
        $repairOrder->loadMissing('vehicle');

        $vehicleId = $repairOrder->vehicle_id;

        if ($vehicleId === null) {
            return $this->empty();
        }

        $priorItems = InspectionItem::query()
            ->whereHas('inspection.repairOrder', function ($query) use ($repairOrder, $vehicleId): void {
                $query->where('vehicle_id', $vehicleId)
                    ->whereKeyNot($repairOrder->id);
            })
            ->where(function ($query) use ($item): void {
                if ($item->inspection_template_item_id !== null) {
                    $query->where('inspection_template_item_id', $item->inspection_template_item_id);
                } else {
                    $query->where('label', $item->label);
                }
            })
            ->where('observed_state', '!=', InspectionObservedState::NotChecked->value)
            ->with([
                'measurements',
                'photos',
                'concern',
                'inspection.repairOrder',
            ])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        if ($priorItems->isEmpty()) {
            return $this->empty();
        }

        return [
            'available' => true,
            'empty_label' => 'No prior history for this inspection point yet.',
            'items' => $priorItems
                ->map(fn (InspectionItem $prior): array => $this->visitRow($prior))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     available: bool,
     *     empty_label: string,
     *     items: list<array<string, mixed>>,
     * }
     */
    private function empty(): array
    {
        return [
            'available' => false,
            'empty_label' => 'No prior history for this inspection point yet.',
            'items' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function visitRow(InspectionItem $item): array
    {
        $repairOrder = $item->inspection?->repairOrder;
        $status = $item->observed_state instanceof InspectionObservedState
            ? InspectionChecklistStatus::fromObservedState($item->observed_state)
            : null;
        $measurement = $item->measurements->first();
        $intent = InspectionFindingIntent::tryFromNotes($item->notes);

        $dispositionLabel = null;
        $concern = $item->concern;

        if ($concern !== null && $concern->disposition === RepairOrderConcernDisposition::Deferred) {
            $dispositionLabel = 'Customer deferred';
        } elseif ($concern !== null && $concern->disposition === RepairOrderConcernDisposition::Declined) {
            $dispositionLabel = 'Customer declined';
        }

        $occurredAt = $item->updated_at ?? $repairOrder?->closed_at ?? $repairOrder?->updated_at;

        return [
            'repair_order_number' => $repairOrder?->repair_order_id,
            'occurred_at' => $occurredAt instanceof Carbon ? $occurredAt->toIso8601String() : null,
            'occurred_label' => $occurredAt instanceof Carbon ? $occurredAt->diffForHumans() : null,
            'status' => $status?->value,
            'status_display' => $status?->label(),
            'measurement' => $measurement?->formattedValue(),
            'photo_count' => $item->photos->count(),
            'recommendation_summary' => $intent !== null
                ? trim($item->label.' · '.$intent->label())
                : null,
            'customer_outcome_label' => $dispositionLabel,
            'note' => filled($item->notes)
                ? InspectionFindingIntent::stripNotesPrefix($item->notes)
                : null,
        ];
    }
}
