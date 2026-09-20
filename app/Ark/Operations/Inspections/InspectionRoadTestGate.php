<?php

namespace App\Ark\Operations\Inspections;

/**
 * Road-test performed gates finding points. Findings cannot be Good when performed is N/A.
 */
final class InspectionRoadTestGate
{
    public static function performedItem(Inspection $inspection): ?InspectionItem
    {
        $templateItemIds = InspectionTemplateItem::query()
            ->where('gate_group', 'road_test_performed')
            ->pluck('id')
            ->all();

        if ($templateItemIds === []) {
            return null;
        }

        return $inspection->items()
            ->whereIn('inspection_template_item_id', $templateItemIds)
            ->first();
    }

    public static function performedObservedState(Inspection $inspection): ?InspectionObservedState
    {
        $item = self::performedItem($inspection);
        if (! $item instanceof InspectionItem) {
            return null;
        }

        return $item->observed_state instanceof InspectionObservedState
            ? $item->observed_state
            : (InspectionObservedState::tryFrom((string) $item->observed_state) ?? InspectionObservedState::NotChecked);
    }

    public static function assertFindingStatusAllowed(
        Inspection $inspection,
        InspectionTemplateItem $templateItem,
        InspectionChecklistStatus $status,
    ): void {
        if (($templateItem->gate_group ?? null) !== 'road_test_findings') {
            return;
        }

        $performedState = self::performedObservedState($inspection);
        if ($performedState === null) {
            return;
        }

        if ($performedState === InspectionObservedState::NotChecked) {
            abort(422, 'Mark road test performed before recording road-test findings.');
        }

        if ($performedState === InspectionObservedState::Na && $status !== InspectionChecklistStatus::Na) {
            abort(422, 'Road test was not performed — findings must be N/A.');
        }
    }

    public static function findingLocked(Inspection $inspection, ?InspectionTemplateItem $templateItem): bool
    {
        if (($templateItem?->gate_group ?? null) !== 'road_test_findings') {
            return false;
        }

        $performedState = self::performedObservedState($inspection);

        return $performedState === InspectionObservedState::NotChecked;
    }

    public static function findingForceNa(Inspection $inspection, ?InspectionTemplateItem $templateItem): bool
    {
        if (($templateItem?->gate_group ?? null) !== 'road_test_findings') {
            return false;
        }

        return self::performedObservedState($inspection) === InspectionObservedState::Na;
    }
}
