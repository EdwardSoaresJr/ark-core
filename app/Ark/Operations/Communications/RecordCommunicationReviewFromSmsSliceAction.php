<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\CallSessionAnalysisProjection;

final class RecordCommunicationReviewFromSmsSliceAction
{
    public function execute(ConversationSmsIntelligenceSlice $slice): ?CommunicationReview
    {
        $analysis = is_array($slice->analysis_json) ? $slice->analysis_json : [];

        if ($analysis === []) {
            return null;
        }

        $normalized = CallSessionAnalysisProjection::fromDecoded($analysis);
        $composite = CommunicationReviewScore::compositeFromAnalysis($normalized);
        $opportunityWeight = CommunicationReviewScore::coachingOpportunityWeight($normalized);

        if ($composite === null && $opportunityWeight === 0) {
            return null;
        }

        $slice->loadMissing('conversation');

        return CommunicationReview::query()->updateOrCreate(
            ['conversation_sms_intelligence_slice_id' => $slice->id],
            [
                'call_session_id' => null,
                'advisor_user_id' => $slice->conversation?->owned_by_user_id,
                'composite_score' => $composite,
                'coaching_opportunity_weight' => $opportunityWeight,
                'strengths' => $normalized['coaching_strengths'],
                'opportunities' => $normalized['coaching_improvements'],
                'dimension_scores' => CommunicationReviewScore::dimensionScoresFromAnalysis($normalized),
                'reviewed_at' => $slice->analyzed_at ?? now(),
                'source' => CommunicationReviewSource::AiAnalysis,
            ],
        );
    }
}
