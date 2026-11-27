<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;

/**
 * Shop Queue List tab accents.
 *
 * "All" is an aggregate filter — system slate, not a repair-order status and not
 * configurable in status/lane settings. Workflow lanes reuse RepairOrderStatusColor.
 */
final class JobBoardLaneTabPresentation
{
    public const ALL_KEY = 'all';

    /**
     * Tabler-compatible bg-* token for the All filter.
     * Distinct from Building Estimate (`building` / RepairOrderStatusColor::LIGHT).
     */
    public const ALL_ACCENT = 'ark-all';

    /**
     * Stored / projected color for a List tab.
     * All always returns the system accent; lanes keep their catalog color.
     */
    public static function colorForTab(string $key, ?string $laneColor = null): string
    {
        if ($key === self::ALL_KEY) {
            return self::ALL_ACCENT;
        }

        if ($laneColor !== null && $laneColor !== '') {
            return RepairOrderStatusColor::normalize($laneColor);
        }

        return self::fallbackLaneColor($key);
    }

    /**
     * bg-* / data-lane-tone class for top accent + active underline.
     */
    public static function tablerToneForTab(string $key, ?string $laneColor = null): string
    {
        if ($key === self::ALL_KEY) {
            return self::ALL_ACCENT;
        }

        return RepairOrderStatusColor::tablerColorForChipTone(
            RepairOrderStatusColor::chipTone(self::colorForTab($key, $laneColor)),
        );
    }

    /**
     * Missing-lane fallback. Estimates must not fall back to the All system accent.
     */
    public static function fallbackLaneColor(string $key): string
    {
        return $key === JobBoardLaneCatalogDefaults::ESTIMATES
            ? RepairOrderStatusColor::LIGHT
            : RepairOrderStatusColor::SECONDARY;
    }
}
