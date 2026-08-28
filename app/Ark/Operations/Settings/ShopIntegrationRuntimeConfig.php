<?php

namespace App\Ark\Operations\Settings;

use Throwable;

/**
 * Merges shop-stored integration credentials into runtime config so mail transports
 * and legacy config('services.*') reads stay authoritative without config:cache churn.
 */
final class ShopIntegrationRuntimeConfig
{
    public static function apply(): void
    {
        try {
            $credentials = ShopIntegrationCredentials::forCurrentShop();
        } catch (Throwable) {
            return;
        }

        $postmarkToken = $credentials->postmarkToken();

        if ($postmarkToken !== null) {
            config(['services.postmark.token' => $postmarkToken]);
        }

        $replyTo = $credentials->postmarkReplyTo();

        if ($replyTo !== null) {
            config(['mail.reply_to.address' => $replyTo]);
        }

        $replyToName = $credentials->postmarkReplyToName();

        if ($replyToName !== null) {
            config(['mail.reply_to.name' => $replyToName]);
        }

        config([
            'services.partstech.base_url' => $credentials->partsTechBaseUrl(),
            'services.partstech.catalog_path' => $credentials->partsTechCatalogPath(),
            'services.partstech.username' => $credentials->partsTechUsername(),
            'services.partstech.api_key' => $credentials->partsTechApiKey(),
            'services.partstech.password' => $credentials->partsTechPassword(),
        ]);
    }
}
