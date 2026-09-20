<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;

final class InspectionWorkspaceUrl
{
    public const SURFACE_TABLET = 'tablet';

    /**
     * Production inspection host — not Estimate Review.
     *
     * @param  array<string, scalar|null>  $query
     */
    public static function show(RepairOrder $repairOrder, array $query = [], ?string $fragment = null): string
    {
        $url = route('operations.repair-orders.inspection.show', [
            'repairOrder' => $repairOrder,
            ...array_filter($query, fn ($value): bool => $value !== null && $value !== ''),
        ]);

        if ($fragment === null || $fragment === '') {
            return $url;
        }

        return $url.'#'.ltrim($fragment, '#');
    }

    public static function capture(RepairOrder $repairOrder, ?int $concernId = null, ?string $surface = null): string
    {
        $query = ['capture' => 1];

        if ($concernId !== null) {
            $query['concern'] = $concernId;
        }

        if ($surface !== null && $surface !== '') {
            $query['surface'] = $surface;
        }

        return self::show($repairOrder, $query);
    }

    public static function finding(RepairOrder $repairOrder, InspectionItem $item, ?string $surface = null): string
    {
        $query = ['point' => $item->id];

        if ($surface !== null && $surface !== '') {
            $query['surface'] = $surface;
        }

        return self::show($repairOrder, $query, 'finding-'.$item->id);
    }

    public static function point(RepairOrder $repairOrder, int $pointId, ?string $surface = null): string
    {
        $query = ['point' => $pointId];

        if ($surface !== null && $surface !== '') {
            $query['surface'] = $surface;
        }

        return self::show($repairOrder, $query);
    }

    public static function tablet(RepairOrder $repairOrder, ?int $pointId = null): string
    {
        if ($pointId !== null) {
            return self::point($repairOrder, $pointId, self::SURFACE_TABLET);
        }

        return self::show($repairOrder, ['surface' => self::SURFACE_TABLET]);
    }

    public static function normalizeSurface(?string $surface): ?string
    {
        return $surface === self::SURFACE_TABLET ? self::SURFACE_TABLET : null;
    }

    /**
     * Advisor review still lives on Estimate Review — Inspect tab only.
     *
     * @param  array<string, scalar|null>  $query
     */
    public static function advisorReview(RepairOrder $repairOrder, array $query = []): string
    {
        $url = route('operations.repair-orders.show', [
            'repairOrder' => $repairOrder,
            ...array_filter($query, fn ($value): bool => $value !== null && $value !== ''),
        ]);

        return $url.'#inspect';
    }
}
