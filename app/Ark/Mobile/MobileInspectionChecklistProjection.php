<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\InspectionTemplateItem;
use App\Ark\Operations\RepairOrders\RepairOrder;

final class MobileInspectionChecklistProjection
{
    /**
     * @return array<string, mixed>
     */
    public static function forRepairOrder(RepairOrder $repairOrder, Inspection $inspection): array
    {
        $inspection->load([
            'items.measurements',
            'items.photos',
        ]);

        $checklistItems = $inspection->items
            ->filter(fn (InspectionItem $item): bool => $item->inspection_template_item_id !== null
                || filled($item->checklist_category_name))
            ->sortBy('position')
            ->values();

        $templateItemIds = $checklistItems
            ->pluck('inspection_template_item_id')
            ->filter()
            ->unique()
            ->values();

        $templateItems = InspectionTemplateItem::query()
            ->whereIn('id', $templateItemIds)
            ->get()
            ->keyBy('id');

        $categories = $checklistItems
            ->groupBy(fn (InspectionItem $item): string => $item->checklist_category_name ?? 'Inspection')
            ->map(function ($items, string $categoryName) use ($repairOrder, $templateItems): array {
                return [
                    'name' => $categoryName,
                    'items' => $items
                        ->map(fn (InspectionItem $item): array => self::itemRow(
                            $item,
                            $repairOrder,
                            $item->inspection_template_item_id !== null
                                ? $templateItems->get($item->inspection_template_item_id)
                                : null,
                        ))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $checkedCount = $checklistItems
            ->filter(fn (InspectionItem $item): bool => $item->observed_state !== InspectionObservedState::NotChecked)
            ->count();

        return [
            'inspection_id' => $inspection->id,
            'template_applied' => $checklistItems->isNotEmpty(),
            'progress' => [
                'checked' => $checkedCount,
                'total' => $checklistItems->count(),
            ],
            'status_options' => collect(InspectionChecklistStatus::ordered())
                ->map(fn (InspectionChecklistStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'requires_evidence' => $status->requiresEvidencePrompt(),
                ])
                ->all(),
            'categories' => $categories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemRow(
        InspectionItem $item,
        RepairOrder $repairOrder,
        ?InspectionTemplateItem $templateItem = null,
    ): array {
        $status = $item->observed_state instanceof InspectionObservedState
            ? InspectionChecklistStatus::fromObservedState($item->observed_state)
            : null;

        $photo = $item->photos->first(fn (InspectionItemPhoto $candidate): bool => $candidate->isImage());
        $video = $item->photos->first(fn (InspectionItemPhoto $candidate): bool => $candidate->isVideo());
        $measurement = $item->measurements->first();

        return [
            'id' => $item->id,
            'label' => $item->label,
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'status_display' => match ($status) {
                InspectionChecklistStatus::Good => 'OK',
                InspectionChecklistStatus::Monitor => 'Monitor',
                InspectionChecklistStatus::NeedsAttention,
                InspectionChecklistStatus::Failed => 'Replace',
                InspectionChecklistStatus::Na => 'N/A',
                default => $status?->label(),
            },
            'note' => filled($item->notes) ? $item->notes : null,
            'requires_photo' => (bool) ($templateItem?->requires_photo ?? false),
            'measurement_name' => $templateItem?->measurement_name,
            'measurement_unit' => $templateItem?->measurement_unit,
            'measurement' => $measurement instanceof \App\Ark\Operations\Inspections\InspectionItemMeasurement
                ? [
                    'value' => $measurement->value,
                    'unit' => $measurement->unit,
                    'formatted' => $measurement->formattedValue(),
                ]
                : null,
            'has_photo' => $item->photos->contains(fn (InspectionItemPhoto $candidate): bool => $candidate->isImage()),
            'has_video' => $item->photos->contains(fn (InspectionItemPhoto $candidate): bool => $candidate->isVideo()),
            'photo_count' => $item->photos->count(),
            'thumbnail_url' => $photo !== null
                ? route('api.mobile.repair-orders.inspection-photos.show', [
                    'repairOrder' => $repairOrder,
                    'photo' => $photo,
                ])
                : null,
            'video_url' => $video !== null
                ? route('api.mobile.repair-orders.inspection-photos.show', [
                    'repairOrder' => $repairOrder,
                    'photo' => $video,
                ])
                : null,
            'checked' => $item->observed_state !== InspectionObservedState::NotChecked,
        ];
    }
}
