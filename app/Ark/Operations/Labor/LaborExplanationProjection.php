<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\LaborGuides\Rte\RteLaborHoursBasis;
use App\Ark\Operations\LaborGuides\Rte\RteShopLaborHoursProjection;

/**
 * Doctrine visibility for labor — advisor summary and detail at apply time.
 *
 * Engineering detail is computed for tests only; never expose in operational UI.
 */
final class LaborExplanationProjection
{
    public function __construct(
        private readonly RteShopLaborHoursProjection $shopHours = new RteShopLaborHoursProjection,
    ) {}

    /**
     * @param  list<array{
     *     label: string,
     *     lo_hr?: float|null,
     *     avg_hr?: float|null,
     *     hi_hr?: float|null,
     *     kind?: string,
     *     book_lo_hr?: float|null,
     *     book_avg_hr?: float|null,
     *     book_hi_hr?: float|null,
     *     lab_id?: string|null,
     *     source_lab_id?: string|null,
     * }>  $lines
     * @return array{
     *     advisor_summary: array{
     *         total_hours: float,
     *         tier_label: string,
     *         age_label: string|null,
     *         includes: list<string>,
     *     },
     *     advisor_detail: array{
     *         lines: list<array{label: string, hours: float}>,
     *         vehicle_age_years: int|null,
     *         age_padding_label: string|null,
     *         tier_label: string,
     *     },
     *     engineering_detail: array<string, mixed>,
     * }
     */
    public function project(
        array $lines,
        ?int $modelYear,
        RteLaborHoursBasis $basis,
        bool $applyAgePadding,
    ): array {
        $multiplier = $this->shopHours->vehicleAgeMultiplier($modelYear);
        $vehicleAgeYears = $this->vehicleAgeYears($modelYear);
        $tierLabel = $this->shopTierLabel($basis);
        $ageLabel = $this->ageLabel($basis, $applyAgePadding, $multiplier);
        $agePaddingLabel = $ageLabel;

        $detailLines = [];
        $includes = [];
        $totalHours = 0.0;
        $engineeringLines = [];

        foreach ($lines as $line) {
            $column = $basis->column();
            $baseHours = isset($line[$column]) && $line[$column] !== ''
                ? (float) $line[$column]
                : null;

            if ($baseHours === null || $baseHours <= 0) {
                continue;
            }

            $hours = $this->effectiveHours($baseHours, $basis, $applyAgePadding, $multiplier);
            $totalHours += $hours;

            $kind = (string) ($line['kind'] ?? 'primary');
            $label = trim((string) ($line['label'] ?? 'Labor'));

            $detailLines[] = [
                'label' => $this->detailLineLabel($label, $kind),
                'hours' => $hours,
            ];

            if ($kind !== 'primary') {
                $includes[] = $this->includeLabel($label, $kind);
            }

            $engineeringLines[] = [
                'label' => $label,
                'kind' => $kind,
                'shop_hours' => round($baseHours, 2),
                'effective_hours' => $hours,
                'book_lo_hr' => $line['book_lo_hr'] ?? null,
                'book_avg_hr' => $line['book_avg_hr'] ?? null,
                'book_hi_hr' => $line['book_hi_hr'] ?? null,
                'lab_id' => $line['lab_id'] ?? null,
                'source_lab_id' => $line['source_lab_id'] ?? null,
            ];
        }

        $includes = array_values(array_unique($includes));

        return [
            'advisor_summary' => [
                'total_hours' => round($totalHours, 2),
                'tier_label' => $tierLabel,
                'age_label' => $ageLabel,
                'includes' => $includes,
            ],
            'advisor_detail' => [
                'lines' => $detailLines,
                'vehicle_age_years' => $vehicleAgeYears,
                'age_padding_label' => $agePaddingLabel,
                'tier_label' => $tierLabel,
            ],
            'engineering_detail' => [
                'basis' => $basis->value,
                'apply_age_padding' => $applyAgePadding,
                'vehicle_age_multiplier' => $multiplier,
                'model_year' => $modelYear,
                'lines' => $engineeringLines,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function variantMatrix(array $lines, ?int $modelYear): array
    {
        $matrix = [];

        foreach (RteLaborHoursBasis::cases() as $basis) {
            $matrix[$basis->value] = [
                'padding_on' => $this->stripEngineering($this->project($lines, $modelYear, $basis, true)),
                'padding_off' => $this->stripEngineering($this->project($lines, $modelYear, $basis, false)),
            ];
        }

        return $matrix;
    }

    /**
     * @param  array<string, mixed>  $projection
     * @return array<string, mixed>
     */
    private function stripEngineering(array $projection): array
    {
        return [
            'advisor_summary' => $projection['advisor_summary'],
            'advisor_detail' => $projection['advisor_detail'],
        ];
    }

    private function effectiveHours(
        float $baseHours,
        RteLaborHoursBasis $basis,
        bool $applyAgePadding,
        float $multiplier,
    ): float {
        if (! $applyAgePadding || $basis === RteLaborHoursBasis::Lo || $multiplier <= 1.0) {
            return round($baseHours, 2);
        }

        return round($baseHours * $multiplier, 2);
    }

    private function shopTierLabel(RteLaborHoursBasis $basis): string
    {
        return match ($basis) {
            RteLaborHoursBasis::Lo => 'Shop Lo',
            RteLaborHoursBasis::Avg => 'Shop Avg',
            RteLaborHoursBasis::Hi => 'Shop Hi',
        };
    }

    private function ageLabel(
        RteLaborHoursBasis $basis,
        bool $applyAgePadding,
        float $multiplier,
    ): ?string {
        if (! $applyAgePadding || $basis === RteLaborHoursBasis::Lo || $multiplier <= 1.0) {
            return null;
        }

        $percent = (int) round(($multiplier - 1) * 100);

        return 'Age +'.$percent.'%';
    }

    private function vehicleAgeYears(?int $modelYear): ?int
    {
        if ($modelYear === null || $modelYear <= 0) {
            return null;
        }

        return max(0, (int) date('Y') - $modelYear);
    }

    private function includeLabel(string $description, string $kind): string
    {
        $normalized = strtoupper(trim($description));

        if (str_contains($normalized, 'DRAIN') && str_contains($normalized, 'FILL') && str_contains($normalized, 'COOL')) {
            return 'Coolant drain & refill';
        }

        if (str_contains($normalized, 'COMBUSTION') && str_contains($normalized, 'COOL')) {
            return 'Cooling system verification';
        }

        if ($kind === 'bundled_add_on') {
            return $this->titleCaseDescription($description);
        }

        return $this->titleCaseDescription($description);
    }

    private function detailLineLabel(string $description, string $kind): string
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
            return $this->titleCaseDescription($description);
        }

        return $this->titleCaseDescription($description);
    }

    private function titleCaseDescription(string $description): string
    {
        $trimmed = trim($description);

        if ($trimmed === '') {
            return 'Labor';
        }

        return mb_convert_case(mb_strtolower($trimmed), MB_CASE_TITLE, 'UTF-8');
    }
}
