<?php

namespace App\Ark\Operations\Labor;

enum TechnicianTimeSessionOrigin: string
{
    case Manual = 'manual';
    case Auto = 'auto';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Auto => 'Auto',
        };
    }
}
