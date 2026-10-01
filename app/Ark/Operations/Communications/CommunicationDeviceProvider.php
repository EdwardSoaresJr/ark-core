<?php

namespace App\Ark\Operations\Communications;

enum CommunicationDeviceProvider: string
{
    case ShopPhone = 'shop_phone';
    case Twilio = 'twilio';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::ShopPhone => 'Shop phone',
            self::Twilio => 'Twilio',
            self::Mobile => 'Mobile',
        };
    }
}
