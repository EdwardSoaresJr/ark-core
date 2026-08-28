<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use App\Ark\Operations\Labor\LaborDiagnosticOverlapObservation;
use App\Ark\Operations\Labor\LaborExplanationDiagnosticOverlap;
use App\Ark\Operations\Labor\LaborExplanationProjection;
use App\Ark\Operations\Labor\LaborMatchAttributionProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

/**
 * Attaches doctrine-visible labor explanations to RTE search payloads.
 */
final class RteLaborExplanationAttacher
{
    public function __construct(
        private readonly LaborExplanationProjection $projection = new LaborExplanationProjection,
        private readonly LaborMatchAttributionProjection $matchAttribution = new LaborMatchAttributionProjection,
        private readonly LaborDiagnosticOverlapObservation $diagnosticOverlap = new LaborDiagnosticOverlapObservation,
        private readonly LaborExplanationDiagnosticOverlap $overlapMerge = new LaborExplanationDiagnosticOverlap,
    ) {}

    /**
     * @param  array<string, mixed>  $results
     * @param  array<string, mixed>|null  $matchContext
     * @return array<string, mixed>
     */
    public function attach(
        array $results,
        ?int $modelYear,
        ?RepairOrder $repairOrder = null,
        ?int $concernId = null,
        ?array $matchContext = null,
    ): array {
        $existingLaborLines = $this->existingLaborLines($repairOrder);

        if ($results['suggested_labor'] !== null && is_array($results['suggested_labor'])) {
            $packageLines = $this->linesFromSuggestedPackage($results['suggested_labor']);
            $overlapLines = [
                ...$packageLines,
                ...$this->linesFromOptionalDiagnostics($results['suggested_labor']),
            ];
            $overlap = $this->diagnosticOverlap->detect($overlapLines, $existingLaborLines, $concernId);

            $results['suggested_labor'] = [
                ...$results['suggested_labor'],
                'labor_explanation' => $this->overlapMerge->attachToExplanation(
                    $this->attachMatchAttribution(
                        $this->projection->variantMatrix($packageLines, $modelYear),
                        $packageLines,
                        $modelYear,
                        $matchContext,
                        (string) ($results['suggested_labor']['primary_lab_id'] ?? ''),
                    ),
                    $overlap,
                ),
            ];
        }

        if ($results['recommended_job'] !== null && is_array($results['recommended_job'])) {
            $results['recommended_job'] = [
                ...$results['recommended_job'],
                'labor_explanation' => $this->explanationForJob(
                    $results['recommended_job'],
                    $modelYear,
                    $existingLaborLines,
                    $concernId,
                    $matchContext,
                ),
            ];
        }

        foreach ($results['jobs'] ?? [] as $index => $job) {
            if (! is_array($job)) {
                continue;
            }

            $results['jobs'][$index] = [
                ...$job,
                'labor_explanation' => $this->explanationForJob(
                    $job,
                    $modelYear,
                    $existingLaborLines,
                    $concernId,
                    $matchContext,
                ),
            ];
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>|null  $matchContext
     * @return array<string, mixed>
     */
    private function explanationForJob(
        array $job,
        ?int $modelYear,
        array $existingLaborLines,
        ?int $concernId,
        ?array $matchContext,
    ): array {
        $jobLines = $this->linesFromJob($job, true);
        $overlapLines = [
            ...$jobLines,
            ...$this->linesFromOptionalDiagnosticsOnJob($job),
        ];
        $overlap = $this->diagnosticOverlap->detect($overlapLines, $existingLaborLines, $concernId);

        $matrix = [];

        foreach (RteLaborHoursBasis::cases() as $basis) {
            $matrix[$basis->value] = [
                'padding_on' => [
                    'addons_on' => $this->stripEngineering($this->projection->project(
                        $this->linesFromJob($job, true),
                        $modelYear,
                        $basis,
                        true,
                    )),
                    'addons_off' => $this->stripEngineering($this->projection->project(
                        $this->linesFromJob($job, false),
                        $modelYear,
                        $basis,
                        true,
                    )),
                ],
                'padding_off' => [
                    'addons_on' => $this->stripEngineering($this->projection->project(
                        $this->linesFromJob($job, true),
                        $modelYear,
                        $basis,
                        false,
                    )),
                    'addons_off' => $this->stripEngineering($this->projection->project(
                        $this->linesFromJob($job, false),
                        $modelYear,
                        $basis,
                        false,
                    )),
                ],
            ];
        }

        $matrix = $this->attachMatchAttribution(
            $matrix,
            $jobLines,
            $modelYear,
            $matchContext,
            (string) ($job['lab_id'] ?? ''),
        );

        return $this->overlapMerge->attachToExplanation($matrix, $overlap);
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $matrix
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>|null  $matchContext
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function attachMatchAttribution(
        array $matrix,
        array $lines,
        ?int $modelYear,
        ?array $matchContext,
        ?string $primaryLabId,
    ): array {
        if ($matchContext === null) {
            return $matrix;
        }

        foreach (RteLaborHoursBasis::cases() as $basis) {
            foreach ([['padding_on', true], ['padding_off', false]] as [$paddingKey, $applyPadding]) {
                $attribution = $this->matchAttribution->build(
                    $matchContext,
                    $lines,
                    $modelYear,
                    $basis,
                    $applyPadding,
                    $primaryLabId,
                );

                if ($attribution === null) {
                    continue;
                }

                if (isset($matrix[$basis->value][$paddingKey]['addons_on'])) {
                    $matrix[$basis->value][$paddingKey]['addons_on']['advisor_detail']['match_attribution'] = $attribution;
                }

                if (isset($matrix[$basis->value][$paddingKey]['addons_off'])) {
                    $matrix[$basis->value][$paddingKey]['addons_off']['advisor_detail']['match_attribution'] = $attribution;
                }

                $matrix[$basis->value][$paddingKey]['advisor_detail']['match_attribution'] = $attribution;
            }
        }

        return $matrix;
    }

    /**
     * @return list<array{
     *     id: int,
     *     description: string,
     *     repair_order_concern_id: int,
     *     concern_summary: string|null,
     * }>
     */
    private function existingLaborLines(?RepairOrder $repairOrder): array
    {
        if ($repairOrder === null) {
            return [];
        }

        $repairOrder->loadMissing('lines.concern');

        return $repairOrder->lines
            ->filter(fn ($line): bool => $line->type === RepairOrderLineType::Labor)
            ->map(fn ($line): array => [
                'id' => (int) $line->id,
                'description' => (string) $line->description,
                'repair_order_concern_id' => (int) $line->repair_order_concern_id,
                'concern_summary' => $line->concern?->summary,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $package
     * @return list<array<string, mixed>>
     */
    private function linesFromSuggestedPackage(array $package): array
    {
        $lines = [];

        foreach ($package['lines'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lines[] = $this->normalizeAttributionLine($line);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $package
     * @return list<array<string, mixed>>
     */
    private function linesFromOptionalDiagnostics(array $package): array
    {
        $lines = [];

        foreach ($package['optional_diagnostic_operations'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lines[] = $this->normalizeAttributionLine([
                ...$line,
                'kind' => 'optional_diagnostic_operation',
            ]);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $job
     * @return list<array<string, mixed>>
     */
    private function linesFromJob(array $job, bool $includeAddOns): array
    {
        $lines = [
            $this->normalizeAttributionLine([
                'description' => $job['job_desc'] ?? $job['lab_id'] ?? 'Labor',
                'lo_hr' => $job['lo_hr'] ?? null,
                'avg_hr' => $job['avg_hr'] ?? null,
                'hi_hr' => $job['hi_hr'] ?? null,
                'book_lo_hr' => $job['book_lo_hr'] ?? null,
                'book_avg_hr' => $job['book_avg_hr'] ?? null,
                'book_hi_hr' => $job['book_hi_hr'] ?? null,
                'kind' => 'primary',
                'lab_id' => $job['lab_id'] ?? null,
                'source_lab_id' => $job['lab_id'] ?? null,
            ]),
        ];

        if (! $includeAddOns) {
            return $lines;
        }

        foreach ($job['included_add_ons'] ?? [] as $included) {
            if (! is_array($included)) {
                continue;
            }

            $lines[] = $this->normalizeAttributionLine([
                'description' => $included['description'] ?? 'Included labor',
                'lo_hr' => $included['lo_hr'] ?? null,
                'avg_hr' => $included['avg_hr'] ?? null,
                'hi_hr' => $included['hi_hr'] ?? null,
                'book_lo_hr' => $included['book_lo_hr'] ?? null,
                'book_avg_hr' => $included['book_avg_hr'] ?? null,
                'book_hi_hr' => $included['book_hi_hr'] ?? null,
                'kind' => $included['kind'] ?? 'related_operation',
                'lab_id' => $included['lab_id'] ?? null,
                'source_lab_id' => $job['lab_id'] ?? null,
            ]);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $job
     * @return list<array<string, mixed>>
     */
    private function linesFromOptionalDiagnosticsOnJob(array $job): array
    {
        $lines = [];

        foreach ($job['optional_diagnostic_operations'] ?? [] as $optional) {
            if (! is_array($optional)) {
                continue;
            }

            $lines[] = $this->normalizeAttributionLine([
                'description' => $optional['description'] ?? 'Diagnostic operation',
                'lo_hr' => $optional['lo_hr'] ?? null,
                'avg_hr' => $optional['avg_hr'] ?? null,
                'hi_hr' => $optional['hi_hr'] ?? null,
                'book_lo_hr' => $optional['book_lo_hr'] ?? null,
                'book_avg_hr' => $optional['book_avg_hr'] ?? null,
                'book_hi_hr' => $optional['book_hi_hr'] ?? null,
                'kind' => 'optional_diagnostic_operation',
                'lab_id' => $optional['lab_id'] ?? null,
                'source_lab_id' => $job['lab_id'] ?? null,
            ]);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function normalizeAttributionLine(array $line): array
    {
        return [
            'label' => trim((string) ($line['description'] ?? $line['label'] ?? 'Labor')),
            'lo_hr' => $line['lo_hr'] ?? null,
            'avg_hr' => $line['avg_hr'] ?? null,
            'hi_hr' => $line['hi_hr'] ?? null,
            'book_lo_hr' => $line['book_lo_hr'] ?? null,
            'book_avg_hr' => $line['book_avg_hr'] ?? null,
            'book_hi_hr' => $line['book_hi_hr'] ?? null,
            'kind' => (string) ($line['kind'] ?? 'primary'),
            'lab_id' => $line['lab_id'] ?? null,
            'source_lab_id' => $line['source_lab_id'] ?? null,
        ];
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
}
