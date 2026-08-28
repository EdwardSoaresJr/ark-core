<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Shop-earned estimate completeness: jobs that usually include companions.
 * Disposable — rebuild from catalog rows (seed + observed tickets).
 */
final class EstimateCompanionCompletenessProjection
{
    /**
     * @return array{
     *     is_timing_job: bool,
     *     needs_attention: bool,
     *     missing: list<string>,
     *     headline: ?string,
     *     advisor_detail: ?string,
     *     send_blocked_message: ?string,
     * }
     */
    public function for(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['lines', 'concerns']);
        $haystack = EstimateCompanionTokens::haystack($repairOrder);
        $missing = [];
        $matchedJob = false;

        foreach (EstimateCompanionPattern::query()->orderBy('id')->get() as $pattern) {
            if (! $pattern->matchesJob($haystack)) {
                continue;
            }

            $matchedJob = true;

            if (! $pattern->shouldSurface()) {
                continue;
            }

            if ($pattern->companionPresentOn($repairOrder)) {
                continue;
            }

            $label = $pattern->companion_label;
            if (! in_array($label, $missing, true)) {
                $missing[] = $label;
            }
        }

        if (! $matchedJob) {
            return $this->empty();
        }

        if ($missing === []) {
            return [
                'is_timing_job' => true,
                'needs_attention' => false,
                'missing' => [],
                'headline' => null,
                'advisor_detail' => null,
                'send_blocked_message' => null,
            ];
        }

        $labels = implode(' and ', $missing);
        $headline = 'This job is missing '.$labels;
        $detail = 'The shop usually includes '.$labels.' on this kind of job. Add them before the customer sees the estimate, or continue if they are already covered.';

        return [
            'is_timing_job' => true,
            'needs_attention' => true,
            'missing' => $missing,
            'headline' => $headline,
            'advisor_detail' => $detail,
            'send_blocked_message' => $headline.'. Add them, or continue anyway if they are already covered off the ticket.',
        ];
    }

    /**
     * @return array{
     *     is_timing_job: bool,
     *     needs_attention: bool,
     *     missing: list<string>,
     *     headline: ?string,
     *     advisor_detail: ?string,
     *     send_blocked_message: ?string,
     * }
     */
    private function empty(): array
    {
        return [
            'is_timing_job' => false,
            'needs_attention' => false,
            'missing' => [],
            'headline' => null,
            'advisor_detail' => null,
            'send_blocked_message' => null,
        ];
    }
}
