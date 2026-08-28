<?php

namespace App\Ark\Growth\Opportunities;

enum GrowthOpportunityStatus: string
{
    case Discovered = 'discovered';
    case Accepted = 'accepted';
    case Building = 'building';
    case Published = 'published';
    case Measuring = 'measuring';
    case Validated = 'validated';

    public function label(): string
    {
        return match ($this) {
            self::Discovered => 'Discovered',
            self::Accepted => 'Accepted',
            self::Building => 'Building',
            self::Published => 'Published',
            self::Measuring => 'Measuring',
            self::Validated => 'Validated',
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Validated], true);
    }

    /**
     * @return list<GrowthOpportunityStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Discovered => [self::Accepted],
            self::Accepted => [self::Building, self::Discovered],
            self::Building => [self::Published, self::Accepted],
            self::Published => [self::Measuring],
            self::Measuring => [self::Validated, self::Discovered],
            self::Validated => [self::Discovered],
        };
    }
}
