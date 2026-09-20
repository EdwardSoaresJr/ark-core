<?php

namespace App\Ark\Operations\Recommendations;

enum RecommendationDueKind: string
{
    case None = 'none';
    case Now = 'now';
    case Date = 'date';
    case Mileage = 'mileage';
    case DateOrMileage = 'date_or_mileage';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No due',
            self::Now => 'Due now',
            self::Date => 'Due date',
            self::Mileage => 'Due mileage',
            self::DateOrMileage => 'Date or mileage',
        };
    }
}
