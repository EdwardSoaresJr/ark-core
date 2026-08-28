<?php

namespace App\Ark\Customer;

use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\Settings\ShopSettings;
use App\Support\Mail\ShopMailBranding;

final class CustomerSurfaceHeaderData
{
    /**
     * @return array{shop: ShopSettings, logoUrl: string|null, businessHoursLabel: string|null}
     */
    public static function viewData(): array
    {
        $publicSurface = PublicSurfaceSettings::current();

        return [
            'shop' => ShopSettings::current(),
            'logoUrl' => ShopMailBranding::logoUrl(),
            'businessHoursLabel' => $publicSurface['business_hours_label'],
        ];
    }
}
