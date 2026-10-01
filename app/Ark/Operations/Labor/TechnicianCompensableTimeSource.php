<?php

namespace App\Ark\Operations\Labor;

enum TechnicianCompensableTimeSource: string
{
    case ManualOverride = 'manual_override';
    case PunchDerived = 'punch_derived';

    public function label(): string
    {
        return match ($this) {
            self::ManualOverride => 'Manual override',
            self::PunchDerived => 'Time clock',
        };
    }
}
