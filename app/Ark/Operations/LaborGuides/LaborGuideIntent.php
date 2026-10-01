<?php

namespace App\Ark\Operations\LaborGuides;

use App\Ark\Operations\LaborGuides\Rte\RteLaborGuideAvailability;

final class LaborGuideIntent
{
    public const KEY = 'rte';

    public static function label(): string
    {
        return 'Labor Guide';
    }

    public static function tooltip(): string
    {
        return 'Look up labor times, or add hours on a labor line.';
    }

    public static function rteAvailable(): bool
    {
        return app(RteLaborGuideAvailability::class)->available();
    }
}
