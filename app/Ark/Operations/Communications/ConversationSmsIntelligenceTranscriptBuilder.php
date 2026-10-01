<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ConversationSmsIntelligenceTranscriptBuilder
{
    /**
     * @return array{
     *     transcript: string,
     *     message_count: int,
     *     inbound_count: int,
     *     outbound_count: int,
     *     last_message_at: ?Carbon
     * }
     */
    public function forConversationDay(int $conversationId, string $activityDate): array
    {
        $timezone = ShopDisplayTimezone::resolve();
        $start = Carbon::parse($activityDate, $timezone)->startOfDay()->utc();
        $end = Carbon::parse($activityDate, $timezone)->endOfDay()->utc();

        /** @var Collection<int, ConversationMessage> $messages */
        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('channel', OperationalCommunicationChannel::Sms)
            ->whereBetween('occurred_at', [$start, $end])
            ->with(['participant.user', 'participant.customer'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $lines = [];
        $inbound = 0;
        $outbound = 0;

        foreach ($messages as $message) {
            if ($message->direction === OperationalCommunicationDirection::Inbound) {
                $inbound++;
            } else {
                $outbound++;
            }

            $body = trim($message->body);

            if ($body === '' || $body === '(attachment)') {
                continue;
            }

            $speaker = $message->direction === OperationalCommunicationDirection::Inbound
                ? ($message->participant?->displayLabel() ?? 'Customer')
                : ($message->participant?->displayLabel() ?? 'Advisor');

            $lines[] = "{$speaker}: {$body}";
        }

        $lastMessage = $messages->last();

        return [
            'transcript' => implode("\n", $lines),
            'message_count' => $messages->count(),
            'inbound_count' => $inbound,
            'outbound_count' => $outbound,
            'last_message_at' => $lastMessage?->occurred_at,
        ];
    }
}
