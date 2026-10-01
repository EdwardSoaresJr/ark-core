<?php

namespace App\Ark\Operations\Recommendations;

enum RecommendationResolvedReason: string
{
    case Performed = 'performed';
    case VerifiedElsewhere = 'verified_elsewhere';
    case ConditionGone = 'condition_gone';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Performed => 'Performed',
            self::VerifiedElsewhere => 'Verified completed elsewhere',
            self::ConditionGone => 'Condition no longer exists',
            self::Superseded => 'Superseded',
        };
    }
}
