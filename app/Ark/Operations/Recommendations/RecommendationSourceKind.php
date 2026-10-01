<?php

namespace App\Ark\Operations\Recommendations;

enum RecommendationSourceKind: string
{
    case Inspection = 'inspection';
    case Advisor = 'advisor';
    case Maintenance = 'maintenance';
    case Estimate = 'estimate';

    public function label(): string
    {
        return match ($this) {
            self::Inspection => 'Inspection',
            self::Advisor => 'Advisor',
            self::Maintenance => 'Maintenance',
            self::Estimate => 'Estimate',
        };
    }
}
