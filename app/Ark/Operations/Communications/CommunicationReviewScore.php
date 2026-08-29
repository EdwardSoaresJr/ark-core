<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\CallSessionAnalysisProjection;

/**
 * Normalizes call analysis into digest-friendly scores without winner/loser framing in storage.
 */
final class CommunicationReviewScore
{
    /**
     * @param  array<string, mixed>  $analysis
     */
    public static function compositeFromAnalysis(array $analysis): ?int
    {
        $scores = collect([
            CallSessionAnalysisProjection::scoreFromRaw(data_get($analysis, 'empathy_score')),
            CallSessionAnalysisProjection::scoreFromRaw(data_get($analysis, 'ownership_score')),
            CallSessionAnalysisProjection::scoreFromRaw(data_get($analysis, 'clarity_score')),
        ])->filter(fn (?int $score): bool => $score !== null);

        if ($scores->isEmpty()) {
            return null;
        }

        return (int) round($scores->average() * 20);
    }

    /**
     * Higher weight = stronger coaching opportunity (not "worst call").
     *
     * @param  array<string, mixed>  $analysis
     */
    public static function coachingOpportunityWeight(array $analysis): int
    {
        return CallSessionAnalysisProjection::coachingUrgencyWeight($analysis);
    }

    /**
     * Phase 2 dimension scaffold — Demo Auto Repair doctrine expands here.
     *
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    public static function dimensionScoresFromAnalysis(array $analysis): array
    {
        return [
            'empathy' => CallSessionAnalysisProjection::scoreFromRaw(data_get($analysis, 'empathy_score')),
            'ownership' => CallSessionAnalysisProjection::scoreFromRaw(data_get($analysis, 'ownership_score')),
            'clarity' => CallSessionAnalysisProjection::scoreFromRaw(data_get($analysis, 'clarity_score')),
            'appointment_captured' => data_get($analysis, 'appointment_captured'),
            'sentiment' => data_get($analysis, 'sentiment'),
            'coaching_priority' => data_get($analysis, 'coaching_priority'),
        ];
    }
}
