<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Observation-only pad wear comparison. Never diagnoses or creates Concerns.
 */
final class InspectionBrakeComparison
{
    public const DEFAULT_SAME_WHEEL_MM = 2.0;

    public const DEFAULT_AXLE_LR_MM = 2.0;

    /**
     * @return array{same_wheel_mm: float, axle_lr_mm: float}
     */
    public static function thresholds(?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $config = is_array($settings?->inspection_comparison) ? $settings->inspection_comparison : [];

        return [
            'same_wheel_mm' => (float) ($config['same_wheel_mm'] ?? self::DEFAULT_SAME_WHEEL_MM),
            'axle_lr_mm' => (float) ($config['axle_lr_mm'] ?? self::DEFAULT_AXLE_LR_MM),
        ];
    }

    /**
     * @return list<array{message: string, helper: string, delta_mm: float, kind: string}>
     */
    public static function promptsForItem(InspectionItem $item, Inspection $inspection): array
    {
        $item->loadMissing('measurements');
        $templateItem = $item->inspection_template_item_id !== null
            ? InspectionTemplateItem::query()->find($item->inspection_template_item_id)
            : null;

        if (! $templateItem instanceof InspectionTemplateItem) {
            return [];
        }

        $pointKey = (string) ($templateItem->point_key ?? '');
        $isPadPoint = str_ends_with($pointKey, '_brake')
            || str_contains($pointKey, '_brake_pads')
            || str_contains($pointKey, '_brake_disc');
        if (! $isPadPoint
            || str_contains($pointKey, 'drum')
            || str_contains($pointKey, 'parking')
            || str_contains($pointKey, 'hose')
            || str_contains($pointKey, 'fluid')) {
            return [];
        }

        $inner = self::padInner($item);
        $outer = self::padOuter($item);
        $thresholds = self::thresholds();
        $prompts = [];

        if ($inner !== null && $outer !== null) {
            $delta = abs($inner - $outer);
            if ($delta >= $thresholds['same_wheel_mm']) {
                $prompts[] = self::prompt($delta, 'same_wheel');
            }
        }

        $pair = self::axlePairItem($inspection, $templateItem);
        if ($pair instanceof InspectionItem) {
            $pair->loadMissing('measurements');
            $thisAvg = self::padAverage($item);
            $pairAvg = self::padAverage($pair);
            if ($thisAvg !== null && $pairAvg !== null) {
                $delta = abs($thisAvg - $pairAvg);
                if ($delta >= $thresholds['axle_lr_mm']) {
                    $prompts[] = self::prompt($delta, 'axle_lr');
                }
            }
        }

        return $prompts;
    }

    /**
     * @return array{message: string, helper: string, delta_mm: float, kind: string}
     */
    private static function prompt(float $delta, string $kind): array
    {
        return [
            'kind' => $kind,
            'delta_mm' => round($delta, 2),
            'message' => 'Pad wear differs by '.number_format($delta, 1).' mm. Take another look before moving on.',
            'helper' => 'Uneven wear can come from pad movement, hardware, slides, caliper operation, or other brake problems.',
        ];
    }

    private static function numericSlot(InspectionItem $item, string $name, string $key): ?float
    {
        $measurement = $item->measurements->first(
            fn (InspectionItemMeasurement $row): bool => strcasecmp((string) $row->name, $name) === 0
                || strcasecmp((string) $row->name, $key) === 0,
        );

        if (! $measurement instanceof InspectionItemMeasurement || ! is_numeric($measurement->value)) {
            return null;
        }

        return (float) $measurement->value;
    }

    private static function padInner(InspectionItem $item): ?float
    {
        return self::numericSlot($item, 'Inner pad', 'inner')
            ?? self::numericSlot($item, 'Inboard pad', 'inboard');
    }

    private static function padOuter(InspectionItem $item): ?float
    {
        return self::numericSlot($item, 'Outer pad', 'outer')
            ?? self::numericSlot($item, 'Outboard pad', 'outboard');
    }

    private static function padAverage(InspectionItem $item): ?float
    {
        $inner = self::padInner($item);
        $outer = self::padOuter($item);

        if ($inner === null || $outer === null) {
            return null;
        }

        return ($inner + $outer) / 2;
    }

    private static function axlePairItem(Inspection $inspection, InspectionTemplateItem $templateItem): ?InspectionItem
    {
        $key = (string) ($templateItem->point_key ?? '');
        $pairKey = match (true) {
            str_contains($key, '_lf_') => str_replace('_lf_', '_rf_', $key),
            str_contains($key, '_rf_') => str_replace('_rf_', '_lf_', $key),
            str_contains($key, '_lr_') => str_replace('_lr_', '_rr_', $key),
            str_contains($key, '_rr_') => str_replace('_rr_', '_lr_', $key),
            default => null,
        };

        if ($pairKey === null) {
            return null;
        }

        $pairTemplateId = InspectionTemplateItem::query()
            ->where('point_key', $pairKey)
            ->value('id');

        if ($pairTemplateId === null) {
            return null;
        }

        return $inspection->items()
            ->where('inspection_template_item_id', $pairTemplateId)
            ->first();
    }
}
