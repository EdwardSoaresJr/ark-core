<?php

namespace App\Ark\Operations\LaborGuides\Rte;

/**
 * Picks the likely RTE labor row for this vehicle and search term.
 */
final class RteLaborJobRecommender
{
    /**
     * @param  list<array<string, mixed>>  $jobs
     * @return array{
     *     recommended_job: array<string, mixed>|null,
     *     jobs: list<array<string, mixed>>
     * }
     */
    public function partition(array $jobs, ?string $term, RteLaborVehicleEngineProfile $engineProfile): array
    {
        if ($jobs === []) {
            return [
                'recommended_job' => null,
                'jobs' => [],
            ];
        }

        if (! filled($term)) {
            return [
                'recommended_job' => null,
                'jobs' => $jobs,
            ];
        }

        $words = array_values(array_filter(preg_split('/\s+/', strtoupper(trim($term))) ?: []));

        $ranked = $jobs;
        usort($ranked, fn (array $left, array $right): int => $this->score($right, $words, $engineProfile)
            <=> $this->score($left, $words, $engineProfile));

        $recommended = $ranked[0];
        $recommendedLabId = (string) ($recommended['lab_id'] ?? '');

        $recommended['is_recommended'] = true;

        $remaining = array_values(array_filter(
            $jobs,
            fn (array $job): bool => (string) ($job['lab_id'] ?? '') !== $recommendedLabId,
        ));

        return [
            'recommended_job' => $recommended,
            'jobs' => $remaining,
        ];
    }

    /**
     * @param  list<string>  $words
     */
    private function score(array $row, array $words, RteLaborVehicleEngineProfile $engineProfile): int
    {
        $score = (int) ($row['match_rank'] ?? 0) * 30;
        $score += $engineProfile->engineMatchScore($row);
        $score += $this->searchRelevanceScore($row, $words);

        $jobCode = strlen((string) ($row['lab_id'] ?? '')) >= 4
            ? substr((string) $row['lab_id'], 0, 4)
            : '';

        if ($jobCode !== '' && $this->termLooksJobSpecific($words, $row)) {
            $score += 25;
        }

        return $score;
    }

    /**
     * @param  list<string>  $words
     */
    private function termLooksJobSpecific(array $words, array $row): bool
    {
        $description = strtoupper((string) ($row['job_desc'] ?? ''));

        foreach ($words as $word) {
            if (str_contains($description, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $words
     */
    private function searchRelevanceScore(array $row, array $words): int
    {
        if ($words === []) {
            return 0;
        }

        $description = strtoupper((string) ($row['job_desc'] ?? ''));
        $score = 0;
        $matchedWords = 0;

        foreach ($words as $word) {
            $wordMatched = str_contains($description, $word);

            if ($wordMatched) {
                $score += 20;
                $matchedWords++;
            }
        }

        if ($matchedWords >= count($words)) {
            $score += 40;
        }

        if (count($words) === 1 && str_contains($description, $words[0])) {
            $score -= substr_count($description, ' ');
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function scoreRow(array $row, ?string $term, RteLaborVehicleEngineProfile $engineProfile): int
    {
        $words = filled($term)
            ? array_values(array_filter(preg_split('/\s+/', strtoupper(trim($term))) ?: []))
            : [];

        return $this->score($row, $words, $engineProfile);
    }
}
