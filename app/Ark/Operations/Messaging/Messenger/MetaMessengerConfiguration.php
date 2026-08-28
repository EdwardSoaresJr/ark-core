<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Composed Messenger runtime config for a shop Page + platform App.
 *
 * Prefer readiness checks on platform / shop connection / channel connection
 * instead of the legacy overloaded isConfigured().
 */
final class MetaMessengerConfiguration
{
    public function __construct(
        private readonly MetaMessengerPlatformConfiguration $platform,
        private readonly MessengerShopConnection $shopConnection,
    ) {}

    public static function current(): self
    {
        return self::forShop(ShopSettings::current());
    }

    public static function forShop(ShopSettings $settings): self
    {
        return new self(
            MetaMessengerPlatformConfiguration::current(),
            MessengerShopConnection::forShop($settings),
        );
    }

    public static function forShopConnection(MessengerShopConnection $shopConnection): self
    {
        return new self(
            MetaMessengerPlatformConfiguration::current(),
            $shopConnection,
        );
    }

    public function platform(): MetaMessengerPlatformConfiguration
    {
        return $this->platform;
    }

    public function shopConnection(): MessengerShopConnection
    {
        return $this->shopConnection;
    }

    public function isEnabled(): bool
    {
        return $this->shopConnection->isEnabled();
    }

    /**
     * @deprecated Prefer platform()->isConfigured(), shopConnection()->isConfigured(), channel isOperational().
     */
    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && $this->shopConnection->isConfigured()
            && $this->platform->isConfigured();
    }

    public function pageId(): ?string
    {
        return $this->shopConnection->pageId();
    }

    public function pageAccessToken(): ?string
    {
        return $this->shopConnection->pageAccessToken();
    }

    public function verifyToken(): ?string
    {
        return $this->platform->verifyToken();
    }

    public function appSecret(): ?string
    {
        return $this->platform->appSecret();
    }

    public function outsideWindowTag(): ?MetaMessengerMessageTag
    {
        return $this->shopConnection->outsideWindowTag();
    }

    public function webhookUrl(): string
    {
        return $this->platform->webhookUrl();
    }

    public function graphVersion(): string
    {
        return $this->platform->graphVersion();
    }
}
