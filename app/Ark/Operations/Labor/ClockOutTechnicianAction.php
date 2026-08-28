<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

final class ClockOutTechnicianAction
{
    public function __construct(
        private readonly RecomputeTechnicianCompensableDayAction $recompute,
    ) {}

    public function handle(
        User $technician,
        ?User $actor = null,
        ?TechnicianTimeSessionCloseReason $closeReason = null,
        ?Carbon $clockedOutAt = null,
    ): TechnicianTimeSession {
        if (! TechnicianTimeClockProjection::canBeClocked($technician)) {
            throw new RuntimeException('Only technicians, advisors, and admins can clock out.');
        }

        $session = TechnicianTimeSession::openForTechnician((int) $technician->id);

        if ($session === null) {
            throw new RuntimeException('No open time session to clock out.');
        }

        $clockedOutAt ??= now('UTC');

        $session->forceFill([
            'clocked_out_at' => $clockedOutAt,
            'clocked_out_by_user_id' => ($actor ?? $technician)->id,
            'status' => TechnicianTimeSessionStatus::Closed->value,
            'close_reason' => $closeReason?->value,
        ])->save();

        $inDate = ShopDisplayTimezone::present($session->clocked_in_at)->toDateString();
        $outDate = ShopDisplayTimezone::present($session->clocked_out_at)->toDateString();

        $this->recompute->recomputeOne($technician, $inDate);

        if ($outDate !== $inDate) {
            $this->recompute->recomputeOne($technician, $outDate);
        }

        return $session->fresh();
    }
}
