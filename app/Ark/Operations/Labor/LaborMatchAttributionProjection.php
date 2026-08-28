<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\LaborGuides\Rte\RteLaborHoursBasis;
use App\Ark\Operations\LaborGuides\Rte\RteShopLaborHoursProjection;

/**
 * Level 2 labor match attribution — guide row through doctrine layers to final hours.
 *
 * Observation only. Does not change matching, hours, or doctrine.
 */
final class LaborMatchAttributionProjection
{
    public function __construct(
        private readonly RteShopLaborHoursProjection $shopHours = new RteShopLaborHoursProjection,
    ) {}

    /**
     * @param  array{
     *     selected_application?: string|null,
     *     vehicle_label?: string|null,
     *     vehicle_engine_label?: string|null,
     * }  $matchContext
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>|null
     */
    public function build(
        array $matchContext,
        array $lines,
        ?int $modelYear,
        RteLaborHoursBasis $basis,
        bool $applyAgePadding,
        ?string $primaryLabId = null,
    ): ?array {
        if ($lines === []) {
            return null;
        }

        $multiplier = $this->shopHours->vehicleAgeMultiplier($modelYear);
        $tierLabel = $this->shopTierLabel($basis);
        $basisColumn = $basis->column();
        $bookColumn = 'book_'.$basis->value.'_hr';

        $primary = null;
        $relatedOperations = [];
        $finalTotal = 0.0;
        $hasPooledRelated = false;

        foreach ($lines as $line) {
            $attribution = $this->lineAttribution($line, $basis, $basisColumn, $bookColumn, $applyAgePadding, $multiplier);

            if ($attribution === null) {
                continue;
            }

            $finalTotal += $attribution['final_hours'];
            $kind = (string) ($line['kind'] ?? 'primary');

            if ($kind === 'primary') {
                $primary = $attribution;

                continue;
            }

            if ($primaryLabId !== null
                && filled($line['source_lab_id'] ?? null)
                && (string) $line['source_lab_id'] !== $primaryLabId) {
                $hasPooledRelated = true;
                $attribution['pooled_from_variant'] = true;
            }

            $relatedOperations[] = $attribution;
        }

        if ($primary === null) {
            return null;
        }

        return [
            'selected_application' => filled($matchContext['selected_application'] ?? null)
                ? (string) $matchContext['selected_application']
                : null,
            'vehicle' => $this->vehicleLabel($matchContext),
            'tier_label' => $tierLabel,
            'primary' => $primary,
            'adjustments' => $this->adjustmentLabels($primary, $tierLabel, $applyAgePadding, $multiplier),
            'related_operations' => $relatedOperations,
            'package_pooling' => $hasPooledRelated
                ? 'Related operation hours use the highest-time variant in this job family.'
                : null,
            'final_total' => round($finalTotal, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>|null
     */
    private function lineAttribution(
        array $line,
        RteLaborHoursBasis $basis,
        string $basisColumn,
        string $bookColumn,
        bool $applyAgePadding,
        float $multiplier,
    ): ?array {
        $shopHours = isset($line[$basisColumn]) && $line[$basisColumn] !== ''
            ? (float) $line[$basisColumn]
            : null;

        if ($shopHours === null || $shopHours <= 0) {
            return null;
        }

        $guideHours = isset($line[$bookColumn]) && $line[$bookColumn] !== ''
            ? (float) $line[$bookColumn]
            : $shopHours;

        $finalHours = $this->effectiveHours($shopHours, $basis, $applyAgePadding, $multiplier);
        $shopAdjustment = round($shopHours - $guideHours, 2);
        $ageAdjustment = round($finalHours - $shopHours, 2);

        $label = trim((string) ($line['label'] ?? $line['description'] ?? 'Labor'));
        $kind = (string) ($line['kind'] ?? 'primary');

        return [
            'guide_row' => $label,
            'lab_id' => filled($line['lab_id'] ?? null) ? (string) $line['lab_id'] : null,
            'guide_hours' => round($guideHours, 2),
            'shop_hours' => round($shopHours, 2),
            'shop_adjustment' => $shopAdjustment,
            'age_adjustment' => $ageAdjustment,
            'final_hours' => $finalHours,
            'display_label' => $this->displayLabel($label, $kind),
        ];
    }

    /**
     * @param  array<string, mixed>  $primary
     * @return list<string>
     */
    private function adjustmentLabels(
        array $primary,
        string $tierLabel,
        bool $applyAgePadding,
        float $multiplier,
    ): array {
        $labels = [];

        if (($primary['shop_adjustment'] ?? 0) != 0.0) {
            $labels[] = sprintf(
                '%s %s',
                $tierLabel,
                $this->signedHours((float) $primary['shop_adjustment']),
            );
        }

        if ($applyAgePadding
            && $multiplier > 1.0
            && ($primary['age_adjustment'] ?? 0) != 0.0) {
            $percent = (int) round(($multiplier - 1) * 100);
            $labels[] = sprintf('Age %s', $this->signedHours((float) $primary['age_adjustment']));
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $matchContext
     */
    private function vehicleLabel(array $matchContext): ?string
    {
        $vehicle = trim((string) ($matchContext['vehicle_label'] ?? ''));
        $engine = trim((string) ($matchContext['vehicle_engine_label'] ?? ''));

        if ($vehicle === '' && $engine === '') {
            return null;
        }

        if ($vehicle !== '' && $engine !== '') {
            return $vehicle.' · '.$engine;
        }

        return $vehicle !== '' ? $vehicle : $engine;
    }

    private function displayLabel(string $description, string $kind): string
    {
        $normalized = strtoupper(trim($description));

        if (str_contains($normalized, 'RADIATOR')) {
            return 'Radiator';
        }

        if (str_contains($normalized, 'DRAIN') && str_contains($normalized, 'FILL') && str_contains($normalized, 'COOL')) {
            return 'Drain & Fill';
        }

        if (str_contains($normalized, 'COMBUSTION') && str_contains($normalized, 'COOL')) {
            return 'Combustion Test';
        }

        if ($kind === 'primary') {
            return mb_convert_case(mb_strtolower(trim($description)), MB_CASE_TITLE, 'UTF-8');
        }

        return mb_convert_case(mb_strtolower(trim($description)), MB_CASE_TITLE, 'UTF-8');
    }

    private function shopTierLabel(RteLaborHoursBasis $basis): string
    {
        return match ($basis) {
            RteLaborHoursBasis::Lo => 'Shop Lo',
            RteLaborHoursBasis::Avg => 'Shop Avg',
            RteLaborHoursBasis::Hi => 'Shop Hi',
        };
    }

    private function effectiveHours(
        float $shopHours,
        RteLaborHoursBasis $basis,
        bool $applyAgePadding,
        float $multiplier,
    ): float {
        if (! $applyAgePadding || $basis === RteLaborHoursBasis::Lo || $multiplier <= 1.0) {
            return round($shopHours, 2);
        }

        return round($shopHours * $multiplier, 2);
    }

    private function signedHours(float $value): string
    {
        $formatted = number_format(abs($value), 2, '.', '');

        return ($value >= 0 ? '+' : '-').$formatted;
    }
}
