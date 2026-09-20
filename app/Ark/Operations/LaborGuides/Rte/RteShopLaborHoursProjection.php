<?php

namespace App\Ark\Operations\LaborGuides\Rte;

/**
 * Shop labor hours — RTE book times remapped for conservative shop estimates.
 *
 * Base tiers (no vehicle-age padding):
 * - Lo  = RTE book average
 * - Avg = weighted toward book high
 * - Hi  = book high plus ceiling headroom
 *
 * Vehicle-age padding is applied separately on Avg and Hi when enabled.
 */
final class RteShopLaborHoursProjection
{
    public function __construct(
        private readonly ?bool $enabledOverride = null,
        private readonly ?float $avgWeightTowardHiOverride = null,
        private readonly ?float $hiCeilingMultiplierOverride = null,
    ) {}

    public function enabled(): bool
    {
        if ($this->enabledOverride !== null) {
            return $this->enabledOverride;
        }

        return (bool) config('rte-labor-guide.shop_hours_projection', true);
    }

    /**
     * @return array{lo_hr: float|null, avg_hr: float|null, hi_hr: float|null}
     */
    public function project(?float $rteLo, ?float $rteAvg, ?float $rteHi): array
    {
        if (! $this->enabled()) {
            return [
                'lo_hr' => $this->round($rteLo),
                'avg_hr' => $this->round($rteAvg),
                'hi_hr' => $this->round($rteHi),
            ];
        }

        $bookAvg = $this->positive($rteAvg) ?? $this->positive($rteLo) ?? $this->positive($rteHi);
        $bookHi = $this->positive($rteHi) ?? $bookAvg;

        if ($bookAvg === null) {
            return [
                'lo_hr' => null,
                'avg_hr' => null,
                'hi_hr' => null,
            ];
        }

        $weight = $this->avgWeightTowardHi();
        $span = max($bookHi - $bookAvg, 0.0);

        $shopLo = round($bookAvg, 2);
        $shopAvg = round($bookAvg + ($span * $weight), 2);
        $shopHi = round($bookHi * $this->hiCeilingMultiplier(), 2);

        if ($shopHi < $shopAvg) {
            $shopHi = $shopAvg;
        }

        return [
            'lo_hr' => $shopLo,
            'avg_hr' => $shopAvg,
            'hi_hr' => $shopHi,
        ];
    }

    /**
     * @param  array{lo_hr?: float|null, avg_hr?: float|null, hi_hr?: float|null}  $hours
     * @return array{lo_hr: float|null, avg_hr: float|null, hi_hr: float|null}
     */
    public function applyAgePaddingToHours(array $hours, float $multiplier): array
    {
        if ($multiplier <= 1.0) {
            return [
                'lo_hr' => $this->round($hours['lo_hr'] ?? null),
                'avg_hr' => $this->round($hours['avg_hr'] ?? null),
                'hi_hr' => $this->round($hours['hi_hr'] ?? null),
            ];
        }

        $lo = $this->round($hours['lo_hr'] ?? null);
        $avg = isset($hours['avg_hr']) && $hours['avg_hr'] !== ''
            ? round((float) $hours['avg_hr'] * $multiplier, 2)
            : null;
        $hi = isset($hours['hi_hr']) && $hours['hi_hr'] !== ''
            ? round((float) $hours['hi_hr'] * $multiplier, 2)
            : null;

        if ($avg !== null && $hi !== null && $hi < $avg) {
            $hi = $avg;
        }

        return [
            'lo_hr' => $lo,
            'avg_hr' => $avg,
            'hi_hr' => $hi,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function applyToRow(array $row): array
    {
        $projected = $this->project(
            isset($row['lo_hr']) && $row['lo_hr'] !== '' ? (float) $row['lo_hr'] : null,
            isset($row['avg_hr']) && $row['avg_hr'] !== '' ? (float) $row['avg_hr'] : null,
            isset($row['hi_hr']) && $row['hi_hr'] !== '' ? (float) $row['hi_hr'] : null,
        );

        return [
            ...$row,
            ...$projected,
        ];
    }

    public function vehicleAgeMultiplier(?int $modelYear): float
    {
        if ($modelYear === null || $modelYear <= 0) {
            return 1.0;
        }

        $age = max(0, (int) date('Y') - $modelYear);

        foreach ((array) config('rte-labor-guide.vehicle_age_padding', []) as $tier) {
            $minAge = (int) ($tier['min_age'] ?? 0);
            $maxAge = isset($tier['max_age']) && $tier['max_age'] !== null
                ? (int) $tier['max_age']
                : null;

            if ($age < $minAge) {
                continue;
            }

            if ($maxAge !== null && $age > $maxAge) {
                continue;
            }

            return (float) ($tier['multiplier'] ?? 1.0);
        }

        return 1.0;
    }

    private function avgWeightTowardHi(): float
    {
        if ($this->avgWeightTowardHiOverride !== null) {
            return $this->avgWeightTowardHiOverride;
        }

        return (float) config('rte-labor-guide.shop_hours.avg_weight_toward_hi', 0.85);
    }

    private function hiCeilingMultiplier(): float
    {
        if ($this->hiCeilingMultiplierOverride !== null) {
            return $this->hiCeilingMultiplierOverride;
        }

        return (float) config('rte-labor-guide.shop_hours.hi_ceiling_multiplier', 1.08);
    }

    private function positive(?float $value): ?float
    {
        if ($value === null || $value <= 0) {
            return null;
        }

        return $value;
    }

    private function round(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return round($value, 2);
    }
}
