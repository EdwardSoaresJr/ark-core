<?php

use App\Ark\Install\InstallationState;
use App\Ark\Mail\ArkMailActivationClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('lets a completed installation start Cloud pairing without a Core rollout flag', function () {
    $this->app['env'] = 'production';

    config([
        'services.ark_platform.base_url' => 'https://cloud.example.test',
        'services.ark_cloud.base_url' => 'https://cloud.example.test',
    ]);

    $pairingPublicId = (string) Str::uuid();

    Http::fake([
        'cloud.example.test/api/v1/pairing/start' => Http::response([
            'ok' => true,
            'pairing_code' => 'WXYZ9876',
            'public_id' => $pairingPublicId,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ], 200),
    ]);

    $started = app(ArkMailActivationClient::class)->activate();

    expect($started['pairing_code'])->toBe('WXYZ9876')
        ->and($started['pairing_public_id'])->toBe($pairingPublicId)
        ->and($started['status'])->toBe('pairing');

    Http::assertSentCount(1);
});

it('rejects Cloud pairing start when Core installation is not complete', function () {
    config([
        'services.ark_platform.base_url' => 'https://cloud.example.test',
        'services.ark_cloud.base_url' => 'https://cloud.example.test',
    ]);

    Http::fake();

    InstallationState::resetForTests();

    expect(InstallationState::isInstalled())->toBeFalse();

    expect(fn () => app(ArkMailActivationClient::class)->activate())
        ->toThrow(RuntimeException::class, 'Finish installing ARK before connecting to ARK Platform.');

    Http::assertNothingSent();
});
