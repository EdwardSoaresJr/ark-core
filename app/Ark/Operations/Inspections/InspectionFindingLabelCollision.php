<?php

namespace App\Ark\Operations\Inspections;

/**
 * Deterministic Other Finding label collision against template-linked walk points.
 *
 * Phase 0 integrity: freeform must not invent a second condition for existing vocabulary.
 */
final class InspectionFindingLabelCollision
{
    public static function normalize(string $label): string
    {
        $normalized = mb_strtolower(trim($label));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    /**
     * First walk-visible template-linked inspection point whose label collides with $proposedLabel.
     */
    public static function collidingVisibleTemplatePoint(Inspection $inspection, string $proposedLabel): ?InspectionItem
    {
        $needle = self::normalize($proposedLabel);
        if ($needle === '') {
            return null;
        }

        $inspection->loadMissing('items');

        $templateLinked = $inspection->items
            ->filter(fn (InspectionItem $item): bool => $item->inspection_template_item_id !== null)
            ->values();

        $visible = InspectionWalkVisibility::visibleItems($inspection, $templateLinked);

        return $visible->first(
            fn (InspectionItem $item): bool => self::normalize((string) $item->label) === $needle
        );
    }

    /**
     * Any template-linked point on the inspection (including walk-hidden) with a colliding label.
     */
    public static function collidingTemplatePoint(Inspection $inspection, string $proposedLabel): ?InspectionItem
    {
        $needle = self::normalize($proposedLabel);
        if ($needle === '') {
            return null;
        }

        $inspection->loadMissing('items');

        return $inspection->items
            ->filter(fn (InspectionItem $item): bool => $item->inspection_template_item_id !== null)
            ->first(fn (InspectionItem $item): bool => self::normalize((string) $item->label) === $needle);
    }
}
