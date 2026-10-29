<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Operations\Telephony\CallSessionAnalyzer;

/**
 * Stock shops keep SMS history and skip thread summaries.
 * Hosted summaries are bound over this class when Dragon is entitled.
 */
class ConversationSmsIntelligenceAnalyzer
{
    public static function enabled(): bool
    {
        return CallSessionAnalyzer::enabled();
    }

    public function queueIfEligible(ConversationSmsIntelligenceSlice $slice): void {}

    public function analyze(ConversationSmsIntelligenceSlice $slice): void
    {
        $slice->forceFill([
            'analysis_status' => CallSessionAnalysisStatus::Skipped,
            'analysis_error' => 'Call and SMS summaries are not part of this shop installation.',
        ])->saveQuietly();
    }
}
