<?php

namespace App\Ark\Operations\Appointments;

enum AppointmentKind: string
{
    case Intake = 'intake';
    case Return = 'return';
    case FollowUp = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::Intake => 'Intake',
            self::Return => 'Return',
            self::FollowUp => 'Follow-up',
        };
    }
}
