<?php

namespace App\Ark\Operations\Encounters;

enum EncounterOperationalState: string
{
    case New = 'new';
    case Active = 'active';
    case Dormant = 'dormant';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Active => 'Active',
            self::Dormant => 'Dormant',
            self::Converted => 'Converted',
        };
    }
}
