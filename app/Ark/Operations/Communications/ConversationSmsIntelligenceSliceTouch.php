<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;

final class ConversationSmsIntelligenceSliceTouch
{
    public const MIN_MESSAGES = 1;

    public function __construct(
        private readonly ConversationSmsIntelligenceTranscriptBuilder $transcriptBuilder,
        private readonly ConversationSmsIntelligenceAnalyzer $analyzer,
    ) {}

    public function touchFromMessage(ConversationMessage $message): void
    {
        if ($message->channel !== OperationalCommunicationChannel::Sms) {
            return;
        }

        $message->loadMissing('conversation');

        $timezone = ShopDisplayTimezone::resolve();
        $activityDate = ($message->occurred_at ?? now())
            ->timezone($timezone)
            ->toDateString();

        $slice = ConversationSmsIntelligenceSlice::query()
            ->where('conversation_id', $message->conversation_id)
            ->whereDate('activity_date', $activityDate)
            ->first();

        if ($slice === null) {
            $slice = ConversationSmsIntelligenceSlice::query()->create([
                'conversation_id' => $message->conversation_id,
                'activity_date' => $activityDate,
                'last_message_at' => $message->occurred_at ?? now(),
            ]);
        }

        $built = $this->transcriptBuilder->forConversationDay(
            (int) $message->conversation_id,
            $activityDate,
        );

        $previousMessageCount = (int) $slice->message_count;
        $previousInboundCount = (int) $slice->inbound_count;
        $previousOutboundCount = (int) $slice->outbound_count;

        $slice->forceFill([
            'last_message_at' => $built['last_message_at'] ?? $slice->last_message_at,
            'message_count' => $built['message_count'],
            'inbound_count' => $built['inbound_count'],
            'outbound_count' => $built['outbound_count'],
            'transcript' => $built['transcript'] !== '' ? $built['transcript'] : null,
        ]);

        $countsChanged = $slice->message_count !== $previousMessageCount
            || $slice->inbound_count !== $previousInboundCount
            || $slice->outbound_count !== $previousOutboundCount;

        if ($slice->analysis_status === CallSessionAnalysisStatus::Ready
            && ($countsChanged || $slice->needsSuggestedReplyRefresh())) {
            $slice->analysis_status = CallSessionAnalysisStatus::Pending;
        }

        $slice->saveQuietly();

        if ($this->isEligibleForAnalysis($slice->fresh())) {
            $this->analyzer->queueIfEligible($slice->fresh());
        }
    }

    public static function isEligible(ConversationSmsIntelligenceSlice $slice): bool
    {
        if ($slice->message_count < self::MIN_MESSAGES) {
            return false;
        }

        return is_string($slice->transcript) && trim($slice->transcript) !== '';
    }

    public function isEligibleForAnalysis(ConversationSmsIntelligenceSlice $slice): bool
    {
        return ConversationSmsIntelligenceSliceTouch::isEligible($slice);
    }
}
