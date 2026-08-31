<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;
use App\Ark\Operations\Messaging\Messenger\MessengerShopConnection;

/**
 * Shop communications channel JSON projection.
 */
final class CommunicationsChannelSettings
{
    public function __construct(
        public readonly bool $messengerEnabled,
        public readonly ?string $messengerPageId,
        public readonly ?string $messengerPageName,
        public readonly ?MetaMessengerMessageTag $messengerOutsideWindowTag,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $connection = MessengerShopConnection::forShop($settings);

        return new self(
            messengerEnabled: $connection->isEnabled(),
            messengerPageId: $connection->pageId(),
            messengerPageName: $connection->pageName(),
            messengerOutsideWindowTag: $connection->outsideWindowTag(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'messenger' => [
                'enabled' => $this->messengerEnabled,
                'page_id' => $this->messengerPageId,
                'page_name' => $this->messengerPageName,
                'outside_window_tag' => $this->messengerOutsideWindowTag?->value,
            ],
        ];
    }
}
