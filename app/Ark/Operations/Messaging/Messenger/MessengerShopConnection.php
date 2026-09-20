<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Shop Messenger channel settings — no bundled Meta transport in Core.
 */
final class MessengerShopConnection
{
    public function __construct(
        private readonly ShopSettings $settings,
        private readonly bool $enabled,
        private readonly ?string $pageId,
        private readonly ?string $pageName,
        private readonly ?MetaMessengerMessageTag $outsideWindowTag,
    ) {}

    public static function forShop(ShopSettings $settings): self
    {
        $channels = is_array($settings->communications_channels) ? $settings->communications_channels : [];
        $messenger = is_array($channels['messenger'] ?? null) ? $channels['messenger'] : [];

        $pageId = filled($settings->messenger_page_id)
            ? trim((string) $settings->messenger_page_id)
            : (filled($messenger['page_id'] ?? null) ? trim((string) $messenger['page_id']) : null);

        return new self(
            settings: $settings,
            enabled: (bool) ($messenger['enabled'] ?? false),
            pageId: $pageId,
            pageName: filled($messenger['page_name'] ?? null) ? trim((string) $messenger['page_name']) : null,
            outsideWindowTag: MetaMessengerMessageTag::tryFrom((string) ($messenger['outside_window_tag'] ?? '')),
        );
    }

    public static function current(): self
    {
        return self::forShop(ShopSettings::current());
    }

    public function shop(): ShopSettings
    {
        return $this->settings;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function pageId(): ?string
    {
        return $this->pageId;
    }

    public function pageName(): ?string
    {
        return $this->pageName;
    }

    public function outsideWindowTag(): ?MetaMessengerMessageTag
    {
        return $this->outsideWindowTag;
    }

    public function maskedPageId(): ?string
    {
        if (! filled($this->pageId)) {
            return null;
        }

        $id = (string) $this->pageId;

        if (strlen($id) <= 6) {
            return str_repeat('•', strlen($id));
        }

        return str_repeat('•', max(0, strlen($id) - 4)).substr($id, -4);
    }
}
