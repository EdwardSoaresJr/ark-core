<?php

namespace App\Ark\Operations\Conversations;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Durable communication work on Conversation: whose turn, owner, follow-up, resolved.
 *
 * Sending a message does not move the conversation to Waiting. Follow-up and Resolve do.
 */
final class ConversationPosture
{
    public function recordInbound(Conversation $conversation): Conversation
    {
        $wasResolved = $conversation->status === ConversationStatus::Resolved;

        $attributes = [
            'status' => ConversationStatus::Open,
            'waiting_on' => ConversationWaitingOn::Shop,
            'follow_up_due_at' => null,
            'posture_changed_at' => now(),
            'resolved_at' => null,
        ];

        if ($wasResolved) {
            $attributes['reopen_count'] = $conversation->reopen_count + 1;
        }

        $conversation->update($attributes);

        return $conversation->refresh();
    }

    public function recordOutbound(Conversation $conversation, User $actor): Conversation
    {
        $conversation->update([
            'owned_by_user_id' => $actor->id,
            'posture_changed_at' => now(),
        ]);

        return $conversation->refresh();
    }

    public function waitOnCustomer(Conversation $conversation, ?User $actor = null, ?Carbon $dueAt = null): Conversation
    {
        $conversation->update([
            'status' => ConversationStatus::Open,
            'waiting_on' => ConversationWaitingOn::Customer,
            'owned_by_user_id' => $conversation->owned_by_user_id ?? $actor?->id,
            'follow_up_due_at' => $dueAt?->copy()->utc(),
            'resolved_at' => null,
            'posture_changed_at' => now(),
        ]);

        return $conversation->refresh();
    }

    public function resolve(Conversation $conversation, User $actor): Conversation
    {
        $conversation->update([
            'status' => ConversationStatus::Resolved,
            'owned_by_user_id' => $conversation->owned_by_user_id ?? $actor->id,
            'resolved_at' => now(),
            'follow_up_due_at' => null,
            'posture_changed_at' => now(),
        ]);

        return $conversation->refresh();
    }

    public function reopen(Conversation $conversation, User $actor): Conversation
    {
        $conversation->update([
            'status' => ConversationStatus::Open,
            'waiting_on' => ConversationWaitingOn::Shop,
            'owned_by_user_id' => $conversation->owned_by_user_id ?? $actor->id,
            'resolved_at' => null,
            'follow_up_due_at' => null,
            'posture_changed_at' => now(),
            'reopen_count' => $conversation->reopen_count + 1,
        ]);

        return $conversation->refresh();
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
