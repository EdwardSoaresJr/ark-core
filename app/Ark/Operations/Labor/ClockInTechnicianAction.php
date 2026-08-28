<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

final class ClockInTechnicianAction
{
    public function __construct(
        private readonly RecomputeTechnicianCompensableDayAction $recompute,
    ) {}

    public function handle(
        User $technician,
        ?User $actor = null,
        ?TechnicianTimeSessionOrigin $origin = null,
        ?Carbon $clockedInAt = null,
    ): TechnicianTimeSession {
        if (! TechnicianTimeClockProjection::canBeClocked($technician)) {
            throw new RuntimeException('Only technicians, advisors, and admins can clock in.');
        }

        if (TechnicianTimeSession::openForTechnician((int) $technician->id) !== null) {
            throw new RuntimeException('An open time session already exists.');
        }

        $clockedInAt ??= now('UTC');

        $session = TechnicianTimeSession::query()->create([
            'user_id' => $technician->id,
            'clocked_in_at' => $clockedInAt,
            'clocked_in_by_user_id' => ($actor ?? $technician)->id,
            'status' => TechnicianTimeSessionStatus::Open->value,
            'origin' => ($origin ?? TechnicianTimeSessionOrigin::Manual)->value,
        ]);

        $this->recompute->recomputeOne($technician, ShopDisplayTimezone::present($clockedInAt)->toDateString());

        return $session;
    }
}
