<?php

namespace App\Ark\Operations\Telephony;

enum TelephonyExtensionDeviceType: string
{
    case DeskPhone = 'desk_phone';
    case Softphone = 'softphone';
    case MobileApp = 'mobile_app';
    case Tablet = 'tablet';
    case PageGroup = 'page_group';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DeskPhone => 'Desk phone',
            self::Softphone => 'Softphone',
            self::MobileApp => 'Mobile app',
            self::Tablet => 'Tablet',
            self::PageGroup => 'Page group',
            self::Other => 'Other',
        };
    }
}
