<?php

namespace App\Ark\Operations\RepairOrders;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RepairOrderWorksheetPresence
{
    public const LEASE_MINUTES = 5;

    public function touch(
        RepairOrder $repairOrder,
        User $user,
        string $sessionToken,
        string $surface,
    ): RepairOrderWorksheetSession {
        $now = now();

        return RepairOrderWorksheetSession::query()->updateOrCreate(
            [
                'repair_order_id' => $repairOrder->id,
                'session_token' => $sessionToken,
            ],
            [
                'user_id' => $user->id,
                'surface' => $surface,
                'last_seen_at' => $now,
                'expires_at' => $now->copy()->addMinutes(self::LEASE_MINUTES),
            ],
        );
    }

    public function release(RepairOrder $repairOrder, string $sessionToken): void
    {
        RepairOrderWorksheetSession::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('session_token', $sessionToken)
            ->delete();
    }

    /**
     * @return Collection<int, RepairOrderWorksheetSession>
     */
    public function activeSessions(RepairOrder $repairOrder): Collection
    {
        $this->pruneExpired($repairOrder);

        return RepairOrderWorksheetSession::query()
            ->with('user:id,name')
            ->where('repair_order_id', $repairOrder->id)
            ->where('expires_at', '>', now())
            ->orderByDesc('last_seen_at')
            ->get();
    }

    public function pruneExpired(?RepairOrder $repairOrder = null): int
    {
        $query = RepairOrderWorksheetSession::query()
            ->where('expires_at', '<=', now());

        if ($repairOrder !== null) {
            $query->where('repair_order_id', $repairOrder->id);
        }

        return $query->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentUsers(RepairOrder $repairOrder, User $viewer, string $currentSessionToken): array
    {
        return $this->activeSessions($repairOrder)
            ->map(function (RepairOrderWorksheetSession $session) use ($viewer, $currentSessionToken): array {
                $isSelf = $session->user_id === $viewer->id;

                return [
                    'user_id' => $session->user_id,
                    'name' => $session->user?->name ?? 'Staff',
                    'surface' => $session->surface,
                    'is_self' => $isSelf,
                    'is_current_tab' => $session->session_token === $currentSessionToken,
                    'last_seen_at' => $session->last_seen_at?->toIso8601String(),
                    'expires_at' => $session->expires_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    public function presenceMessage(RepairOrder $repairOrder, User $viewer, string $currentSessionToken): ?string
    {
        $sessions = $this->activeSessions($repairOrder);

        if ($sessions->isEmpty()) {
            return null;
        }

        $otherUsers = $sessions
            ->filter(fn (RepairOrderWorksheetSession $session): bool => $session->user_id !== $viewer->id)
            ->unique('user_id')
            ->values();

        $selfSessions = $sessions->filter(
            fn (RepairOrderWorksheetSession $session): bool => $session->user_id === $viewer->id
                && $session->session_token !== $currentSessionToken,
        );

        if ($otherUsers->isNotEmpty()) {
            $names = $otherUsers
                ->map(fn (RepairOrderWorksheetSession $session): string => $session->user?->name ?? 'Staff')
                ->implode(', ');

            return $otherUsers->count() === 1
                ? "{$names} is also on this estimate."
                : "{$names} are also on this estimate.";
        }

        if ($selfSessions->isNotEmpty()) {
            return 'You have this estimate open in another tab.';
        }

        return null;
    }

    public function formatChangedAt(?Carbon $changedAt): ?string
    {
        return $changedAt?->timezone(\App\Ark\Operations\Settings\ShopDisplayTimezone::resolve())->format('g:i A');
    }
}
