<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use RuntimeException;

final class DeleteTechnicianTimeSessionAction
{
    public function __construct(
        private readonly RecomputeTechnicianCompensableDayAction $recompute,
    ) {}

    public function handle(
        TechnicianTimeSession $session,
        string $reason,
        User $actor,
    ): TechnicianTimeSession {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException('A delete reason is required.');
        }

        if ($session->isDeleted()) {
            throw new RuntimeException('This punch is already deleted.');
        }

        $session->loadMissing('technician');
        $technician = $session->technician;

        if ($technician === null) {
            throw new RuntimeException('Technician not found for this session.');
        }

        $affectedDates = [
            ShopDisplayTimezone::present($session->clocked_in_at)->toDateString(),
        ];

        if ($session->clocked_out_at !== null) {
            $affectedDates[] = ShopDisplayTimezone::present($session->clocked_out_at)->toDateString();
        }

        $previousStatus = $session->status;

        TechnicianTimeSessionCorrection::query()->create([
            'technician_time_session_id' => $session->id,
            'field' => 'deleted',
            'from_value' => $previousStatus,
            'to_value' => TechnicianTimeSessionStatus::Deleted->value,
            'reason' => $reason,
            'corrected_by_user_id' => $actor->id,
            'corrected_at' => now('UTC'),
        ]);

        $session->status = TechnicianTimeSessionStatus::Deleted->value;
        $session->save();

        foreach (array_unique($affectedDates) as $date) {
            $this->recompute->recomputeOne($technician, $date);
        }

        // Open deleted punches no longer block clock-in; recompute today if still open.
        if ($session->clocked_out_at === null) {
            $this->recompute->recomputeOne($technician, ShopDisplayTimezone::now()->toDateString());
        }

        return $session->fresh();
    }
}
