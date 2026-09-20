<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Voice\ManagedVoiceGate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function enablePlatformConnection(?string $shopPublicId = null): string
{
    $installationUuid = (string) Str::uuid();
    InstallationIdentity::write($installationUuid);

    $shopPublicId ??= (string) Str::uuid();

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-voice-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => $shopPublicId,
    ]);

    ManagedVoiceGate::resetMemo();

    return $installationUuid;
}

function fakePlatformVoiceStatus(string $voiceStatus, ?string $voiceStatusLabel = null): void
{
    ManagedVoiceGate::resetMemo();

    Http::fake([
        'cloud.test/api/v1/status' => Http::response([
            'ok' => true,
            'services' => [
                ['key' => 'connect', 'label' => 'ARK Connect', 'status' => 'active', 'status_label' => 'Active', 'detail' => null],
                ['key' => 'mail', 'label' => 'ARK Email', 'status' => 'active', 'status_label' => 'Active', 'detail' => null],
                [
                    'key' => 'voice',
                    'label' => 'ARK Voice',
                    'status' => $voiceStatus,
                    'status_label' => $voiceStatusLabel ?? ($voiceStatus === 'active' ? 'Active' : 'Not enabled'),
                    'detail' => null,
                ],
            ],
        ], 200),
    ]);
}

function fakePlatformStatusUnavailable(): void
{
    ManagedVoiceGate::resetMemo();

    Http::fake([
        'cloud.test/api/v1/status' => Http::response(['ok' => false], 503),
    ]);
}
