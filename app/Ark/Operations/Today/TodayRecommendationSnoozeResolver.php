<?php

namespace App\Ark\Operations\Today;

use App\Models\User;

final class TodayRecommendationSnoozeResolver
{
    /**
     * @return list<int> Shop repair order numbers snoozed for this advisor.
     */
    public function activeShopRepairOrderIds(User $user): array
    {
        return TodayRecommendationSnooze::query()
            ->where('user_id', $user->id)
            ->where('snoozed_until', '>', now())
            ->orderBy('repair_order_id')
            ->pluck('repair_order_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
