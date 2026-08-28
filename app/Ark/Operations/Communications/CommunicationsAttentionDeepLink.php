<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;

/**
 * Owner/coaching surfaces → advisor Communications Needs You workspace.
 */
final class CommunicationsAttentionDeepLink
{
    public static function forCallSession(CallSession $callSession): ?string
    {
        if ($callSession->analysis_status !== CallSessionAnalysisStatus::Ready) {
            return null;
        }

        if (! (bool) data_get($callSession->analysis_json, 'follow_up_needed', false)) {
            return null;
        }

        return CommunicationsNeedsYou::url(['call' => $callSession->id]);
    }

    public static function forSmsSlice(ConversationSmsIntelligenceSlice $slice): ?string
    {
        if ($slice->analysis_status !== CallSessionAnalysisStatus::Ready) {
            return null;
        }

        if (! $slice->analysisFollowUpNeeded()) {
            return null;
        }

        $conversationId = (int) ($slice->conversation_id ?? 0);

        if ($conversationId <= 0) {
            return null;
        }

        return CommunicationsNeedsYou::url(['conversation' => $conversationId]);
    }
}
