<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Voice\ManagedVoiceGate;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function enableHostedPlatformMail(?string $shopPublicId = null): string
{
    $installationUuid = (string) Str::uuid();
    InstallationIdentity::write($installationUuid);

    config()->set('services.ark_platform.mail_send', true);

    $shopPublicId ??= (string) Str::uuid();

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-mail-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => $shopPublicId,
        'ark_mail_from_email' => 'shop@example.test',
        'ark_mail_status' => 'connected',
    ]);

    Http::fake([
        'cloud.test/api/v1/status' => Http::response([
            'ok' => true,
            'services' => [
                ['key' => 'voice', 'label' => 'ARK Voice', 'status' => 'not_enabled', 'status_label' => 'Not enabled', 'detail' => null],
            ],
        ], 200),
    ]);

    ManagedVoiceGate::resetMemo();

    return $installationUuid;
}

function fakeHostedPlatformMail(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/api/v1/services/mail/messages/transactional')) {
            return Http::response([
                'ok' => true,
                'status' => 'provider_sent',
                'message_id' => 'mail-test-1',
            ], 200);
        }

        return Http::response(['unexpected' => true, 'url' => $url], 599);
    });
}
