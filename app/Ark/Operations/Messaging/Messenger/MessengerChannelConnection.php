<?php

namespace App\Ark\Operations\Messaging\Messenger;

/**
 * Messenger channel connection projection — transport not bundled in Core.
 */
final class MessengerChannelConnection
{
    public function __construct(
        private readonly MessengerShopConnection $shopConnection,
        private readonly MessengerHealth $health,
    ) {}

    public static function forCurrentShop(): self
    {
        $shop = MessengerShopConnection::current();

        return new self(
            $shop,
            MessengerHealth::forShopConnection($shop),
        );
    }

    public static function forShopConnection(MessengerShopConnection $shopConnection): self
    {
        return new self(
            $shopConnection,
            MessengerHealth::forShopConnection($shopConnection),
        );
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
        return false;
    }

    public function statusLabel(): string
    {
        if (! $this->shopConnection->isEnabled()) {
            return 'Disabled';
        }

        return 'Not configured';
    }

    public function statusTone(): string
    {
        return match ($this->statusLabel()) {
            'Disabled' => 'muted',
            default => 'warning',
        };
    }
}
