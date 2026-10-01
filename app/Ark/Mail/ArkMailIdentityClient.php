<?php

namespace App\Ark\Mail;

use App\Ark\Platform\PlatformSignedRequest;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushes shop-owned Reply-To to ARK Platform Mail.
 */
final class ArkMailIdentityClient
{
    public function syncShopReplyTo(): bool
    {
        $cloud = PlatformConnection::current();
        if (! $cloud->isConnected() || ! filled($cloud->credential())) {
            return false;
        }

        $settings = ShopSettings::current();
        $replyTo = $settings->postmark_reply_to ?: $settings->email;
        if (! filled($replyTo)) {
            Log::warning('ark_mail.identity.missing_reply_to');

            return false;
        }

        $base = rtrim((string) $cloud->baseUrl(), '/');
        $path = '/api/v1/services/mail/identity';
        $payload = [
            'reply_to_email' => strtolower(trim((string) $replyTo)),
            'reply_to_name' => filled($settings->postmark_reply_to_name)
                ? trim((string) $settings->postmark_reply_to_name)
                : (string) ($settings->shop_name ?: config('app.name', 'ARK')),
            'shop_display_name' => (string) ($settings->shop_name ?: config('app.name', 'ARK')),
        ];

        try {
            $raw = json_encode($payload, JSON_THROW_ON_ERROR);
            $headers = PlatformSignedRequest::headers('PUT', $path, $raw, (string) $cloud->credential());
            $response = Http::withHeaders($headers)
                ->timeout(12)
                ->withBody($raw, 'application/json')
                ->put($base.$path);
        } catch (\Throwable $e) {
            Log::warning('ark_mail.identity.sync_unavailable', ['error' => $e->getMessage()]);

            return false;
        }

        $json = $response->json();
        if ($response->successful() && is_array($json) && ($json['ok'] ?? false) === true) {
            return true;
        }

        Log::warning('ark_mail.identity.sync_rejected', [
            'status' => $response->status(),
            'reason' => is_array($json) ? ($json['reason_code'] ?? null) : null,
        ]);

        return false;
    }
}
