<?php

namespace App\Ark\Operations\Communications;

enum CommunicationDeviceCapability: string
{
    case Voice = 'voice';
    case Transfer = 'transfer';
    case Hold = 'hold';
    case Page = 'page';
    case Sms = 'sms';

    /**
     * @return list<string>
     */
    public static function deskPhoneDefaults(): array
    {
        return [
            self::Voice->value,
            self::Transfer->value,
            self::Hold->value,
        ];
    }
}
