<?php

namespace App\Ark\Operations\Appointments;

enum AppointmentCapacityEnforcement: string
{
    case Warn = 'warn';
    case Block = 'block';

    public function label(): string
    {
        return match ($this) {
            self::Warn => 'Warn and allow',
            self::Block => 'Block scheduling',
        };
    }
}
