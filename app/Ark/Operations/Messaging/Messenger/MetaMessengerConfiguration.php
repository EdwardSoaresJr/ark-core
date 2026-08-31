<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Messenger channel projection for Core — transport is not bundled.
 */
final class MetaMessengerConfiguration
{
    public function __construct(
        private readonly MessengerShopConnection $shopConnection,
    ) {}

    public static function current(): self
    {
        return self::forShop(ShopSettings::current());
    }

    public static function forShop(ShopSettings $settings): self
    {
        return new self(MessengerShopConnection::forShop($settings));
    }

    public function shopConnection(): MessengerShopConnection
    {
        return $this->shopConnection;
    }

    public function isEnabled(): bool
    {
        return $this->shopConnection->isEnabled();
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function outsideWindowTag(): ?MetaMessengerMessageTag
    {
        return $this->shopConnection->outsideWindowTag();
    }
}
