<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class UnreadInboundMessageQueue
{
    public function __construct(
        private readonly InboundMessageQueueChannels $channels,
    ) {}

    /**
     * Latest unread inbound message per conversation for the viewer.
     *
     * @return EloquentCollection<int, ConversationMessage>
     */
    public function latestUnreadPerConversation(
        User $viewer,
        ?OperationalCommunicationChannel $channel = null,
    ): EloquentCollection {
        $channels = $channel !== null
            ? [$channel]
            : $this->channels->enabled();

        return new EloquentCollection(
            collect($channels)
                ->flatMap(fn (OperationalCommunicationChannel $queueChannel): EloquentCollection => $this->latestUnreadForChannel($viewer, $queueChannel))
                ->sortByDesc(fn (ConversationMessage $message): int => $message->occurred_at?->timestamp ?? 0)
                ->values()
                ->all(),
        );
    }

    /**
     * @return EloquentCollection<int, ConversationMessage>
     */
    private function latestUnreadForChannel(User $viewer, OperationalCommunicationChannel $channel): EloquentCollection
    {
        $contactSurface = $channel->inboundQueueContactSurface();

        if ($contactSurface === null) {
            return new EloquentCollection;
        }

        $windowStart = now()->subHours(CommunicationsQueueWindow::HOURS);

        $messages = ConversationMessage::query()
            ->with(['conversation', 'attachments', 'participant.customer'])
            ->where('direction', OperationalCommunicationDirection::Inbound)
            ->where('channel', $channel)
            ->where('occurred_at', '>=', $windowStart)
            ->whereHas('conversation', fn ($query) => $query
                ->where('contact_surface', $contactSurface)
                // Resolved is a shop-level statement that the thread is done —
                // it must clear the unread row for every advisor, not one viewer.
                ->where('status', ConversationStatus::Open->value))
            ->where(function ($query) use ($viewer): void {
                $query->whereDoesntHave('conversation.reads', fn ($readQuery) => $readQuery->where('user_id', $viewer->id))
                    ->orWhereHas('conversation.reads', function ($readQuery) use ($viewer): void {
                        $readQuery
                            ->where('user_id', $viewer->id)
                            ->whereColumn('conversation_reads.read_through_at', '<', 'conversation_messages.occurred_at');
                    });
            })
            ->orderByDesc('occurred_at')
            ->limit(100)
            ->get();

        return $messages
            ->groupBy('conversation_id')
            ->map(fn (EloquentCollection $group): ConversationMessage => $group->first())
            ->sortByDesc(fn (ConversationMessage $message): int => $message->occurred_at?->timestamp ?? 0)
            ->values();
    }
}
