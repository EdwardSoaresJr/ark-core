<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Log;

final class MessengerShopPageResolver
{
    public function resolveByPageId(?string $pageId): ?ShopSettings
    {
        $pageId = trim((string) $pageId);

        if ($pageId === '') {
            return null;
        }

        $byColumn = ShopSettings::query()
            ->where('messenger_page_id', $pageId)
            ->first();

        if ($byColumn !== null) {
            return $byColumn;
        }

        // Compat: JSON page_id before column backfill / write-through.
        foreach (ShopSettings::query()->whereNotNull('communications_channels')->cursor() as $shop) {
            $channels = is_array($shop->communications_channels) ? $shop->communications_channels : [];
            $jsonPageId = trim((string) data_get($channels, 'messenger.page_id', ''));

            if ($jsonPageId !== '' && hash_equals($jsonPageId, $pageId)) {
                return $shop;
            }
        }

        return null;
    }

    public function logUnknownPage(string $pageId): void
    {
        Log::info('messenger.webhook.unknown_page', [
            'page_id' => $pageId,
        ]);
    }
}
