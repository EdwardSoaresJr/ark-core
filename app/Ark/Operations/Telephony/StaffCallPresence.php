<?php

namespace App\Ark\Operations\Telephony;

use App\Models\User;

class StaffCallPresence
{
    public function isPresent(?int $userId, int $timeoutMinutes = 30): bool
    {
        if ($userId === null) {
            return false;
        }

        $user = User::query()->find($userId);

        if ($user === null || $user->last_seen_at === null) {
            return false;
        }

        return $user->last_seen_at->greaterThanOrEqualTo(now()->subMinutes($timeoutMinutes));
    }

    public function markPresent(User $user): void
    {
        $now = now();

        if ($user->last_seen_at !== null && $user->last_seen_at->greaterThan($now->copy()->subMinute())) {
            return;
        }

        $user->forceFill(['last_seen_at' => $now])->saveQuietly();
    }
}
