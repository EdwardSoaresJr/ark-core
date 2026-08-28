<?php

namespace App\Ark\Operations\Messaging\Messenger;

/**
 * Messenger Channel Connection projection — first channel of the Connect/Health shape.
 * Not a new authority store.
 */
final class MessengerChannelConnection
{
    public function __construct(
        private readonly MetaMessengerPlatformConfiguration $platform,
        private readonly MessengerShopConnection $shopConnection,
        private readonly MessengerHealth $health,
    ) {}

    public static function forCurrentShop(): self
    {
        $shop = MessengerShopConnection::current();

        return new self(
            MetaMessengerPlatformConfiguration::current(),
            $shop,
            MessengerHealth::forShopConnection($shop),
        );
    }

    public static function forShopConnection(MessengerShopConnection $shopConnection): self
    {
        return new self(
            MetaMessengerPlatformConfiguration::current(),
            $shopConnection,
            MessengerHealth::forShopConnection($shopConnection),
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

    public function health(): MessengerHealth
    {
        return $this->health;
    }

    public function isOperational(): bool
    {
        return $this->shopConnection->isEnabled()
            && $this->shopConnection->isConfigured()
            && $this->platform->isConfigured();
    }

    public function statusLabel(): string
    {
        if (! $this->shopConnection->isEnabled()) {
            return 'Disabled';
        }

        if (! $this->platform->isConfigured()) {
            return 'Platform credentials missing';
        }

        if (! $this->shopConnection->isConfigured()) {
            return 'Page connection incomplete';
        }

        if ($this->health->lastWebhookAt() === null) {
            return 'Webhook not yet observed';
        }

        return 'Connected';
    }

    public function statusTone(): string
    {
        return match ($this->statusLabel()) {
            'Connected' => 'success',
            'Webhook not yet observed' => 'warning',
            'Disabled' => 'muted',
            default => 'danger',
        };
    }
}
