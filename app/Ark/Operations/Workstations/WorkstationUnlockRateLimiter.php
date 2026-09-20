<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class WorkstationUnlockRateLimiter
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function assertNotLimited(WorkstationBrowserBinding $binding, User $operator): void
    {
        $key = $this->key($binding, $operator);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'pin' => 'Too many failed PIN attempts. Try again in '.$seconds.' seconds.',
        ]);
    }

    public function recordFailure(WorkstationBrowserBinding $binding, User $operator): void
    {
        RateLimiter::hit($this->key($binding, $operator), self::DECAY_SECONDS);
    }

    public function clear(WorkstationBrowserBinding $binding, User $operator): void
    {
        RateLimiter::clear($this->key($binding, $operator));
    }

    private function key(WorkstationBrowserBinding $binding, User $operator): string
    {
        return 'workstation-unlock:'.$binding->id.':'.$operator->id;
    }
}
