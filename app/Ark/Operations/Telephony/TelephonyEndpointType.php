<?php

namespace App\Ark\Operations\Telephony;

enum TelephonyEndpointType: string
{
    case Cell = 'cell';
    case Sip = 'sip';
    case MobileApp = 'mobile_app';

    public function label(): string
    {
        return match ($this) {
            self::Cell => 'Cell',
            self::Sip => 'SIP',
            self::MobileApp => 'Mobile app',
        };
    }
}
