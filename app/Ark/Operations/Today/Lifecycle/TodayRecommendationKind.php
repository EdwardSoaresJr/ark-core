<?php

namespace App\Ark\Operations\Today\Lifecycle;

enum TodayRecommendationKind: string
{
    case EstimateFollowUp = 'estimate_follow_up';
    case PartsArrival = 'parts_arrival';

    public function label(): string
    {
        return match ($this) {
            self::EstimateFollowUp => 'Estimate follow-up',
            self::PartsArrival => 'Parts arrival',
        };
    }
}
