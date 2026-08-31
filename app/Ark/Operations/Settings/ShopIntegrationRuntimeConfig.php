<?php

namespace App\Ark\Operations\Settings;

use Throwable;

/**
 * Applies shop-stored reply-to into runtime config.
 * Does not inject Postmark tokens — official production mail is ARK Mail only.
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

        $replyTo = $credentials->mailReplyTo();

        if ($replyTo !== null) {
            config(['mail.reply_to.address' => $replyTo]);
        }

        $replyToName = $credentials->mailReplyToName();

        if ($replyToName !== null) {
            config(['mail.reply_to.name' => $replyToName]);
        }
    }
}
