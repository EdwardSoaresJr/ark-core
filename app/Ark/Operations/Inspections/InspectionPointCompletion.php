<?php

namespace App\Ark\Operations\Inspections;

/**
 * Whether a checklist point is addressed for coverage (not lifecycle completion).
 */
final class InspectionPointCompletion
{
    public static function isAddressed(InspectionItem $item, ?InspectionTemplateItem $templateItem = null): bool
    {
        $item->loadMissing(['measurements', 'photos']);

        if ($item->observed_state === InspectionObservedState::NotChecked
            || $item->observed_state === null) {
            return false;
        }

        $status = InspectionChecklistStatus::fromObservedState(
            $item->observed_state instanceof InspectionObservedState
                ? $item->observed_state
                : InspectionObservedState::tryFrom((string) $item->observed_state) ?? InspectionObservedState::NotChecked,
        );

        if ($status === InspectionChecklistStatus::Na) {
            return true;
        }

        if (! $status instanceof InspectionChecklistStatus) {
            return false;
        }

        $templateItem ??= $item->inspection_template_item_id !== null
            ? InspectionTemplateItem::query()->find($item->inspection_template_item_id)
            : null;

        $slots = InspectionMeasurementSlots::required(
            InspectionMeasurementSlots::fromTemplateItem($templateItem),
        );

        foreach ($slots as $slot) {
            if (! self::slotFilled($item, $slot)) {
                return false;
            }
        }

        if ($templateItem?->requires_scan_evidence) {
            $hasPhoto = $item->photos->isNotEmpty();
            $hasNote = filled($item->notes);
            if (! $hasPhoto && ! $hasNote) {
                return false;
            }
        }

        if (in_array($status->value, InspectionTemplatePointMeta::requireObservationWhen($templateItem), true)) {
            $selected = is_array($item->selected_observations) ? $item->selected_observations : [];
            if ($selected === []) {
                return false;
            }
        }

        if (in_array($status->value, InspectionTemplatePointMeta::requireNoteWhen($templateItem), true)) {
            if (! filled($item->notes)) {
                return false;
            }
        }

        if (InspectionTemplatePointMeta::photoRequiredForStatus($templateItem, $status)) {
            if ($item->photos->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{key: string, name: string, unit: ?string, required: bool, type: string}  $slot
     */
    public static function slotFilled(InspectionItem $item, array $slot): bool
    {
        $measurement = $item->measurements->first(
            fn (InspectionItemMeasurement $row): bool => strcasecmp((string) $row->name, $slot['name']) === 0
                || strcasecmp((string) $row->name, $slot['key']) === 0,
        );

        if (! $measurement instanceof InspectionItemMeasurement) {
            return false;
        }

        return filled(trim((string) $measurement->value));
    }

    /**
     * @return list<string> missing required slot names
     */
    public static function missingRequiredSlotNames(InspectionItem $item, ?InspectionTemplateItem $templateItem = null): array
    {
        $item->loadMissing('measurements');
        $templateItem ??= $item->inspection_template_item_id !== null
            ? InspectionTemplateItem::query()->find($item->inspection_template_item_id)
            : null;

        $missing = [];
        foreach (InspectionMeasurementSlots::required(InspectionMeasurementSlots::fromTemplateItem($templateItem)) as $slot) {
            if (! self::slotFilled($item, $slot)) {
                $missing[] = $slot['name'];
            }
        }

        return $missing;
    }
}
