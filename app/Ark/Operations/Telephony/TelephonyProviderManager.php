<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\Contracts\TelephonyProvider;
use App\Ark\Operations\Telephony\Providers\NotConfiguredTelephonyProvider;
use RuntimeException;

class TelephonyProviderManager
{
    public function current(): TelephonyProvider
    {
        return app(NotConfiguredTelephonyProvider::class);
    }

    public function currentType(): TelephonyProviderType
    {
        return TelephonyProviderType::None;
    }

    public function resolve(TelephonyProviderType $type): TelephonyProvider
    {
        return app(NotConfiguredTelephonyProvider::class);
    }

    public function twilio(): TelephonyProvider
    {
        throw new RuntimeException('Voice telephony is not configured.');
    }
}
