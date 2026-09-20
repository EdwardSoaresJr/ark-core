<?php

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function enableHostedPlatformSendWithoutCoreMirror(): void
{
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_send', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/api/v1/services/payments/')) {
            return Http::response(platformPaymentReadinessPayload(), 200);
        }

        return Http::response([
            'ok' => true,
            'message_id' => 'plat-msg-1',
            'provider_message_id' => 'SMplatform01',
            'status' => 'queued',
        ], 200);
    });
}
