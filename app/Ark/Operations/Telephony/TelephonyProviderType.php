<?php

namespace App\Ark\Operations\Telephony;

enum TelephonyProviderType: string
{
    case None = 'none';
    case Fake = 'fake';
    case Twilio = 'twilio';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Not configured',
            self::Fake => 'Fake',
            self::Twilio => 'Legacy (removed)',
        };
    }
}
