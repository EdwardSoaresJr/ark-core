<?php

namespace App\Ark\Operations\LaborGuides\Rte;

/**
 * Builds a suggested multi-line labor package from the likely match and sibling RTE rows.
 */
final class RteLaborSuggestedPackageBuilder
{
    public function __construct(
        private readonly RteLaborJobRecommender $recommender = new RteLaborJobRecommender,
    ) {}

    /**
     * @param  array<string, mixed>|null  $recommendedJob
     * @param  list<array<string, mixed>>  $alternateJobs
     * @param  list<array<string, mixed>>  $allJobs
     * @return array<string, mixed>|null
     */
    public function build(
        ?array $recommendedJob,
        array $alternateJobs,
        array $allJobs,
        RteLaborVehicleEngineProfile $engineProfile,
        ?string $term,
    ): ?array {
        if ($recommendedJob === null || ! filled($term)) {
            return null;
        }

        $primaryJobCode = $this->jobCode($recommendedJob);

        if ($primaryJobCode === null) {
            return null;
        }

        $familyRows = array_values(array_filter(
            $allJobs,
            fn (array $row): bool => $this->jobCode($row) === $primaryJobCode,
        ));

        if ($familyRows === []) {
            $familyRows = [$recommendedJob];
        }

        $lines = [$this->primaryLine($recommendedJob)];

        foreach ($this->bestIncludedLines($familyRows, $engineProfile, $term) as $includedLine) {
            $lines[] = $includedLine;
        }

        $optionalDiagnostics = $this->optionalDiagnosticOperations($familyRows);

        return [
            'title' => $this->titleFor($recommendedJob, $term),
            'primary_lab_id' => (string) ($recommendedJob['lab_id'] ?? ''),
            'search_term' => trim((string) $term),
            'line_count' => count($lines),
            'lines' => $lines,
            'optional_diagnostic_operations' => $optionalDiagnostics,
            'total_lo_hr' => round(array_sum(array_column($lines, 'lo_hr')), 2),
            'total_avg_hr' => round(array_sum(array_column($lines, 'avg_hr')), 2),
            'total_hi_hr' => round(array_sum(array_column($lines, 'hi_hr')), 2),
            'summary' => $this->summaryFor($lines),
            'rationale' => $this->rationaleFor($recommendedJob, $familyRows, $engineProfile, count($lines) - 1),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $familyRows
     * @return list<array<string, mixed>>
     */
    private function bestIncludedLines(
        array $familyRows,
        RteLaborVehicleEngineProfile $engineProfile,
        string $term,
    ): array {
        $bestByKey = [];

        foreach ($familyRows as $row) {
            $parentScore = $this->recommender->scoreRow($row, $term, $engineProfile);

            foreach ($row['included_add_ons'] ?? [] as $included) {
                $key = $this->includedLineKey($included);

                if ($key === null) {
                    continue;
                }

                if (! isset($bestByKey[$key]) || $this->shouldPreferIncluded(
                    $included,
                    $parentScore,
                    $bestByKey[$key]['included'],
                    $bestByKey[$key]['parent_score'],
                )) {
                    $bestByKey[$key] = [
                        'included' => $included,
                        'parent_score' => $parentScore,
                        'source_lab_id' => (string) ($row['lab_id'] ?? ''),
                    ];
                }
            }
        }

        uasort(
            $bestByKey,
            fn (array $left, array $right): int => strcmp(
                (string) ($left['included']['description'] ?? ''),
                (string) ($right['included']['description'] ?? ''),
            ),
        );

        $lines = [];

        foreach ($bestByKey as $entry) {
            $line = $this->includedLine($entry['included'], $entry['source_lab_id']);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $familyRows
     * @return list<array<string, mixed>>
     */
    private function optionalDiagnosticOperations(array $familyRows): array
    {
        $bestByKey = [];

        foreach ($familyRows as $row) {
            foreach ($row['optional_diagnostic_operations'] ?? [] as $optional) {
                $key = $this->includedLineKey($optional);

                if ($key === null) {
                    continue;
                }

                if (! isset($bestByKey[$key]) || $this->includedLaborRank($optional) > $this->includedLaborRank($bestByKey[$key])) {
                    $bestByKey[$key] = $optional;
                }
            }
        }

        uasort(
            $bestByKey,
            fn (array $left, array $right): int => strcmp(
                (string) ($left['description'] ?? ''),
                (string) ($right['description'] ?? ''),
            ),
        );

        $lines = [];

        foreach ($bestByKey as $optional) {
            $line = $this->includedLine($optional, (string) ($optional['source_lab_id'] ?? $optional['lab_id'] ?? ''));

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $recommendedJob
     * @return array<string, mixed>
     */
    private function primaryLine(array $recommendedJob): array
    {
        return [
            'kind' => 'primary',
            'description' => trim((string) ($recommendedJob['job_desc'] ?? $recommendedJob['lab_id'] ?? 'Labor')),
            'lab_id' => (string) ($recommendedJob['lab_id'] ?? ''),
            'add_id' => null,
            'lo_hr' => (float) ($recommendedJob['lo_hr'] ?? 0),
            'avg_hr' => (float) ($recommendedJob['avg_hr'] ?? 0),
            'hi_hr' => (float) ($recommendedJob['hi_hr'] ?? 0),
            'book_lo_hr' => isset($recommendedJob['book_lo_hr']) ? (float) $recommendedJob['book_lo_hr'] : null,
            'book_avg_hr' => isset($recommendedJob['book_avg_hr']) ? (float) $recommendedJob['book_avg_hr'] : null,
            'book_hi_hr' => isset($recommendedJob['book_hi_hr']) ? (float) $recommendedJob['book_hi_hr'] : null,
            'source_lab_id' => (string) ($recommendedJob['lab_id'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $included
     * @return array<string, mixed>|null
     */
    private function includedLine(array $included, string $sourceLabId): ?array
    {
        $avg = (float) ($included['avg_hr'] ?? 0);

        if ($avg <= 0) {
            return null;
        }

        return [
            'kind' => (string) ($included['kind'] ?? 'related_operation'),
            'description' => trim((string) ($included['description'] ?? 'Included labor')),
            'lab_id' => filled($included['lab_id'] ?? null)
                ? (string) $included['lab_id']
                : $sourceLabId,
            'add_id' => $included['add_id'] ?? null,
            'lo_hr' => (float) ($included['lo_hr'] ?? $avg),
            'avg_hr' => $avg,
            'hi_hr' => (float) ($included['hi_hr'] ?? $avg),
            'book_lo_hr' => isset($included['book_lo_hr']) ? (float) $included['book_lo_hr'] : null,
            'book_avg_hr' => isset($included['book_avg_hr']) ? (float) $included['book_avg_hr'] : null,
            'book_hi_hr' => isset($included['book_hi_hr']) ? (float) $included['book_hi_hr'] : null,
            'source_lab_id' => $sourceLabId,
        ];
    }

    /**
     * @param  array<string, mixed>  $included
     */
    private function includedLineKey(array $included): ?string
    {
        if (filled($included['job_id_code'] ?? null)) {
            return 'job:'.strtoupper(trim((string) $included['job_id_code']));
        }

        if (filled($included['add_id'] ?? null)) {
            return 'add:'.trim((string) $included['add_id']);
        }

        $description = strtoupper(trim((string) ($included['description'] ?? '')));

        return $description !== '' ? 'desc:'.$description : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function jobCode(array $row): ?string
    {
        $labId = trim((string) ($row['lab_id'] ?? ''));

        if (strlen($labId) < 4) {
            return null;
        }

        return strtoupper(substr($labId, 0, 4));
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $incumbent
     */
    private function shouldPreferIncluded(
        array $candidate,
        int $candidateParentScore,
        array $incumbent,
        int $incumbentParentScore,
    ): bool {
        $candidateRank = $this->includedLaborRank($candidate);
        $incumbentRank = $this->includedLaborRank($incumbent);

        if ($candidateRank !== $incumbentRank) {
            return $candidateRank > $incumbentRank;
        }

        return $candidateParentScore > $incumbentParentScore;
    }

    /**
     * @param  array<string, mixed>  $included
     */
    private function includedLaborRank(array $included): int
    {
        $hi = (int) round(((float) ($included['hi_hr'] ?? 0)) * 100);
        $avg = (int) round(((float) ($included['avg_hr'] ?? 0)) * 100);

        return ($hi * 1000) + $avg;
    }

    /**
     * @param  array<string, mixed>  $recommendedJob
     */
    private function titleFor(array $recommendedJob, string $term): string
    {
        $jobDesc = trim((string) ($recommendedJob['job_desc'] ?? ''));

        if ($jobDesc !== '') {
            return $jobDesc;
        }

        return trim($term);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function summaryFor(array $lines): string
    {
        return implode(' · ', array_map(
            fn (array $line): string => sprintf(
                '%s (%s hr)',
                $line['description'],
                number_format((float) $line['avg_hr'], 2),
            ),
            $lines,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $familyRows
     */
    private function rationaleFor(
        array $recommendedJob,
        array $familyRows,
        RteLaborVehicleEngineProfile $engineProfile,
        int $includedCount,
    ): string {
        return '';
    }
}
