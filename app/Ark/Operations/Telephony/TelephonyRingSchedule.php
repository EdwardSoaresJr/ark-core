<?php

namespace App\Ark\Operations\Telephony;

enum TelephonyRingSchedule: string
{
    case Always = 'always';
    case WhenPresent = 'when_present';
    case OnDuty = 'on_duty';

    public function label(): string
    {
        return match ($this) {
            self::Always => 'Always ring during open hours',
            self::WhenPresent => 'Ring when logged into ARK',
            self::OnDuty => 'Ring when on duty',
        };
    }
}
