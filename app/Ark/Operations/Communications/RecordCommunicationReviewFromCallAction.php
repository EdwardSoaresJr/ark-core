<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisProjection;

final class RecordCommunicationReviewFromCallAction
{
    public function execute(CallSession $callSession): ?CommunicationReview
    {
        $analysis = is_array($callSession->analysis_json) ? $callSession->analysis_json : [];

        if ($analysis === []) {
            return null;
        }

        $normalized = CallSessionAnalysisProjection::fromDecoded($analysis);
        $composite = CommunicationReviewScore::compositeFromAnalysis($normalized);
        $opportunityWeight = CommunicationReviewScore::coachingOpportunityWeight($normalized);

        if ($composite === null && $opportunityWeight === 0) {
            return null;
        }

        $reviewedAt = $callSession->analyzed_at ?? now();

        return CommunicationReview::query()->updateOrCreate(
            ['call_session_id' => $callSession->id],
            [
                'advisor_user_id' => $callSession->owned_by_user_id,
                'composite_score' => $composite,
                'coaching_opportunity_weight' => $opportunityWeight,
                'strengths' => $normalized['coaching_strengths'],
                'opportunities' => $normalized['coaching_improvements'],
                'dimension_scores' => CommunicationReviewScore::dimensionScoresFromAnalysis($normalized),
                'reviewed_at' => $reviewedAt,
                'source' => CommunicationReviewSource::AiAnalysis,
            ],
        );
    }
}
