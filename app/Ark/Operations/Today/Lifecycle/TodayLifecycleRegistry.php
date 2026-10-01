<?php

namespace App\Ark\Operations\Today\Lifecycle;

final class TodayLifecycleRegistry
{
    /**
     * @return list<TodayRecommendationLifecycle>
     */
    public function lifecycles(): array
    {
        return [
            app(EstimateFollowUpLifecycle::class),
            app(PartsArrivalLifecycle::class),
        ];
    }

    public function find(TodayRecommendationKind $kind): ?TodayRecommendationLifecycle
    {
        foreach ($this->lifecycles() as $lifecycle) {
            if ($lifecycle->kind() === $kind) {
                return $lifecycle;
            }
        }

        return null;
    }
}
