<?php

namespace App\Ark\Operations\Appointments;

enum AppointmentCapacityBasis: string
{
    case Technicians = 'technicians';
    case Bays = 'bays';
    case LimitingResource = 'limiting_resource';

    public function label(): string
    {
        return match ($this) {
            self::Technicians => 'Technicians',
            self::Bays => 'Bays',
            self::LimitingResource => 'Limiting resource',
        };
    }
}
