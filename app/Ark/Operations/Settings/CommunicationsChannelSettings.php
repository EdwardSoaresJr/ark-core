<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;
use App\Ark\Operations\Messaging\Messenger\MessengerShopConnection;

/**
 * Shop communications channel JSON projection.
 * Messenger Page credentials: prefer MessengerShopConnection (encrypted token column).
 */
final class CommunicationsChannelSettings
{
    public function __construct(
        public readonly bool $messengerEnabled,
        public readonly ?string $messengerPageId,
        public readonly ?string $messengerPageName,
        public readonly ?string $messengerPageAccessToken,
        public readonly ?MetaMessengerMessageTag $messengerOutsideWindowTag,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $connection = MessengerShopConnection::forShop($settings);

        return new self(
            messengerEnabled: $connection->isEnabled(),
            messengerPageId: $connection->pageId(),
            messengerPageName: $connection->pageName(),
            messengerPageAccessToken: $connection->pageAccessToken(),
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
                // Token is stored encrypted on shop_settings.messenger_page_access_token — never echo into JSON.
                'outside_window_tag' => $this->messengerOutsideWindowTag?->value,
            ],
        ];
    }
}
