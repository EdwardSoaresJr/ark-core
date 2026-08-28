<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\TelephonyHealth;
use App\Ark\Platform\ShopBaseUrl;
use Illuminate\Http\JsonResponse;

final class VoiceCapabilityHealthController
{
    public function __invoke(): JsonResponse
    {
        $telephony = TelephonyHealth::forCurrentShop();

        return response()->json([
            'shop' => ShopBaseUrl::origin(),
            'listener' => $telephony->webhookLabel(),
            'listener_enabled' => $telephony->credentialsConfigured(),
            'ingress_token_configured' => $telephony->credentialsConfigured(),
            'last_asterisk_webhook_at' => $telephony->lastWebhookAt()?->toIso8601String(),
        ]);
    }
}
