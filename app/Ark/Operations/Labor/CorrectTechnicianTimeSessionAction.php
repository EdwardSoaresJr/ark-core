<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

final class CorrectTechnicianTimeSessionAction
{
    public function __construct(
        private readonly RecomputeTechnicianCompensableDayAction $recompute,
    ) {}

    public function handle(
        TechnicianTimeSession $session,
        ?string $clockedInLocal,
        ?string $clockedOutLocal,
        string $reason,
        User $actor,
    ): TechnicianTimeSession {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException('A correction reason is required.');
        }

        if ($session->isDeleted()) {
            throw new RuntimeException('Deleted punches cannot be corrected.');
        }

        $session->loadMissing('technician');
        $technician = $session->technician;

        if ($technician === null) {
            throw new RuntimeException('Technician not found for this session.');
        }

        $affectedDates = [];

        if ($clockedInLocal !== null && $clockedInLocal !== '') {
            $newIn = ShopDisplayTimezone::parseLocal($clockedInLocal)->utc();
            if (! $session->clocked_in_at->equalTo($newIn)) {
                $affectedDates[] = ShopDisplayTimezone::present($session->clocked_in_at)->toDateString();
                $affectedDates[] = ShopDisplayTimezone::present($newIn)->toDateString();
                $this->recordCorrection($session, 'clocked_in_at', $session->clocked_in_at, $newIn, $reason, $actor);
                $session->clocked_in_at = $newIn;
            }
        }

        if ($clockedOutLocal !== null && $clockedOutLocal !== '') {
            $newOut = ShopDisplayTimezone::parseLocal($clockedOutLocal)->utc();
            $previousOut = $session->clocked_out_at;

            if ($previousOut === null || ! $previousOut->equalTo($newOut)) {
                if ($previousOut !== null) {
                    $affectedDates[] = ShopDisplayTimezone::present($previousOut)->toDateString();
                }
                $affectedDates[] = ShopDisplayTimezone::present($newOut)->toDateString();
                $this->recordCorrection(
                    $session,
                    'clocked_out_at',
                    $previousOut,
                    $newOut,
                    $reason,
                    $actor,
                );
                $session->clocked_out_at = $newOut;
            }
        }

        if ($session->clocked_out_at !== null) {
            $session->status = TechnicianTimeSessionStatus::Closed->value;
        }

        $session->save();

        foreach (array_unique($affectedDates) as $date) {
            $this->recompute->recomputeOne($technician, $date);
        }

        return $session->fresh();
    }

    private function recordCorrection(
        TechnicianTimeSession $session,
        string $field,
        ?Carbon $from,
        Carbon $to,
        string $reason,
        User $actor,
    ): void {
        TechnicianTimeSessionCorrection::query()->create([
            'technician_time_session_id' => $session->id,
            'field' => $field,
            'from_value' => $from?->toIso8601String(),
            'to_value' => $to->toIso8601String(),
            'reason' => $reason,
            'corrected_by_user_id' => $actor->id,
            'corrected_at' => now('UTC'),
        ]);
    }
}
