<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;

final class MarkOvernightOpenSessionsAction
{
    public function handle(?User $technician = null): int
    {
        $todayStartUtc = ShopDisplayTimezone::now()->startOfDay()->copy()->utc();

        $query = TechnicianTimeSession::query()
            ->whereNull('clocked_out_at')
            ->where('clocked_in_at', '<', $todayStartUtc)
            ->where('status', TechnicianTimeSessionStatus::Open->value);

        if ($technician !== null) {
            $query->where('user_id', $technician->id);
        }

        return $query->update([
            'status' => TechnicianTimeSessionStatus::NeedsResolution->value,
        ]);
    }
}
