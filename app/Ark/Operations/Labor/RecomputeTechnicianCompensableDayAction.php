<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Materialize punch-derived daily hours into technician_compensable_time_entries.
 * Never overwrites manual-locked days.
 */
final class RecomputeTechnicianCompensableDayAction
{
    public function recomputeOne(User $technician, Carbon|string $workDate): void
    {
        $this->handle($technician, $workDate);
    }

    public function handle(User $technician, Carbon|string $workDate): void
    {
        $workDate = $this->normalizeWorkDate($workDate);

        $existing = TechnicianCompensableTimeEntry::query()
            ->where('user_id', $technician->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->first();

        if ($existing?->isManualLocked()) {
            return;
        }

        $hours = $this->hoursForWorkDate($technician, $workDate);

        if ($hours <= 0) {
            if ($existing !== null && $existing->sourceEnum() === TechnicianCompensableTimeSource::PunchDerived) {
                $existing->delete();
            }

            return;
        }

        $payload = [
            'compensable_hours' => round($hours, 2),
            'source' => TechnicianCompensableTimeSource::PunchDerived->value,
            'manual_locked' => false,
            'recorded_by_user_id' => null,
        ];

        if ($existing !== null) {
            $existing->update($payload);

            return;
        }

        TechnicianCompensableTimeEntry::query()->create([
            'user_id' => $technician->id,
            'work_date' => $workDate->toDateString(),
            ...$payload,
        ]);
    }

    public function hoursForWorkDate(User $technician, Carbon|string $workDate): float
    {
        $workDate = $this->normalizeWorkDate($workDate);
        $dayStart = ShopDisplayTimezone::parseLocal($workDate->format('Y-m-d').' 00:00:00');
        $dayEnd = ShopDisplayTimezone::parseLocal($workDate->format('Y-m-d').' 23:59:59');
        $todayStart = ShopDisplayTimezone::now()->startOfDay();
        $isToday = $dayStart->toDateString() === $todayStart->toDateString();
        $now = now('UTC');

        $sessions = TechnicianTimeSession::query()
            ->active()
            ->where('user_id', $technician->id)
            ->where('clocked_in_at', '<=', $dayEnd->copy()->utc())
            ->where(function ($query) use ($dayStart): void {
                $query->whereNull('clocked_out_at')
                    ->orWhere('clocked_out_at', '>=', $dayStart->copy()->utc());
            })
            ->get();

        $totalSeconds = 0;

        foreach ($sessions as $session) {
            $totalSeconds += $this->sessionSecondsOnDate($session, $dayStart, $dayEnd, $isToday, $now);
        }

        $totalSeconds = $this->deductAutoLunchIfUnpunched($technician, $sessions, $totalSeconds, $dayStart, $dayEnd);

        return round($totalSeconds / 3600, 2);
    }

    /**
     * Auto-clock technicians without a manual lunch punch that shop day lose
     * the shop's default lunch minutes once. If a real Out for Lunch / Back
     * from Lunch punch already exists that day, the gap is already excluded
     * from the summed punch seconds above — never double-deduct.
     */
    private function deductAutoLunchIfUnpunched(
        User $technician,
        Collection $sessions,
        int $totalSeconds,
        Carbon $dayStart,
        Carbon $dayEnd,
    ): int {
        if (! $technician->auto_clock_enabled) {
            return $totalSeconds;
        }

        $lunchMinutes = (int) ($technician->auto_lunch_minutes ?? 0);

        if ($lunchMinutes <= 0) {
            return $totalSeconds;
        }

        $dayStartUtc = $dayStart->copy()->utc();
        $dayEndUtc = $dayEnd->copy()->utc();

        $hasLunchPunch = $sessions->contains(function (TechnicianTimeSession $session) use ($dayStartUtc, $dayEndUtc): bool {
            return $session->close_reason === TechnicianTimeSessionCloseReason::Lunch->value
                && $session->clocked_out_at !== null
                && $session->clocked_out_at->between($dayStartUtc, $dayEndUtc);
        });

        if ($hasLunchPunch) {
            return $totalSeconds;
        }

        return max(0, $totalSeconds - ($lunchMinutes * 60));
    }

    private function sessionSecondsOnDate(
        TechnicianTimeSession $session,
        Carbon $dayStart,
        Carbon $dayEnd,
        bool $isToday,
        Carbon $now,
    ): int {
        if ($session->clocked_out_at !== null) {
            $start = $session->clocked_in_at->greaterThan($dayStart) ? $session->clocked_in_at : $dayStart;
            $end = $session->clocked_out_at->lessThan($dayEnd) ? $session->clocked_out_at : $dayEnd;

            return $this->secondsBetween($start, $end);
        }

        // Unclosed sessions (open or needs_resolution) only ever count toward
        // shop-local TODAY. The prior calendar day of an overnight punch is
        // never invented until the session is closed or corrected.
        if (! $isToday) {
            return 0;
        }

        $start = $session->clocked_in_at->greaterThan($dayStart) ? $session->clocked_in_at : $dayStart;
        $end = $now->lessThan($dayEnd) ? $now : $dayEnd;

        return $this->secondsBetween($start, $end);
    }

    private function secondsBetween(Carbon $start, Carbon $end): int
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return max(0, (int) $start->diffInSeconds($end));
    }

    private function normalizeWorkDate(Carbon|string $workDate): Carbon
    {
        if ($workDate instanceof Carbon) {
            return ShopDisplayTimezone::parseLocal($workDate->format('Y-m-d').' 00:00:00');
        }

        return ShopDisplayTimezone::parseLocal((string) $workDate.' 00:00:00');
    }
}
