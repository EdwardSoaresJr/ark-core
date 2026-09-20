<?php

namespace App\Ark\Operations\Labor;

enum TechnicianTimeSessionCloseReason: string
{
    case Lunch = 'lunch';
    case EndOfDay = 'end_of_day';

    public function label(): string
    {
        return match ($this) {
            self::Lunch => 'Lunch',
            self::EndOfDay => 'End of day (auto)',
        };
    }
}
