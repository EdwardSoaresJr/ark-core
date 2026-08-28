<?php

namespace App\Ark\Growth\Opportunities;

enum GrowthOpportunityEffort: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small',
            self::Medium => 'Medium',
            self::Large => 'Large',
        };
    }

    public function sortWeight(): int
    {
        return match ($this) {
            self::Small => 3,
            self::Medium => 2,
            self::Large => 1,
        };
    }
}
