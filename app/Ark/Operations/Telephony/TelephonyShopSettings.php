<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Settings\ShopSettings;

readonly class TelephonyShopSettings
{
    public function __construct(
        public TelephonyProviderType $primaryProvider = TelephonyProviderType::Twilio,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $raw = trim((string) ($settings->telephony_provider ?? ''));

        $provider = TelephonyProviderType::tryFrom($raw) ?? TelephonyProviderType::Twilio;

        if ($provider !== TelephonyProviderType::Twilio) {
            $provider = TelephonyProviderType::Twilio;
        }

        return new self(primaryProvider: $provider);
    }

    public static function primaryProviderForCurrentShop(): TelephonyProviderType
    {
        return self::fromShopSettings(ShopSettings::current())->primaryProvider;
    }

    /**
     * @return array{telephony_provider: string}
     */
    public function toArray(): array
    {
        return [
            'telephony_provider' => $this->primaryProvider->value,
        ];
    }
}
