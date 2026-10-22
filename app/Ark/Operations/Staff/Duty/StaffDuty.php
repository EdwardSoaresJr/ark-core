<?php

namespace App\Ark\Operations\Staff\Duty;

use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Server-side duty records. Duty never depends on browser activity: it starts with an
 * interactive sign-in (or another explicit kind) and ends at sign-out, the shop's closing
 * time, a planned end, or the shop's maximum duty length, whichever comes first.
 */
class StaffDuty
{
    public function startFromSignIn(User $user, ?CarbonImmutable $at = null): StaffDutyPeriod
    {
        return $this->start($user, StaffDutyKind::SignIn, at: $at);
    }

    /**
     * An existing effective period is returned unchanged, so a repeated sign-in never extends duty.
     */
    public function start(
        User $user,
        StaffDutyKind $kind,
        ?CarbonImmutable $endsAt = null,
        ?User $authorizedBy = null,
        ?CarbonImmutable $at = null,
    ): StaffDutyPeriod {
        $at = $this->stored($at ?? CarbonImmutable::now());
        $endsAt = $endsAt === null ? null : $this->stored($endsAt);

        if ($kind === StaffDutyKind::AfterHoursCoverage && ($endsAt === null || $authorizedBy === null)) {
            throw new InvalidArgumentException('After-hours coverage needs an end time and the person who authorized it.');
        }

        $active = $this->activePeriod($user->id, $at);

        if ($active !== null && $active->kind === $kind) {
            return $active;
        }

        $this->closeLapsed($user->id, $at);

        return StaffDutyPeriod::query()->create([
            'user_id' => $user->id,
            'kind' => $kind,
            'started_at' => $at,
            'ends_at' => $endsAt,
            'authorized_by_user_id' => $authorizedBy?->id,
        ]);
    }

    public function end(User $user, StaffDutyEndReason $reason, ?CarbonImmutable $at = null): void
    {
        $at = $this->stored($at ?? CarbonImmutable::now());

        $this->openPeriods($user->id)->each(function (StaffDutyPeriod $period) use ($reason, $at): void {
            [$lapsesAt, $lapseReason] = $this->lapse($period);

            $period->forceFill($lapsesAt->lessThanOrEqualTo($at)
                ? ['ended_at' => $lapsesAt, 'end_reason' => $lapseReason]
                : ['ended_at' => $at, 'end_reason' => $reason])->save();
        });
    }

    public function activePeriod(?int $userId, ?CarbonImmutable $at = null): ?StaffDutyPeriod
    {
        if ($userId === null) {
            return null;
        }

        $at ??= CarbonImmutable::now();

        return $this->openPeriods($userId)
            ->first(fn (StaffDutyPeriod $period): bool => $period->started_at->lessThanOrEqualTo($at)
                && $this->lapse($period)[0]->greaterThan($at));
    }

    public function isOnDuty(?int $userId, int $afterMinutes = 0, ?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now();
        $period = $this->activePeriod($userId, $at);

        return $period !== null
            && $period->started_at->lessThanOrEqualTo($at->subMinutes(max(0, $afterMinutes)));
    }

    /**
     * @return array{0: CarbonImmutable, 1: StaffDutyEndReason}
     */
    public function lapse(StaffDutyPeriod $period): array
    {
        $flow = TelephonyCallFlowSettings::fromShopSettings();
        $end = $period->started_at->addHours($flow->dutyMaxHours());
        $reason = StaffDutyEndReason::MaximumLength;

        if ($period->ends_at !== null && $period->ends_at->lessThan($end)) {
            [$end, $reason] = [$period->ends_at, StaffDutyEndReason::Scheduled];
        }

        if ($period->kind->endsAtShopClose()) {
            $timezone = $flow->timezone();
            $startedLocal = $period->started_at->timezone($timezone);
            $close = $flow->closeAtForDay($startedLocal, $timezone);
            $shopEnd = $close !== null && $startedLocal->lessThan($close) ? $close : $startedLocal->endOfDay();

            if ($shopEnd->lessThan($end)) {
                [$end, $reason] = [$shopEnd, StaffDutyEndReason::ShopClosed];
            }
        }

        return [$this->stored($end), $reason];
    }

    /**
     * Timestamps are written in the application timezone; shop-local times would otherwise be stored as wall-clock values.
     */
    private function stored(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->timezone((string) config('app.timezone', 'UTC'));
    }

    private function closeLapsed(int $userId, CarbonImmutable $at): void
    {
        $this->openPeriods($userId)->each(function (StaffDutyPeriod $period) use ($at): void {
            [$lapsesAt, $reason] = $this->lapse($period);

            if ($lapsesAt->lessThanOrEqualTo($at)) {
                $period->forceFill(['ended_at' => $lapsesAt, 'end_reason' => $reason])->save();
            }
        });
    }

    /**
     * @return Collection<int, StaffDutyPeriod>
     */
    private function openPeriods(int $userId)
    {
        return StaffDutyPeriod::query()
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->orderBy('started_at')
            ->get();
    }
}
