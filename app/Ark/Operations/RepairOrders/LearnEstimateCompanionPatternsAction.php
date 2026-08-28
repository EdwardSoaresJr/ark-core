<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Incremental shop learning: labor on a ticket plus parts that rode along become companions.
 */
final class LearnEstimateCompanionPatternsAction
{
    public function ingest(RepairOrder $repairOrder): void
    {
        $repairOrder->loadMissing(['lines', 'concerns']);

        $labors = $repairOrder->lines->filter(
            fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Labor
                || $line->type === RepairOrderLineType::Package,
        );
        $parts = $repairOrder->lines->filter(
            fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Part
                || $line->type === RepairOrderLineType::Fee,
        );

        if ($labors->isEmpty() || $parts->isEmpty()) {
            return;
        }

        foreach ($labors as $labor) {
            $jobTokens = EstimateCompanionTokens::from(EstimateCompanionTokens::lineText($labor));
            if (count($jobTokens) < 2) {
                continue;
            }

            $jobKey = EstimateCompanionTokens::key($jobTokens);
            $jobNeedle = mb_strtolower(trim((string) $labor->description));
            $laborHaystack = EstimateCompanionTokens::lineText($labor);

            foreach ($parts as $part) {
                $text = EstimateCompanionTokens::lineText($part);

                if ($this->skipCompanionText($text)) {
                    continue;
                }

                $existing = EstimateCompanionPattern::query()->get()
                    ->first(fn (EstimateCompanionPattern $pattern): bool => $pattern->matchesJob($laborHaystack)
                        && $pattern->companionMatchesText($text));

                if ($existing !== null) {
                    $needles = $existing->job_needles ?? [];
                    if ($jobNeedle !== '' && ! in_array($jobNeedle, $needles, true)) {
                        $needles[] = $jobNeedle;
                    }
                    $companionNeedles = $existing->companion_needles ?? [];
                    if ($text !== '' && ! in_array($text, $companionNeedles, true)) {
                        $companionNeedles[] = $text;
                    }
                    $existing->forceFill([
                        'job_needles' => array_values($needles),
                        'companion_needles' => array_slice(array_values($companionNeedles), 0, 12),
                        'support_count' => (int) $existing->support_count + 1,
                    ])->save();

                    continue;
                }

                $companionTokens = EstimateCompanionTokens::from($text);
                if ($companionTokens === []) {
                    continue;
                }

                $companionKey = EstimateCompanionTokens::key($companionTokens);
                if ($companionKey === $jobKey) {
                    continue;
                }

                $row = EstimateCompanionPattern::query()->firstOrNew([
                    'job_key' => $jobKey,
                    'companion_key' => $companionKey,
                ]);

                $needles = $row->job_needles ?? [];
                if ($jobNeedle !== '' && ! in_array($jobNeedle, $needles, true)) {
                    $needles[] = $jobNeedle;
                }

                $companionNeedles = $row->companion_needles ?? [];
                if ($text !== '' && ! in_array($text, $companionNeedles, true)) {
                    $companionNeedles[] = $text;
                }

                $row->job_needles = array_values($needles);
                $row->companion_needles = array_slice(array_values($companionNeedles), 0, 12);
                $row->companion_label = $row->companion_label ?: ($companionTokens[0] ?? 'item');
                $row->source = $row->exists && $row->source === 'seed' ? 'seed' : 'observed';
                $row->support_count = (int) $row->support_count + 1;
                $row->save();
            }
        }
    }

    public function recordExceptions(RepairOrder $repairOrder): void
    {
        $projection = (new EstimateCompanionCompletenessProjection)->for($repairOrder);

        if (! ($projection['needs_attention'] ?? false)) {
            return;
        }

        $repairOrder->loadMissing(['lines', 'concerns']);
        $haystack = EstimateCompanionTokens::haystack($repairOrder);

        foreach (EstimateCompanionPattern::query()->get() as $pattern) {
            if (! $pattern->matchesJob($haystack) || $pattern->companionPresentOn($repairOrder)) {
                continue;
            }

            $pattern->forceFill([
                'exception_count' => (int) $pattern->exception_count + 1,
            ])->save();
        }
    }

    private function skipCompanionText(string $text): bool
    {
        if ($text === '') {
            return true;
        }

        return (bool) preg_match('/\b(leak|stain)\b/u', $text)
            && ! preg_match('/\b(change|flush|engine oil|motor oil|antifreeze)\b/u', $text);
    }
}
