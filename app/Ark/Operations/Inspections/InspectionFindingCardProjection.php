<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Carbon;

final class InspectionFindingCardProjection
{
    /**
     * @return array{
     *     id: int,
     *     label: string,
     *     category: string,
     *     intent: ?string,
     *     intent_tone: string,
     *     measurement: ?string,
     *     note: ?string,
     *     photo_count: int,
     *     first_photo_url: ?string,
     *     age: string,
     *     recorded: bool,
     * }
     */
    public static function forItem(InspectionItem $item, RepairOrder $repairOrder): array
    {
        $intent = InspectionFindingIntent::tryFromNotes($item->notes);
        $measurement = $item->measurements->first();
        $firstImage = $item->photos->first(fn (InspectionItemPhoto $photo): bool => $photo->isImage());
        $firstVideo = $item->photos->first(fn (InspectionItemPhoto $photo): bool => $photo->isVideo());

        return [
            'id' => $item->id,
            'label' => $item->label,
            'category' => $item->categoryLabel(),
            'intent' => $intent?->label(),
            'intent_tone' => self::intentTone($intent),
            'measurement' => $measurement instanceof InspectionItemMeasurement
                ? $measurement->formattedValue()
                : null,
            'note' => InspectionFindingIntent::stripNotesPrefix($item->notes),
            'photo_count' => $item->photos->count(),
            'first_photo_url' => $firstImage instanceof InspectionItemPhoto
                ? route('operations.repair-orders.inspection.photos.show', [$repairOrder, $firstImage])
                : null,
            'first_video_url' => $firstVideo instanceof InspectionItemPhoto
                ? route('operations.repair-orders.inspection.photos.show', [$repairOrder, $firstVideo])
                : null,
            'has_video' => $firstVideo instanceof InspectionItemPhoto,
            'age' => self::ageLabel($item->updated_at),
            'recorded' => self::isRecorded($item),
        ];
    }

    public static function isRecorded(InspectionItem $item): bool
    {
        return $item->observed_state !== InspectionObservedState::NotChecked
            || filled(InspectionFindingIntent::stripNotesPrefix($item->notes))
            || $item->measurements->isNotEmpty()
            || $item->photos->isNotEmpty();
    }

    public static function recordedCountForRepairOrder(RepairOrder $repairOrder): int
    {
        return InspectionItem::query()
            ->whereHas('inspection', fn ($query) => $query->where('repair_order_id', $repairOrder->id))
            ->where(function ($query): void {
                $query->where('observed_state', '!=', InspectionObservedState::NotChecked->value)
                    ->orWhere(function ($notes): void {
                        $notes->whereNotNull('notes')->where('notes', '!=', '');
                    })
                    ->orWhereHas('measurements')
                    ->orWhereHas('photos');
            })
            ->count();
    }

    private static function intentTone(?InspectionFindingIntent $intent): string
    {
        return match ($intent) {
            InspectionFindingIntent::Safety => 'rose',
            InspectionFindingIntent::Maintenance => 'amber',
            InspectionFindingIntent::Diagnostic => 'sky',
            InspectionFindingIntent::Verification => 'emerald',
            InspectionFindingIntent::Observation => 'slate',
            default => 'slate',
        };
    }

    private static function ageLabel(?Carbon $updatedAt): string
    {
        if (! $updatedAt instanceof Carbon) {
            return 'just now';
        }

        return $updatedAt->diffForHumans(short: true);
    }
}
