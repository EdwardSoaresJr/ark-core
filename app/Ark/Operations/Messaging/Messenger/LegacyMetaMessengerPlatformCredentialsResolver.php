<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Log;

/**
 * Transitional bridge: when platform env is empty, reuse legacy shop-stored
 * App Secret / Verify Token only if exactly one Messenger-configured shop exists.
 *
 * Never uses ShopSettings::current() — webhooks have no trustworthy shop context.
 */
final class LegacyMetaMessengerPlatformCredentialsResolver
{
    /**
     * @return array{app_secret: ?string, verify_token: ?string}|null
     */
    public function resolve(): ?array
    {
        $configured = ShopSettings::query()
            ->get()
            ->filter(function (ShopSettings $shop): bool {
                $channels = is_array($shop->communications_channels) ? $shop->communications_channels : [];
                $messenger = is_array($channels['messenger'] ?? null) ? $channels['messenger'] : [];
                $verify = filled($messenger['verify_token'] ?? null);
                $secret = filled($shop->messenger_app_secret);

                return $verify || $secret;
            })
            ->values();

        if ($configured->count() === 0) {
            return null;
        }

        if ($configured->count() > 1) {
            Log::warning('messenger.platform.legacy_credentials_ambiguous', [
                'shop_count' => $configured->count(),
            ]);

            return null;
        }

        /** @var ShopSettings $shop */
        $shop = $configured->first();
        $channels = is_array($shop->communications_channels) ? $shop->communications_channels : [];
        $messenger = is_array($channels['messenger'] ?? null) ? $channels['messenger'] : [];

        return [
            'app_secret' => filled($shop->messenger_app_secret) ? (string) $shop->messenger_app_secret : null,
            'verify_token' => filled($messenger['verify_token'] ?? null)
                ? trim((string) $messenger['verify_token'])
                : null,
        ];
    }
}
