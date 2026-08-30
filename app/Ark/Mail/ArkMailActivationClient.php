<?php

namespace App\Ark\Mail;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dev/local activation against ARK Mail allow_dev_activation.
 * Production account entitlement is intentionally not faked.
 */
final class ArkMailActivationClient
{
    public function activate(?string $serviceUrl = null): array
    {
        $settings = ShopSettings::current();
        $base = rtrim((string) ($serviceUrl ?: $settings->ark_mail_service_url ?: config('services.ark_mail.base_url')), '/');

        if ($base === '') {
            throw new \RuntimeException('ARK Mail service URL is not configured.');
        }

        if (! config('services.ark_mail.allow_activation', false) && app()->environment('production')) {
            throw new \RuntimeException('ARK Mail activation is not available yet for this installation.');
        }

        $replyTo = $settings->postmark_reply_to ?: $settings->email;
        if (! filled($replyTo) || ! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Set your Shop Profile email (or Reply-To) before enabling ARK Mail.');
        }

        $payload = [
            'installation_uuid' => InstallationIdentity::uuid(),
            'shop_display_name' => $settings->shop_name ?: config('app.name', 'ARK'),
            'reply_to_email' => strtolower((string) $replyTo),
            'reply_to_name' => $settings->postmark_reply_to_name ?: $settings->shop_name,
        ];

        $response = Http::acceptJson()->timeout(20)->post($base.'/api/v1/activate', $payload);

        if (! $response->successful() || ! ($response->json('ok') ?? false)) {
            $message = $response->json('message') ?? 'ARK Mail activation failed.';
            Log::warning('ark_mail.activation_failed', [
                'status' => $response->status(),
                // never log credential
            ]);
            throw new \RuntimeException(is_string($message) ? $message : 'ARK Mail activation failed.');
        }

        $credential = $response->json('credential');
        if (! is_string($credential) || $credential === '') {
            throw new \RuntimeException('ARK Mail activation did not return a credential.');
        }

        $settings->persistTrusted([
            'ark_mail_service_url' => $base,
            'ark_mail_tenant_public_id' => $response->json('tenant_public_id'),
            'ark_mail_from_email' => $response->json('from_email'),
            'ark_mail_credential' => $credential,
            'ark_mail_status' => 'connected',
            'ark_mail_connected_at' => now(),
            // Prefer shop reply-to aligned with activation
            'postmark_reply_to' => $payload['reply_to_email'],
        ]);

        Log::info('ark_mail.activated', [
            'tenant_public_id' => $response->json('tenant_public_id'),
            'installation_uuid' => $payload['installation_uuid'],
            // never log credential
        ]);

        return [
            'tenant_public_id' => $response->json('tenant_public_id'),
            'from_email' => $response->json('from_email'),
            'reply_to' => $response->json('reply_to'),
            'status' => 'connected',
        ];
    }

    public function disconnect(): void
    {
        ShopSettings::current()->persistTrusted([
            'ark_mail_credential' => null,
            'ark_mail_tenant_public_id' => null,
            'ark_mail_from_email' => null,
            'ark_mail_status' => null,
            'ark_mail_connected_at' => null,
        ]);
    }
}
