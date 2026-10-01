<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Models\User;
use Illuminate\Support\Carbon;

class ConversationReadTracker
{
    public function markRead(Conversation $conversation, User $user, ?Carbon $through = null): ConversationRead
    {
        $through ??= now();

        return ConversationRead::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            ['read_through_at' => $through],
        );
    }

    public function unreadInboundCount(Conversation $conversation, User $user): int
    {
        $readThrough = ConversationRead::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->value('read_through_at');

        $query = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', OperationalCommunicationDirection::Inbound);

        if ($readThrough instanceof Carbon) {
            $query->where('occurred_at', '>', $readThrough);
        }

        return $query->count();
    }
}
