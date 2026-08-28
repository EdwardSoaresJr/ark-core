<?php

namespace App\Ark\Operations\Telephony;

enum TelephonyProviderType: string
{
    case Fake = 'fake';
    case Twilio = 'twilio';

    public function label(): string
    {
        return match ($this) {
            self::Fake => 'Fake',
            self::Twilio => 'Twilio',
        };
    }
}
