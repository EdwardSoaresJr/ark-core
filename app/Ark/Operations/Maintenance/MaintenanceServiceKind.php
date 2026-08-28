<?php

namespace App\Ark\Operations\Maintenance;

enum MaintenanceServiceKind: string
{
    case EngineOil = 'engine_oil';

    public function label(): string
    {
        return match ($this) {
            self::EngineOil => 'Engine Oil Service',
        };
    }
}
