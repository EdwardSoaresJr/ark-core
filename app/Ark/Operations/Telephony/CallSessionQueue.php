<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Conversations\SyncConversationTurnAction;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CallSessionQueue
{
    public const WINDOW_HOURS = 8;

    /**
     * @return EloquentCollection<int, CallSession>
     */
    public function waitingSessions(): EloquentCollection
    {
        $sessions = CallSession::query()
            ->with(['customer', 'owner'])
            ->excludingFeatureCodeDials()
            ->whereNull('worked_at')
            ->where('started_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->whereIn('status', [
                CallSessionStatus::Ringing,
                CallSessionStatus::Answered,
                CallSessionStatus::Missed,
                CallSessionStatus::Completed,
                CallSessionStatus::Failed,
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $this->dedupeByCaller($sessions)
            ->sortBy(fn (CallSession $session): int => $this->withinStatusSortKey($session))
            ->sortBy(fn (CallSession $session): int => $this->statusSortOrder($session->status))
            ->values();
    }

    public function waitingCount(): int
    {
        return $this->waitingSessions()->count();
    }

    public static function calleeKey(CallSession $session): string
    {
        return $session->normalized_from ?? (string) $session->from_number;
    }

    public function markCallerHandled(CallSession $session): int
    {
        $calleeKey = self::calleeKey($session);

        $cleared = CallSession::query()
            ->whereNull('worked_at')
            ->where('started_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->where(function ($query) use ($calleeKey): void {
                $query->where('normalized_from', $calleeKey)
                    ->orWhere(function ($query) use ($calleeKey): void {
                        $query->whereNull('normalized_from')
                            ->where('from_number', $calleeKey);
                    });
            })
            ->update(['worked_at' => now()]);

        // Bulk update skips model events — resync conversation turn so a
        // handled call clears the shop-turn posture it created.
        app(SyncConversationTurnAction::class)->forCallSession($session->refresh());

        return $cleared;
    }

    public function markCustomerOrPhoneHandled(?int $customerId, ?string $phone): int
    {
        if ($customerId === null && ! filled($phone)) {
            return 0;
        }

        $digits = PhoneNumber::normalize($phone) ?? '';

        $cleared = CallSession::query()
            ->whereNull('worked_at')
            ->where('started_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->where(function ($query) use ($customerId, $digits): void {
                $hasConstraint = false;

                if ($customerId !== null) {
                    $query->where('customer_id', $customerId);
                    $hasConstraint = true;
                }

                if ($digits !== '') {
                    $method = $hasConstraint ? 'orWhere' : 'where';

                    $query->{$method}(function ($phoneQuery) use ($digits): void {
                        $phoneQuery->where('normalized_from', $digits)
                            ->orWhere(function ($fallback) use ($digits): void {
                                $fallback->whereNull('normalized_from')
                                    ->where('from_number', 'like', '%'.$digits);
                            });
                    });
                }
            })
            ->update(['worked_at' => now()]);

        if ($cleared > 0 && $digits !== '') {
            $conversation = app(ConversationResolver::class)
                ->findForContactKey(ConversationContactSurface::Phone, $digits);

            if ($conversation !== null) {
                app(SyncConversationTurnAction::class)->execute($conversation);
            }
        }

        return $cleared;
    }

    /**
     * Close out live statuses that never received a terminal status callback.
     *
     * Must run from poll endpoints, scheduled jobs, or write actions — not page-render GET paths.
     */
    public function reconcileStaleLiveSessions(): void
    {
        CallSession::query()
            ->whereNotNull('worked_at')
            ->whereIn('status', [CallSessionStatus::Ringing, CallSessionStatus::Answered])
            ->update([
                'status' => CallSessionStatus::Completed,
                'ended_at' => now(),
            ]);

        CallSession::query()
            ->where('status', CallSessionStatus::Ringing)
            ->where('started_at', '<', now()->subMinutes(5))
            ->update([
                'status' => CallSessionStatus::Missed,
                'ended_at' => now(),
            ]);

        CallSession::query()
            ->where('status', CallSessionStatus::Answered)
            ->whereNull('ended_at')
            ->where(function ($query): void {
                $query->where('answered_at', '<', now()->subMinutes(5))
                    ->orWhere(function ($query): void {
                        $query->whereNull('answered_at')
                            ->where('started_at', '<', now()->subMinutes(5));
                    });
            })
            ->update([
                'status' => CallSessionStatus::Completed,
                'ended_at' => now(),
            ]);
    }

    /**
     * @param  EloquentCollection<int, CallSession>  $sessions
     * @return EloquentCollection<int, CallSession>
     */
    private function dedupeByCaller(EloquentCollection $sessions): EloquentCollection
    {
        return $sessions
            ->groupBy(fn (CallSession $session): string => self::calleeKey($session))
            ->map(fn (EloquentCollection $group): CallSession => $group->sortByDesc('id')->first())
            ->values();
    }

    private function withinStatusSortKey(CallSession $session): int
    {
        $timestamp = $session->started_at?->timestamp ?? 0;

        return match ($session->status) {
            CallSessionStatus::Missed, CallSessionStatus::Failed => $timestamp,
            default => -$timestamp,
        };
    }

    private function statusSortOrder(CallSessionStatus $status): int
    {
        return match ($status) {
            CallSessionStatus::Ringing => 0,
            CallSessionStatus::Missed => 1,
            CallSessionStatus::Failed => 1,
            CallSessionStatus::Answered => 2,
            CallSessionStatus::Completed => 3,
        };
    }
}
