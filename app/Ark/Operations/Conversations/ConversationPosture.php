<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Durable communication work on Conversation: whose turn, owner, follow-up, resolved.
 *
 * Sending a message does not move the conversation to Waiting. Follow-up and Resolve do.
 */
final class ConversationPosture
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

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
        return DB::transaction(function () use ($conversation, $actor): Conversation {
            $previous = $this->postureOf($conversation);

            $conversation->update([
                'status' => ConversationStatus::Resolved,
                'waiting_on' => ConversationWaitingOn::Customer,
                'owned_by_user_id' => $conversation->owned_by_user_id ?? $actor->id,
                'resolved_at' => now(),
                'follow_up_due_at' => null,
                'posture_changed_at' => now(),
            ]);

            $this->events->record(OperationalEventName::ConversationResolved, $conversation, actor: $actor, payload: [
                'source' => 'staff',
                'previous' => $previous,
            ]);

            return $conversation->refresh();
        });
    }

    /**
     * A resolution no staff member made, such as a backlog cleanup. It records the reason, leaves the owner as it
     * is, and changes nothing when the conversation is no longer open, so running it again is harmless.
     *
     * @param  array<string, mixed>  $context  What the reason rests on, such as the repair orders that closed.
     */
    public function resolveAutomatically(Conversation $conversation, string $reason, array $context = []): Conversation
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('An automatic resolution needs a reason.');
        }

        return DB::transaction(function () use ($conversation, $reason, $context): Conversation {
            $current = Conversation::query()->lockForUpdate()->findOrFail($conversation->getKey());

            if ($current->status !== ConversationStatus::Open) {
                return $current;
            }

            $previous = $this->postureOf($current);

            $current->update([
                'status' => ConversationStatus::Resolved,
                'waiting_on' => ConversationWaitingOn::Customer,
                'resolved_at' => now(),
                'follow_up_due_at' => null,
                'posture_changed_at' => now(),
            ]);

            $this->events->record(OperationalEventName::ConversationResolved, $current, payload: array_filter([
                'source' => 'system',
                'reason' => $reason,
                'context' => $context,
                'previous' => $previous,
            ], fn (mixed $value): bool => $value !== []));

            return $current->refresh();
        });
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

    /**
     * @return array{status: string|null, waiting_on: string|null, owned_by_user_id: int|null, follow_up_due_at: string|null, posture_changed_at: string|null}
     */
    private function postureOf(Conversation $conversation): array
    {
        return [
            'status' => $conversation->status?->value,
            'waiting_on' => $conversation->waiting_on?->value,
            'owned_by_user_id' => $conversation->owned_by_user_id,
            'follow_up_due_at' => $conversation->follow_up_due_at?->toIso8601String(),
            'posture_changed_at' => $conversation->posture_changed_at?->toIso8601String(),
        ];
    }
}
