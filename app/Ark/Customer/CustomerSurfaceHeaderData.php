<?php

namespace App\Ark\Customer;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyBusinessHoursLabel;
use App\Support\Mail\ShopMailBranding;

final class CustomerSurfaceHeaderData
{
    /**
     * @return array{shop: ShopSettings, logoUrl: string|null, businessHoursLabel: string|null}
     */
    public static function viewData(): array
    {
        return [
            'shop' => ShopSettings::current(),
            'logoUrl' => ShopMailBranding::logoUrl(),
            'businessHoursLabel' => TelephonyBusinessHoursLabel::fromCallFlow(),
        ];
    }
}
