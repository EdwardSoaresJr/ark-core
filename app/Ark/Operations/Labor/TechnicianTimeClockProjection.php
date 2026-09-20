<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Collection;

final class TechnicianTimeClockProjection
{
    public static function canManage(?User $user): bool
    {
        return OwnerWorkspaceAccess::allows($user);
    }

    /**
     * Floor staff who may punch someone else in/out on their behalf.
     */
    public static function canPunchForStaff(?User $user): bool
    {
        if ($user === null || ! $user->isActive()) {
            return false;
        }

        return $user->hasAnyRole([
            ArkRole::Admin->value,
            ArkRole::Advisor->value,
        ]);
    }

    /**
     * Anyone whose own work is trackable on the time clock — technician,
     * advisor, or admin. Not customer, not inactive.
     */
    public static function canBeClocked(?User $user): bool
    {
        if ($user === null || ! $user->isActive()) {
            return false;
        }

        return $user->worksAsTechnician()
            || $user->worksAsAdvisor()
            || $user->hasRole(ArkRole::Admin->value);
    }

    public static function canSelfPunch(?User $user): bool
    {
        return self::canBeClocked($user);
    }

    public static function canAccess(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return self::canBeClocked($user)
            || self::canManage($user)
            || self::canPunchForStaff($user);
    }

    /**
     * Staff eligible to appear on the punch-for-staff list — any active
     * technician, advisor, or admin. Admin assigns auto day to any of these.
     *
     * @return Collection<int, User>
     */
    public static function staffForClockList(): Collection
    {
        return User::query()
            ->active()
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', [
                    ArkRole::Technician->value,
                    ArkRole::Advisor->value,
                    ArkRole::Admin->value,
                ]);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTechnician(User $technician): array
    {
        $todayStart = ShopDisplayTimezone::now()->startOfDay();
        $todayEnd = $todayStart->copy()->endOfDay();
        $openSession = TechnicianTimeSession::openForTechnician((int) $technician->id);
        $recompute = app(RecomputeTechnicianCompensableDayAction::class);

        $todayPunches = TechnicianTimeSession::query()
            ->active()
            ->where('user_id', $technician->id)
            ->where('clocked_in_at', '<=', $todayEnd->copy()->utc())
            ->where(function ($query) use ($todayStart): void {
                $query->whereNull('clocked_out_at')
                    ->orWhere('clocked_out_at', '>=', $todayStart->copy()->utc());
            })
            ->orderByDesc('clocked_in_at')
            ->get();

        $awaitingLunchReturn = $openSession === null
            && $todayPunches->isNotEmpty()
            && $todayPunches->first()->close_reason === TechnicianTimeSessionCloseReason::Lunch->value;

        $todayHours = $recompute->hoursForWorkDate($technician, $todayStart->toDateString());
        $todayElapsedSeconds = (int) round($todayHours * 3600);

        return [
            'technician' => $technician,
            'is_clocked_in' => $openSession !== null,
            'open_session' => $openSession,
            'awaiting_lunch_return' => $awaitingLunchReturn,
            'status_label' => self::statusLabel($openSession, $awaitingLunchReturn),
            'today_elapsed_seconds' => $todayElapsedSeconds,
            'today_compensable_hours' => $todayHours,
            'today_punches' => $todayPunches,
            'auto_clock_enabled' => (bool) $technician->auto_clock_enabled,
            'auto_lunch_minutes' => $technician->auto_lunch_minutes,
        ];
    }

    /**
     * @return Collection<int, TechnicianTimeSession>
     */
    public static function needsResolutionRows(): Collection
    {
        return TechnicianTimeSession::query()
            ->where('status', TechnicianTimeSessionStatus::NeedsResolution->value)
            ->whereNull('clocked_out_at')
            ->with('technician')
            ->orderBy('clocked_in_at')
            ->get();
    }

    public static function statusLabel(?TechnicianTimeSession $openSession, bool $awaitingLunchReturn = false): string
    {
        if ($openSession !== null) {
            $since = ShopDisplayTimezone::format($openSession->clocked_in_at, 'g:i A');

            return 'Clocked in since '.$since;
        }

        if ($awaitingLunchReturn) {
            return 'Out for lunch';
        }

        return 'Clocked out';
    }
}
