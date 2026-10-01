<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

/**
 * Shared checklist ordering for living-record navigation (walk order).
 */
final class InspectionChecklistItems
{
    /**
     * @return Collection<int, InspectionItem>
     */
    public static function orderedChecklistItems(Inspection $inspection): Collection
    {
        $inspection->loadMissing([
            'items.measurements',
            'items.photos',
        ]);

        return $inspection->items
            ->filter(fn (InspectionItem $item): bool => ! $item->isSuperseded()
                && ($item->inspection_template_item_id !== null || filled($item->checklist_category_name)))
            ->sortBy('position')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public static function livingRecordForItem(
        RepairOrder $repairOrder,
        Inspection $inspection,
        InspectionItem $item,
        InspectionItemLivingRecordProjection $projection,
        string $evidenceSurface = 'web',
    ): array {
        $ordered = self::orderedChecklistItems($inspection);

        $templateItem = $item->inspection_template_item_id !== null
            ? InspectionTemplateItem::query()->find($item->inspection_template_item_id)
            : null;

        return $projection->forItem($repairOrder, $item, $templateItem, $ordered, $evidenceSurface);
    }
}
