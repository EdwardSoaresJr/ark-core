<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\ArkBoxHeartbeatClient;
use App\Ark\Platform\BoxRuntimeObservation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'services.ark_platform.base_url' => 'https://cloud.test',
        'app.version' => '2026.9.1',
        'app.release' => 'production',
        'app.commit' => 'abc123def456abc123def456abc123def456ab',
    ]);
});

test('box heartbeat posts signed observation when platform is connected', function (): void {
    $installationUuid = (string) Str::uuid();
    InstallationIdentity::write($installationUuid);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-heartbeat-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => (string) Str::uuid(),
    ]);

    Http::fake([
        'cloud.test/api/v1/box/heartbeat' => Http::response([
            'ok' => true,
            'health' => 'online',
            'reported_version' => '2026.9.1',
        ], 200),
    ]);

    $result = app(ArkBoxHeartbeatClient::class)->send();

    expect($result['ok'])->toBeTrue()
        ->and($result['health'])->toBe('online');

    Http::assertSent(function ($request) use ($installationUuid): bool {
        $payload = $request->data();

        return $request->url() === 'https://cloud.test/api/v1/box/heartbeat'
            && $request->method() === 'POST'
            && $request->hasHeader('X-Ark-Installation-Id', $installationUuid)
            && $request->hasHeader('X-Ark-Signature')
            && ($payload['core_version'] ?? null) === '2026.9.1'
            && ($payload['release'] ?? null) === 'production'
            && ($payload['commit'] ?? null) === 'abc123def456abc123def456abc123def456ab'
            && ($payload['php_version'] ?? null) === PHP_VERSION;
    });
});

test('box heartbeat command is a no-op when platform is not connected', function (): void {
    Http::fake();

    $this->artisan('ark:platform-heartbeat')->assertSuccessful();

    Http::assertNothingSent();
});

test('box runtime observation reports configured identity', function (): void {
    expect(BoxRuntimeObservation::payload())->toMatchArray([
        'core_version' => '2026.9.1',
        'release' => 'production',
        'commit' => 'abc123def456abc123def456abc123def456ab',
        'php_version' => PHP_VERSION,
    ]);
});
