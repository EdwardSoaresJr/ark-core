<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use Illuminate\Support\Str;

/**
 * Advisor-facing analysis insight for Communications workspace context.
 *
 * Surfaces suggested replies when a higher-priority nudge hides the analysis nudge.
 */
final class CommunicationsAnalysisInsightProjection
{
    /**
     * @param  array<string, mixed>  $selected
     * @return array<string, mixed>|null
     */
    public function forSelection(array $selected): ?array
    {
        return match ($selected['kind'] ?? '') {
            'conversation' => $this->forConversationId((int) Str::after((string) ($selected['key'] ?? ''), 'conversation:')),
            'call' => $this->forCallSessionId((int) Str::after((string) ($selected['key'] ?? ''), 'call:')),
            default => null,
        };
    }

    public function forConversationId(int $conversationId): ?array
    {
        $slice = ConversationSmsIntelligenceSlice::query()
            ->where('conversation_id', $conversationId)
            ->where('analysis_status', CallSessionAnalysisStatus::Ready)
            ->orderByDesc('activity_date')
            ->first();

        if ($slice !== null && $slice->advisorFollowUpApplies()) {
            $insight = $this->fromAnalysisJson(
                is_array($slice->analysis_json) ? $slice->analysis_json : [],
                'SMS',
            );

            if ($insight !== null) {
                return $insight;
            }
        }

        // Calls merge into the conversation selection — an analyzed unhandled
        // call keeps its insight visible through that mapping.
        $session = $this->latestAnalyzedInboundCall($conversationId);

        return $session !== null ? $this->forCallSessionId($session->id) : null;
    }

    private function latestAnalyzedInboundCall(int $conversationId): ?CallSession
    {
        $conversation = Conversation::query()->find($conversationId);

        if ($conversation === null || $conversation->contact_surface !== ConversationContactSurface::Phone) {
            return null;
        }

        $phone = trim((string) $conversation->contact_address);

        if ($phone === '') {
            return null;
        }

        return CallSession::query()
            ->where('analysis_status', CallSessionAnalysisStatus::Ready)
            ->whereNull('worked_at')
            ->where('started_at', '>=', now()->subDays(7))
            ->where(function ($query) use ($phone): void {
                $query->where('normalized_from', $phone)
                    ->orWhere('from_number', 'like', '%'.$phone);
            })
            ->orderByDesc('started_at')
            ->first();
    }

    public function forCallSessionId(int $callSessionId): ?array
    {
        $session = CallSession::query()->find($callSessionId);

        if ($session === null || $session->analysis_status !== CallSessionAnalysisStatus::Ready) {
            return null;
        }

        return $this->fromAnalysisJson(
            is_array($session->analysis_json) ? $session->analysis_json : [],
            'Call',
        );
    }

    /**
     * @param  array<string, mixed>|null  $nudge
     */
    public function shouldShowAlongsideNudge(?array $nudge, ?array $insight): bool
    {
        if ($insight === null) {
            return false;
        }

        if ($nudge === null) {
            return true;
        }

        $nudgeKey = (string) ($nudge['key'] ?? '');

        return ! in_array($nudgeKey, [
            'conversation.sms_analysis_follow_up',
            'call.analysis_follow_up',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>|null
     */
    private function fromAnalysisJson(array $analysis, string $channel): ?array
    {
        if ($analysis === []) {
            return null;
        }

        $followUpNeeded = (bool) data_get($analysis, 'follow_up_needed', false);
        $suggestedReply = trim((string) data_get($analysis, 'suggested_reply', ''));
        $summary = trim((string) data_get($analysis, 'summary', ''));
        $followUpNotes = trim((string) data_get($analysis, 'follow_up_notes', ''));

        if (! $followUpNeeded && $suggestedReply === '' && $followUpNotes === '' && $summary === '') {
            return null;
        }

        return [
            'channel' => $channel,
            'summary' => $summary !== '' ? $summary : null,
            'follow_up_notes' => $followUpNotes !== '' ? $followUpNotes : null,
            'suggested_reply' => $suggestedReply !== '' ? $suggestedReply : null,
            'follow_up_needed' => $followUpNeeded,
            'nudge_key' => $channel === 'Call'
                ? 'call.analysis_follow_up'
                : 'conversation.sms_analysis_follow_up',
        ];
    }
}
