<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Incremental shop learning: labor on a ticket plus parts that rode along become companions.
 */
final class LearnEstimateCompanionPatternsAction
{
    /** Observed patterns need this many supports before they warn. */
    public const OBSERVED_SUPPORT_FLOOR = 3;

    /** @var list<string> */
    private const JUNK_PHRASES = [
        'customer provided',
        'customer parts',
        'parts provided',
        'provided by customer',
        'see advisor',
        'see notes',
        'as discussed',
        'per customer',
    ];

    /** @var list<string> */
    private const HARDWARE_ONLY = [
        'bolt', 'bolts', 'nut', 'nuts', 'screw', 'screws', 'washer', 'washers',
        'clamp', 'clamps', 'clip', 'clips', 'pin', 'pins', 'ring', 'rings',
    ];

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

        $catalog = EstimateCompanionPattern::query()->get();

        foreach ($labors as $labor) {
            $jobTokens = EstimateCompanionTokens::from(EstimateCompanionTokens::lineText($labor));
            if (count($jobTokens) < 2) {
                continue;
            }

            $jobKey = EstimateCompanionTokens::key($jobTokens);
            $jobNeedle = mb_strtolower(trim((string) $labor->description));
            $laborHaystack = EstimateCompanionTokens::lineText($labor);
            $laborConcernId = $labor->repair_order_concern_id;

            foreach ($parts as $part) {
                if (! $this->sameConcern($laborConcernId, $part->repair_order_concern_id)) {
                    continue;
                }

                $text = EstimateCompanionTokens::lineText($part);

                if ($this->skipCompanionText($text)) {
                    continue;
                }

                $existing = $catalog->first(
                    fn (EstimateCompanionPattern $pattern): bool => $pattern->matchesJob($laborHaystack)
                        && $pattern->companionMatchesText($text),
                );

                if ($existing !== null) {
                    $this->bumpExisting($existing, $jobNeedle, $text);
                    continue;
                }

                $companionTokens = EstimateCompanionTokens::from($text);
                if ($companionTokens === [] || EstimateCompanionTokens::key($companionTokens) === $jobKey) {
                    continue;
                }

                if ($this->hardwareOnlyTokens($companionTokens)) {
                    continue;
                }

                $companionKey = EstimateCompanionTokens::key($companionTokens);
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
                $row->companion_label = $row->companion_label ?: EstimateCompanionTokens::labelFor($text, $companionTokens);
                $row->source = $row->exists && $row->source === 'seed' ? 'seed' : 'observed';
                $row->support_count = (int) $row->support_count + 1;
                $row->save();
                $catalog->push($row);
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

    private function bumpExisting(EstimateCompanionPattern $existing, string $jobNeedle, string $text): void
    {
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
    }

    private function sameConcern(mixed $laborConcernId, mixed $partConcernId): bool
    {
        if ($laborConcernId === null || $partConcernId === null) {
            return $laborConcernId === $partConcernId;
        }

        return (int) $laborConcernId === (int) $partConcernId;
    }

    private function skipCompanionText(string $text): bool
    {
        if ($text === '') {
            return true;
        }

        foreach (self::JUNK_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        if (preg_match('/\b(leak|stain)\b/u', $text)
            && ! preg_match('/\b(change|flush|engine oil|motor oil|antifreeze)\b/u', $text)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function hardwareOnlyTokens(array $tokens): bool
    {
        if ($tokens === []) {
            return true;
        }

        foreach ($tokens as $token) {
            if (! in_array($token, self::HARDWARE_ONLY, true)) {
                return false;
            }
        }

        return true;
    }
}
