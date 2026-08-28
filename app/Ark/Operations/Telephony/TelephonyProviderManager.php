<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\Contracts\TelephonyProvider;
use App\Ark\Operations\Telephony\Providers\TwilioTelephonyProvider;
use InvalidArgumentException;

class TelephonyProviderManager
{
    public function current(): TelephonyProvider
    {
        return app(TwilioTelephonyProvider::class);
    }

    public function currentType(): TelephonyProviderType
    {
        return TelephonyProviderType::Twilio;
    }

    public function resolve(TelephonyProviderType $type): TelephonyProvider
    {
        return match ($type) {
            TelephonyProviderType::Twilio => app(TwilioTelephonyProvider::class),
            TelephonyProviderType::Fake => app(TwilioTelephonyProvider::class),
            default => app(TwilioTelephonyProvider::class),
        };
    }

    /**
     * Twilio HTTP webhooks always parse Twilio payloads — independent of shop primary provider.
     */
    public function twilio(): TwilioTelephonyProvider
    {
        $provider = $this->resolve(TelephonyProviderType::Twilio);

        if (! $provider instanceof TwilioTelephonyProvider) {
            throw new InvalidArgumentException('Twilio telephony provider is not registered.');
        }

        return $provider;
    }
}
