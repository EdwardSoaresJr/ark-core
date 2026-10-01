<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Durable communication work on Core Conversation.
 * Platform owns messages. This owns whose turn it is.
 */
final class ConversationWork
{
    public function __construct(
        private readonly ConversationResolver $resolver,
        private readonly ConversationLinker $linker,
        private readonly ConversationPosture $posture,
    ) {}

    public function ensureForPhone(string $phone, ?Customer $customer = null): Conversation
    {
        $conversation = $this->resolver->forPhone($phone);

        if ($customer instanceof Customer) {
            $this->linker->link($conversation, $customer);
        }

        return $conversation;
    }

    public function markNeedsAttention(Conversation $conversation): Conversation
    {
        return $this->posture->recordInbound($conversation);
    }

    public function recordOutboundOwner(Conversation $conversation, User $actor): Conversation
    {
        return $this->posture->recordOutbound($conversation, $actor);
    }

    public function followUp(Conversation $conversation, ?User $actor = null, ?Carbon $dueAt = null): Conversation
    {
        return $this->posture->waitOnCustomer($conversation, $actor, $dueAt);
    }

    public function resolve(Conversation $conversation, User $actor): Conversation
    {
        return $this->posture->resolve($conversation, $actor);
    }

    public function assign(Conversation $conversation, ?User $owner): Conversation
    {
        return $this->posture->assign($conversation, $owner);
    }

    public function assertCurrent(Conversation $conversation, ?string $expectedPostureChangedAt): void
    {
        $current = $conversation->posture_changed_at;

        if ($current === null) {
            return;
        }

        if (! filled($expectedPostureChangedAt)) {
            throw new StaleConversationWorkException;
        }

        $expected = Carbon::parse($expectedPostureChangedAt);

        if ($current->utc()->timestamp !== $expected->utc()->timestamp) {
            throw new StaleConversationWorkException;
        }
    }

    public function lane(Conversation $conversation): string
    {
        if ($conversation->status === ConversationStatus::Resolved) {
            return 'resolved';
        }

        if ($conversation->waiting_on === ConversationWaitingOn::Customer) {
            $dueAt = $conversation->follow_up_due_at;
            if ($dueAt instanceof DateTimeInterface && Carbon::parse($dueAt)->lte(now())) {
                return 'needs';
            }

            return 'waiting';
        }

        return 'needs';
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function applyLane(Builder $query, string $lane): Builder
    {
        $open = ConversationStatus::Open->value;
        $customer = ConversationWaitingOn::Customer->value;
        $now = now();

        return match ($lane) {
            'resolved' => $query->where('status', ConversationStatus::Resolved->value),
            'waiting' => $query
                ->where('status', $open)
                ->where('waiting_on', $customer)
                ->where(function (Builder $due) use ($now): void {
                    $due->whereNull('follow_up_due_at')
                        ->orWhere('follow_up_due_at', '>', $now);
                }),
            default => $query
                ->where('status', $open)
                ->where(function (Builder $needs) use ($customer, $now): void {
                    $needs->where('waiting_on', '!=', $customer)
                        ->orWhereNull('waiting_on')
                        ->orWhere(function (Builder $overdue) use ($customer, $now): void {
                            $overdue->where('waiting_on', $customer)
                                ->whereNotNull('follow_up_due_at')
                                ->where('follow_up_due_at', '<=', $now);
                        });
                }),
        };
    }

    /**
     * @return array{all: int, needs: int, waiting: int, resolved: int}
     */
    public function counts(): array
    {
        $needs = $this->applyLane(Conversation::query(), 'needs')->count();
        $waiting = $this->applyLane(Conversation::query(), 'waiting')->count();
        $resolved = $this->applyLane(Conversation::query(), 'resolved')->count();

        return [
            'needs' => $needs,
            'waiting' => $waiting,
            'resolved' => $resolved,
            'all' => $needs + $waiting + $resolved,
        ];
    }

    public function laneLabel(Conversation $conversation): string
    {
        return match ($this->lane($conversation)) {
            'waiting' => 'Waiting',
            'resolved' => 'Resolved',
            default => 'Needs attention',
        };
    }

    public function isFollowUpOverdue(Conversation $conversation): bool
    {
        if ($conversation->waiting_on !== ConversationWaitingOn::Customer) {
            return false;
        }

        $dueAt = $conversation->follow_up_due_at;

        return $dueAt instanceof DateTimeInterface && Carbon::parse($dueAt)->lte(now());
    }

    public function badgeLabel(Conversation $conversation): string
    {
        if ($this->lane($conversation) !== 'waiting') {
            return $this->laneLabel($conversation);
        }

        $dueAt = $conversation->follow_up_due_at;
        if ($dueAt instanceof DateTimeInterface) {
            $due = Carbon::parse($dueAt);
            if ($due->isToday() || $due->isFuture()) {
                return 'Follow-up today';
            }
        }

        return 'Waiting';
    }

    public function wait(Conversation $conversation, ?User $actor = null): Conversation
    {
        return $this->followUp($conversation, $actor, null);
    }
}
