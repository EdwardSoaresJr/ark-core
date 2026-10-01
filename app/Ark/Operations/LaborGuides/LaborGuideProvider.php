<?php

namespace App\Ark\Operations\LaborGuides;

enum LaborGuideProvider: string
{
    case AllData = 'alldata';
    case ProDemand = 'prodemand';

    public function label(): string
    {
        return match ($this) {
            self::AllData => 'AllData',
            self::ProDemand => 'ProDemand',
        };
    }

    public static function fromRoute(string $provider): self
    {
        return self::from($provider);
    }

    /**
     * @return list<self>
     */
    public static function enabled(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $provider): bool => app(LaborGuideLauncher::class)->configured($provider),
        ));
    }
}
