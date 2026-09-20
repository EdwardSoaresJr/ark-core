<?php

namespace App\Ark\Operations\ShopExcellence\PartsMatrixTune;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PartsMatrixTuneAssistant
{
    private const MIN_SAMPLE_LINES = 5;

    private const MAX_MARKUP_BUMP = 5.0;

    public function __construct(
        private readonly PartsMatrixHistoricalSampler $sampler,
        private readonly EstimateTotalsCalculator $calculator,
    ) {}

    /**
     * @param  array<int, string>|null  $proposedMarkups  Row index => markup % (simulation input)
     * @return array<string, mixed>
     */
    public function analyze(
        Carbon $from,
        Carbon $to,
        ?string $matrixKey = null,
        ?array $proposedMarkups = null,
    ): array {
        $settings = ShopSettings::current();
        $matrix = $settings->partsMatrixByKey($matrixKey) ?? $settings->defaultPartsMatrix();
        $matrixKey = $matrix['key'];
        $targets = ShopExcellenceTargets::current();
        $targetMargin = (int) $targets['parts_margin_target_percent'];

        $samples = $this->sampler->sample($from, $to, $matrixKey);
        $insufficientData = $samples->count() < self::MIN_SAMPLE_LINES;
        $mixPartsPercent = $this->mixPartsPercent($from, $to);

        $actualPosture = $this->aggregatePosture($samples, fn (PartsMatrixLineSample $line): int => $line->sellCents);
        $matrixPosture = $this->aggregatePosture($samples, function (PartsMatrixLineSample $line) use ($settings, $matrixKey): int {
            return $this->calculator->matrixSuggestedPriceCents($line->costCents, $settings, $matrixKey)
                ?? $line->sellCents;
        });

        $suggestedMarkups = $this->suggestMarkups(
            $matrix['rows'],
            $samples,
            $targetMargin,
            $actualPosture['margin_percent'],
        );

        $simulationMarkups = $this->resolveSimulationMarkups($matrix['rows'], $suggestedMarkups, $proposedMarkups);
        $proposedRows = $this->applyMarkupsToRows($matrix['rows'], $simulationMarkups);
        $simulatedPosture = $this->aggregatePosture($samples, function (PartsMatrixLineSample $line) use ($proposedRows): int {
            if ($line->sellOverridden) {
                return $line->sellCents;
            }

            return $this->calculator->matrixSuggestedPriceCentsForRows($line->costCents, $proposedRows)
                ?? $line->sellCents;
        });

        $tiers = $this->tierBreakdown($matrix['rows'], $samples, $settings, $matrixKey, $simulationMarkups);

        return [
            'from' => OperationalReportDateScope::shopDateString($from),
            'to' => OperationalReportDateScope::shopDateString($to),
            'range_label' => OperationalReportDateScope::shopRangeLabel($from, $to),
            'matrix_key' => $matrixKey,
            'matrix_name' => $matrix['name'],
            'sample_count' => $samples->count(),
            'insufficient_data' => $insufficientData,
            'minimum_sample_lines' => self::MIN_SAMPLE_LINES,
            'trustworthy_floor' => (string) config('ark-reports.trustworthy_data_starts_at'),
            'doctrine' => [
                'matrix_discipline' => 'Follow the parts matrix — margin comes from system pricing, not advisor discounting.',
                'review_cadence' => 'Review closed truth quarterly; simulate before changing live matrix policy.',
                'mix_note' => 'Parts/labor mix is structural (inspections, parts lists, ARO). Matrix tuning affects parts margin only.',
            ],
            'posture' => [
                'actual' => array_merge($actualPosture, [
                    'label' => 'Closed sample (actual sell)',
                    'tone' => ShopExcellenceTargets::toneForMinimumPercent($actualPosture['margin_percent'], $targetMargin),
                ]),
                'matrix_discipline' => array_merge($matrixPosture, [
                    'label' => 'If matrix price had been followed',
                    'tone' => ShopExcellenceTargets::toneForMinimumPercent($matrixPosture['margin_percent'], $targetMargin),
                ]),
                'simulated' => array_merge($simulatedPosture, [
                    'label' => 'Proposed matrix on same sample',
                    'tone' => ShopExcellenceTargets::toneForMinimumPercent($simulatedPosture['margin_percent'], $targetMargin),
                ]),
                'target_margin_percent' => $targetMargin,
                'margin_gap_points' => $actualPosture['margin_percent'] !== null
                    ? $actualPosture['margin_percent'] - $targetMargin
                    : null,
                'mix' => [
                    'parts_percent' => $mixPartsPercent,
                    'labor_percent' => $mixPartsPercent !== null ? 100 - $mixPartsPercent : null,
                    'target_parts_percent' => (int) $targets['parts_sales_target_percent'],
                    'target_labor_percent' => (int) $targets['labor_sales_target_percent'],
                    'tone' => ShopExcellenceTargets::toneForMixPercent(
                        $mixPartsPercent !== null ? 100 - $mixPartsPercent : null,
                        (int) $targets['labor_sales_target_percent'],
                    ),
                ],
                'context_kpis' => [],
            ],
            'discipline' => [
                'override_lines' => $samples->filter(fn (PartsMatrixLineSample $line): bool => $line->sellOverridden)->count(),
                'matrix_lines' => $samples->reject(fn (PartsMatrixLineSample $line): bool => $line->sellOverridden)->count(),
                'override_gp_cents' => (int) $samples
                    ->filter(fn (PartsMatrixLineSample $line): bool => $line->sellOverridden)
                    ->sum(fn (PartsMatrixLineSample $line): int => $line->grossProfitCents()),
            ],
            'tiers' => $tiers,
            'simulation' => [
                'proposed_markups' => $simulationMarkups,
                'margin_percent' => $simulatedPosture['margin_percent'],
                'delta_margin_points' => $actualPosture['margin_percent'] !== null && $simulatedPosture['margin_percent'] !== null
                    ? $simulatedPosture['margin_percent'] - $actualPosture['margin_percent']
                    : null,
                'additional_gp_cents' => max(0, $simulatedPosture['gp_cents'] - $actualPosture['gp_cents']),
                'meets_target' => $simulatedPosture['margin_percent'] !== null
                    && $simulatedPosture['margin_percent'] >= $targetMargin,
                'simulation_mode' => $proposedMarkups !== null ? 'owner_proposed' : 'suggested',
            ],
            'recommendation' => $this->recommendationSummary(
                $insufficientData,
                $actualPosture,
                $matrixPosture,
                $simulatedPosture,
                $targetMargin,
                $samples->count(),
            ),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveDefaultRange(?string $fromDate = null, ?string $toDate = null): array
    {
        $shopToday = OperationalReportDateScope::shopNow()->toDateString();
        $to = $toDate ?: $shopToday;
        $from = $fromDate ?: OperationalReportDateScope::shopNow()->subDays(89)->toDateString();

        [$fromCarbon, $toCarbon] = OperationalReportDateScope::resolveRange($from, $to);

        return [$fromCarbon, $toCarbon];
    }

    /**
     * @param  Collection<int, PartsMatrixLineSample>  $samples
     * @param  callable(PartsMatrixLineSample): int  $sellResolver
     * @return array{sales_cents: int, cost_cents: int, gp_cents: int, margin_percent: int|null}
     */
    private function aggregatePosture(Collection $samples, callable $sellResolver): array
    {
        if ($samples->isEmpty()) {
            return [
                'sales_cents' => 0,
                'cost_cents' => 0,
                'gp_cents' => 0,
                'margin_percent' => null,
            ];
        }

        $salesCents = 0;
        $costCents = 0;

        foreach ($samples as $line) {
            $sellCents = max(0, (int) $sellResolver($line));
            $salesCents += $sellCents;
            $costCents += $line->costCents;
        }

        $gpCents = $salesCents - $costCents;
        $marginPercent = $salesCents > 0 ? (int) round(($gpCents / $salesCents) * 100) : null;

        return [
            'sales_cents' => $salesCents,
            'cost_cents' => $costCents,
            'gp_cents' => $gpCents,
            'margin_percent' => $marginPercent,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, PartsMatrixLineSample>  $samples
     * @return array<int, string>
     */
    private function suggestMarkups(array $rows, Collection $samples, int $targetMargin, ?int $actualMargin): array
    {
        $markups = [];

        foreach ($rows as $index => $row) {
            $markups[$index] = number_format((float) ($row['markup_percentage'] ?? 0), 2, '.', '');
        }

        if ($samples->isEmpty() || $actualMargin === null || $actualMargin >= $targetMargin) {
            return $markups;
        }

        $gap = $targetMargin - $actualMargin;
        $bump = min(self::MAX_MARKUP_BUMP, max(0.5, round($gap / 2, 1)));
        $tierStats = $this->tierSamples($rows, $samples);
        $weights = collect($tierStats)->sortByDesc('sales_cents');

        if ($weights->isEmpty()) {
            return $markups;
        }

        $weakest = $weights
            ->filter(fn (array $tier): bool => $tier['sample_lines'] > 0 && ($tier['actual_margin_percent'] ?? 100) < $targetMargin)
            ->sortBy('actual_margin_percent')
            ->keys()
            ->take(2);

        if ($weakest->isEmpty()) {
            $weakest = $weights->take(1)->keys();
        }

        foreach ($weakest as $index) {
            $current = (float) $markups[$index];
            $markups[$index] = number_format(min(9999.99, $current + $bump), 2, '.', '');
        }

        return $markups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $suggested
     * @param  array<int, string>|null  $proposed
     * @return array<int, string>
     */
    private function resolveSimulationMarkups(array $rows, array $suggested, ?array $proposed): array
    {
        $resolved = [];

        foreach ($rows as $index => $row) {
            $fallback = $suggested[$index] ?? number_format((float) ($row['markup_percentage'] ?? 0), 2, '.', '');
            $resolved[$index] = isset($proposed[$index]) && $proposed[$index] !== ''
                ? number_format((float) $proposed[$index], 2, '.', '')
                : $fallback;
        }

        return $resolved;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $markups
     * @return array<int, array<string, mixed>>
     */
    private function applyMarkupsToRows(array $rows, array $markups): array
    {
        return collect($rows)
            ->map(function (array $row, int $index) use ($markups): array {
                $row['markup_percentage'] = $markups[$index] ?? $row['markup_percentage'];

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, PartsMatrixLineSample>  $samples
     * @return list<array<string, mixed>>
     */
    private function tierBreakdown(
        array $rows,
        Collection $samples,
        ShopSettings $settings,
        string $matrixKey,
        array $simulationMarkups,
    ): array {
        $tierSamples = $this->tierSamples($rows, $samples);
        $proposedRows = $this->applyMarkupsToRows($rows, $simulationMarkups);

        return collect($rows)->map(function (array $row, int $index) use (
            $tierSamples,
            $settings,
            $matrixKey,
            $proposedRows,
            $simulationMarkups,
        ): array {
            $stats = $tierSamples[$index] ?? [
                'sample_lines' => 0,
                'sales_cents' => 0,
                'cost_cents' => 0,
                'gp_cents' => 0,
                'actual_margin_percent' => null,
                'override_lines' => 0,
            ];

            $currentMarkup = number_format((float) ($row['markup_percentage'] ?? 0), 2, '.', '');
            $proposedMarkup = $simulationMarkups[$index] ?? $currentMarkup;
            $markupDelta = round((float) $proposedMarkup - (float) $currentMarkup, 2);

            $simulatedPosture = $this->aggregatePosture(
                collect($stats['lines'] ?? []),
                function (PartsMatrixLineSample $line) use ($proposedRows): int {
                    if ($line->sellOverridden) {
                        return $line->sellCents;
                    }

                    return $this->calculator->matrixSuggestedPriceCentsForRows($line->costCents, $proposedRows)
                        ?? $line->sellCents;
                },
            );

            return [
                'row_index' => $index,
                'min_cost' => $row['min_cost'],
                'max_cost' => $row['max_cost'],
                'current_markup' => $currentMarkup,
                'proposed_markup' => $proposedMarkup,
                'markup_delta' => $markupDelta,
                'current_margin_percent' => $settings->marginPercentageForMarkup($currentMarkup),
                'proposed_margin_percent' => $settings->marginPercentageForMarkup($proposedMarkup),
                'sample_lines' => $stats['sample_lines'],
                'override_lines' => $stats['override_lines'],
                'sales_cents' => $stats['sales_cents'],
                'actual_margin_percent' => $stats['actual_margin_percent'],
                'simulated_margin_percent' => $simulatedPosture['margin_percent'],
                'recommendation' => $this->tierRecommendation($markupDelta, $stats),
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, PartsMatrixLineSample>  $samples
     * @return array<int, array<string, mixed>>
     */
    private function tierSamples(array $rows, Collection $samples): array
    {
        $settings = ShopSettings::current();
        $tiers = [];

        foreach ($rows as $index => $row) {
            $tiers[$index] = [
                'sample_lines' => 0,
                'override_lines' => 0,
                'sales_cents' => 0,
                'cost_cents' => 0,
                'gp_cents' => 0,
                'actual_margin_percent' => null,
                'lines' => [],
            ];
        }

        foreach ($samples as $line) {
            $index = $this->resolveTierIndex($rows, $line->costCents);

            if ($index === null) {
                continue;
            }

            $tiers[$index]['sample_lines']++;
            $tiers[$index]['sales_cents'] += $line->sellCents;
            $tiers[$index]['cost_cents'] += $line->costCents;
            $tiers[$index]['gp_cents'] += $line->grossProfitCents();
            $tiers[$index]['lines'][] = $line;

            if ($line->sellOverridden) {
                $tiers[$index]['override_lines']++;
            }
        }

        foreach ($tiers as $index => $tier) {
            if ($tier['sales_cents'] <= 0) {
                continue;
            }

            $tiers[$index]['actual_margin_percent'] = (int) round(($tier['gp_cents'] / $tier['sales_cents']) * 100);
        }

        return $tiers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function resolveTierIndex(array $rows, int $costCents): ?int
    {
        $costDollars = $costCents / 100;

        foreach ($rows as $index => $row) {
            $from = (float) $row['min_cost'];
            $to = ($row['max_cost'] ?? null) === null || $row['max_cost'] === ''
                ? null
                : (float) $row['max_cost'];

            if ($costDollars < $from || ($to !== null && $costDollars > $to)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function tierRecommendation(float $markupDelta, array $stats): ?string
    {
        if (($stats['sample_lines'] ?? 0) === 0) {
            return 'No closed sample in range';
        }

        if ($markupDelta > 0) {
            return '+'.number_format($markupDelta, 2).'% markup in simulation';
        }

        if (($stats['override_lines'] ?? 0) > 0) {
            return 'Overrides present — matrix discipline review';
        }

        return 'On sampled posture';
    }

    /**
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $matrix
     * @param  array<string, mixed>  $simulated
     * @return array{headline: string, detail: string, action: string}
     */
    private function recommendationSummary(
        bool $insufficientData,
        array $actual,
        array $matrix,
        array $simulated,
        int $targetMargin,
        int $sampleCount,
    ): array {
        if ($insufficientData) {
            return [
                'headline' => 'Waiting for closed part-line history',
                'detail' => 'Need at least '.self::MIN_SAMPLE_LINES.' closed part lines with known cost in range. Data after '.$this->trustworthyFloorLabel().' counts toward analysis.',
                'action' => 'Run this review after more ROs close, or widen the date range.',
            ];
        }

        $actualMargin = $actual['margin_percent'];
        $gap = $actualMargin !== null ? $targetMargin - $actualMargin : null;

        if ($actualMargin !== null && $actualMargin >= $targetMargin) {
            return [
                'headline' => 'Sampled parts margin meets target',
                'detail' => "Closed sample at {$actualMargin}% on {$sampleCount} part lines. Matrix discipline posture: ".($matrix['margin_percent'] ?? 'n/a').'%.',
                'action' => 'Quarterly review only — mark Owner Targets when verified.',
            ];
        }

        $simulatedMargin = $simulated['margin_percent'];
        $simNote = $simulatedMargin !== null
            ? " Proposed simulation: {$simulatedMargin}% on same sample."
            : '';

        return [
            'headline' => $gap !== null
                ? abs($gap).' point'.($gap === 1 ? '' : 's').' below parts margin target'
                : 'Parts margin below target',
            'detail' => "Actual closed sample: {$actualMargin}%. Target: {$targetMargin}%.{$simNote} Overrides and manual sells stay fixed in simulation.",
            'action' => 'Simulate tier markups below, then apply manually in Settings → Parts Matrix. Mark target review complete when live.',
        ];
    }

    private function mixPartsPercent(Carbon $from, Carbon $to): ?int
    {
        $parts = (int) OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $from, $to))
            ->where('repair_order_lines.type', RepairOrderLineType::Part)
            ->sum('repair_order_lines.subtotal_cents');

        $labor = (int) OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $from, $to))
            ->where('repair_order_lines.type', RepairOrderLineType::Labor)
            ->sum('repair_order_lines.subtotal_cents');

        $total = $parts + $labor;

        if ($total <= 0) {
            return null;
        }

        return (int) round(($parts / $total) * 100);
    }

    private function trustworthyFloorLabel(): string
    {
        return OperationalReportDateScope::shopNow()
            ->parse((string) config('ark-reports.trustworthy_data_starts_at'))
            ->format('M j, Y');
    }
}
