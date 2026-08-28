<?php

namespace App\Ark\Operations\Inspections;

use Illuminate\Support\Collection;

/**
 * Filters walk points by rear axle Disc/Drum choice.
 */
final class InspectionWalkVisibility
{
    /**
     * @param  Collection<int, InspectionItem>  $items
     * @return Collection<int, InspectionItem>
     */
    public static function visibleItems(Inspection $inspection, Collection $items): Collection
    {
        $templateById = InspectionTemplateItem::query()
            ->whereIn('id', $items->pluck('inspection_template_item_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $rearType = $inspection->rear_axle_brake_type;

        return $items->filter(function (InspectionItem $item) use ($templateById, $rearType): bool {
            $templateItem = $templateById->get($item->inspection_template_item_id);
            if (! $templateItem instanceof InspectionTemplateItem) {
                return true;
            }

            $role = $templateItem->axle_role;
            if ($role === null || $role === '' || $role === 'front') {
                return true;
            }

            if ($rearType === Inspection::REAR_AXLE_DISC) {
                return $role === 'rear_disc';
            }

            if ($rearType === Inspection::REAR_AXLE_DRUM) {
                return $role === 'rear_drum';
            }

            // Axle not chosen yet — hide both rear brake paths until Disc/Drum selected.
            return false;
        })->values();
    }
}
