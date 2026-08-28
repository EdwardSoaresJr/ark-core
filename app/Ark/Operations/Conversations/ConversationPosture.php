<?php

namespace App\Ark\Operations\Conversations;

use App\Models\User;

/**
 * Thread posture on Conversation — ownership / open-resolved.
 *
 * Turn (waiting_on) is owned by {@see SyncConversationTurnAction} via
 * {@see ConversationTurnPrecedence} — not by the last transport write alone.
 */
final class ConversationPosture
{
    public function __construct(
        private readonly SyncConversationTurnAction $syncTurn,
    ) {}

    public function recordInbound(Conversation $conversation): Conversation
    {
        $wasResolved = $conversation->status === ConversationStatus::Resolved;

        $attributes = [
            'status' => ConversationStatus::Open,
            'posture_changed_at' => now(),
            'resolved_at' => null,
        ];

        if ($wasResolved) {
            $attributes['reopen_count'] = $conversation->reopen_count + 1;
        }

        $conversation->update($attributes);

        return $this->syncTurn->execute($conversation->fresh());
    }

    public function recordOutbound(Conversation $conversation, User $actor): Conversation
    {
        $conversation->update([
            'status' => ConversationStatus::Open,
            'owned_by_user_id' => $actor->id,
            'posture_changed_at' => now(),
            'resolved_at' => null,
        ]);

        return $this->syncTurn->execute($conversation->fresh());
    }

    public function resolve(Conversation $conversation, User $actor): Conversation
    {
        $conversation->update([
            'status' => ConversationStatus::Resolved,
            'owned_by_user_id' => $conversation->owned_by_user_id ?? $actor->id,
            'resolved_at' => now(),
            'posture_changed_at' => now(),
        ]);

        return $this->syncTurn->execute($conversation->fresh());
    }

    public function reopen(Conversation $conversation, User $actor): Conversation
    {
        $conversation->update([
            'status' => ConversationStatus::Open,
            'owned_by_user_id' => $conversation->owned_by_user_id ?? $actor->id,
            'resolved_at' => null,
            'posture_changed_at' => now(),
            'reopen_count' => $conversation->reopen_count + 1,
        ]);

        return $this->syncTurn->execute($conversation->fresh());
    }

    public function assign(Conversation $conversation, ?User $owner): Conversation
    {
        $conversation->update([
            'owned_by_user_id' => $owner?->id,
            'posture_changed_at' => now(),
        ]);

        return $conversation->refresh();
    }
}
