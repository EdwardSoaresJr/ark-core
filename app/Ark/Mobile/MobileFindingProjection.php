<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

final class MobileFindingProjection
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(InspectionItem $item, RepairOrder $repairOrder): array
    {
        $card = InspectionFindingCardProjection::forItem($item, $repairOrder);
        $note = InspectionFindingIntent::stripNotesPrefix($item->notes);
        $concern = $item->relationLoaded('concern') ? $item->concern : null;
        $photo = $item->photos->first();

        return [
            'id' => $item->id,
            'label' => $item->label,
            'category' => $item->categoryLabel(),
            'concern_id' => $item->repair_order_concern_id,
            'concern_title' => $concern?->summary,
            'intent' => InspectionFindingIntent::tryFromNotes($item->notes)?->value,
            'intent_label' => InspectionFindingIntent::tryFromNotes($item->notes)?->label(),
            'measurement_summary' => $card['measurement'],
            'note' => filled($note) ? $note : null,
            'has_photo' => $item->photos->isNotEmpty(),
            'photo_count' => $item->photos->count(),
            'thumbnail_url' => $photo !== null
                ? route('api.mobile.repair-orders.inspection-photos.show', [
                    'repairOrder' => $repairOrder,
                    'photo' => $photo,
                ])
                : null,
            'recorded_at' => $item->updated_at?->toIso8601String(),
            'age' => $card['age'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(InspectionItem $item, RepairOrder $repairOrder): array
    {
        $summary = self::summary($item, $repairOrder);
        $photo = $item->photos->first();
        $uploader = $photo?->relationLoaded('uploadedBy') ? $photo->uploadedBy : null;

        return [
            ...$summary,
            'measurements' => $item->measurements->map(fn ($measurement): array => [
                'name' => $measurement->name,
                'value' => $measurement->value,
                'unit' => $measurement->unit,
                'formatted' => $measurement->formattedValue(),
            ])->all(),
            'photos' => $item->photos->map(fn ($photo): array => [
                'id' => $photo->id,
                'url' => route('api.mobile.repair-orders.inspection-photos.show', [
                    'repairOrder' => $repairOrder,
                    'photo' => $photo,
                ]),
            ])->all(),
            'recorded_by' => $uploader?->name,
            'concern' => $item->repair_order_concern_id !== null
                ? [
                    'id' => $item->repair_order_concern_id,
                    'title' => $item->concern?->summary,
                ]
                : null,
        ];
    }

    /**
     * @param  Collection<int, InspectionItem>  $recordedItems
     */
    public static function forConcern(int $concernId, Collection $recordedItems, RepairOrder $repairOrder): array
    {
        return $recordedItems
            ->filter(fn (InspectionItem $item): bool => (int) $item->repair_order_concern_id === $concernId)
            ->sortByDesc(fn (InspectionItem $item) => $item->updated_at?->timestamp ?? 0)
            ->map(fn (InspectionItem $item): array => self::summary($item, $repairOrder))
            ->values()
            ->all();
    }
}
