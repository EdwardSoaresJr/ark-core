<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;

final class TelephonyOutboundCallerId
{
    public function resolve(?ShopSettings $settings = null): ?string
    {
        $settings ??= ShopSettings::current();

        if (! filled($settings->telephony_inbound_number)) {
            return null;
        }

        return PhoneNumber::toE164((string) $settings->telephony_inbound_number);
    }
}
