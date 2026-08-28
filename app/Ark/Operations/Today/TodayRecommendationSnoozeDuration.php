<?php

namespace App\Ark\Operations\Today;

use Illuminate\Support\Carbon;

enum TodayRecommendationSnoozeDuration: string
{
    case Tomorrow = 'tomorrow';
    case ThreeDays = 'three_days';
    case OneWeek = 'one_week';

    public function label(): string
    {
        return match ($this) {
            self::Tomorrow => 'Until tomorrow',
            self::ThreeDays => '3 days',
            self::OneWeek => '1 week',
        };
    }

    public function snoozedUntil(Carbon $from): Carbon
    {
        return match ($this) {
            self::Tomorrow => $from->copy()->addDay()->startOfDay()->setTime(8, 0),
            self::ThreeDays => $from->copy()->addDays(3)->startOfDay()->setTime(8, 0),
            self::OneWeek => $from->copy()->addWeek()->startOfDay()->setTime(8, 0),
        };
    }
}
