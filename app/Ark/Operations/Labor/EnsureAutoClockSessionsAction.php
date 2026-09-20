<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Materializes and closes the auto-assigned workday for staff whose day is
 * covered by Business Hours instead of a real punch. Explicit punches
 * (including lunch) always win — this action never overrides an unresolved
 * lunch break and never reopens a day that already ended.
 */
final class EnsureAutoClockSessionsAction
{
    public function __construct(
        private readonly ClockInTechnicianAction $clockIn,
        private readonly ClockOutTechnicianAction $clockOut,
    ) {}

    public function handle(): void
    {
        $callFlow = TelephonyCallFlowSettings::fromShopSettings();

        $users = User::query()
            ->where('auto_clock_enabled', true)
            ->active()
            ->get();

        foreach ($users as $user) {
            $this->sync($user, $callFlow);
        }
    }

    public function handleForUser(User $user): void
    {
        if (! $user->auto_clock_enabled || ! $user->isActive()) {
            return;
        }

        $this->sync($user, TelephonyCallFlowSettings::fromShopSettings());
    }

    private function sync(User $user, TelephonyCallFlowSettings $callFlow): void
    {
        $now = ShopDisplayTimezone::now();
        $today = $now->copy()->startOfDay();

        $openAt = $callFlow->openAtForDay($today);
        $closeAt = $callFlow->closeAtForDay($today);

        if ($openAt === null || $closeAt === null) {
            return;
        }

        if ($this->isAwaitingLunchReturn($user)) {
            return;
        }

        if ($this->hasEndedDay($user, $today)) {
            return;
        }

        $openSession = TechnicianTimeSession::openForTechnician((int) $user->id);

        if ($openSession === null && $now->greaterThanOrEqualTo($openAt)) {
            $this->clockIn->handle($user, $user, TechnicianTimeSessionOrigin::Auto, Carbon::instance($openAt)->utc());
            $openSession = TechnicianTimeSession::openForTechnician((int) $user->id);
        }

        if ($openSession !== null && $now->greaterThanOrEqualTo($closeAt)) {
            $this->clockOut->handle($user, $user, TechnicianTimeSessionCloseReason::EndOfDay, Carbon::instance($closeAt)->utc());
        }
    }

    /**
     * A closed Out for Lunch punch with no newer session means the person is
     * currently on their break — never auto-materialize or auto-close over it.
     */
    private function isAwaitingLunchReturn(User $user): bool
    {
        if (TechnicianTimeSession::openForTechnician((int) $user->id) !== null) {
            return false;
        }

        $latest = TechnicianTimeSession::query()
            ->active()
            ->where('user_id', $user->id)
            ->whereNotNull('clocked_out_at')
            ->orderByDesc('clocked_out_at')
            ->first();

        return $latest !== null
            && $latest->close_reason === TechnicianTimeSessionCloseReason::Lunch->value;
    }

    private function hasEndedDay(User $user, Carbon $today): bool
    {
        return TechnicianTimeSession::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('close_reason', TechnicianTimeSessionCloseReason::EndOfDay->value)
            ->whereBetween('clocked_out_at', [
                $today->copy()->utc(),
                $today->copy()->endOfDay()->utc(),
            ])
            ->exists();
    }
}
