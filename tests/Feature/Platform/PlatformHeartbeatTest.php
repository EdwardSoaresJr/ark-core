<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\ArkBoxHeartbeatClient;
use App\Ark\Platform\BoxRuntimeObservation;
use Illuminate\Foundation\Application;
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
            && ($payload['laravel_version'] ?? null) === Application::VERSION
            && ($payload['php_version'] ?? null) === PHP_VERSION
            && ! array_key_exists('image_digest', $payload);
    });
});

test('box heartbeat command is a no-op when platform is not connected', function (): void {
    Http::fake();

    $this->artisan('ark:platform-heartbeat')->assertSuccessful();

    Http::assertNothingSent();
});

test('box heartbeat includes a valid image digest', function (): void {
    $installationUuid = (string) Str::uuid();
    InstallationIdentity::write($installationUuid);
    $digest = 'sha256:'.str_repeat('cd', 32);
    config(['app.image_digest' => $digest]);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-heartbeat-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => (string) Str::uuid(),
    ]);

    Http::fake([
        'cloud.test/api/v1/box/heartbeat' => Http::response(['ok' => true, 'health' => 'online'], 200),
    ]);

    expect(app(ArkBoxHeartbeatClient::class)->send()['ok'])->toBeTrue();

    Http::assertSent(function ($request) use ($digest): bool {
        return ($request->data()['image_digest'] ?? null) === $digest;
    });
});

test('box runtime observation reports configured identity', function (): void {
    expect(BoxRuntimeObservation::payload())->toMatchArray([
        'core_version' => '2026.9.1',
        'release' => 'production',
        'commit' => 'abc123def456abc123def456abc123def456ab',
        'laravel_version' => Application::VERSION,
        'php_version' => PHP_VERSION,
        'image_digest' => null,
    ]);
});

test('box runtime observation reads source commit file when config is empty', function (): void {
    config([
        'app.version' => null,
        'app.release' => null,
        'app.commit' => null,
    ]);

    $path = base_path('.ark-source-commit');
    file_put_contents($path, "deadbeefdeadbeefdeadbeefdeadbeefdeadbeef\n");

    try {
        expect(BoxRuntimeObservation::payload())->toMatchArray([
            'core_version' => 'deadbeefdead',
            'release' => null,
            'commit' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
            'laravel_version' => Application::VERSION,
            'php_version' => PHP_VERSION,
            'image_digest' => null,
        ]);
    } finally {
        @unlink($path);
    }
});

test('box runtime observation prefers the source commit file over APP_COMMIT', function (): void {
    config(['app.commit' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);

    $path = base_path('.ark-source-commit');
    file_put_contents($path, "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb\n");

    try {
        expect(BoxRuntimeObservation::payload()['commit'])->toBe('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
    } finally {
        @unlink($path);
    }
});

test('box runtime observation accepts a sha256 image digest and rejects a git sha', function (): void {
    $digest = 'sha256:'.str_repeat('ab', 32);
    config(['app.image_digest' => $digest]);
    expect(BoxRuntimeObservation::payload()['image_digest'])->toBe($digest);

    config(['app.image_digest' => 'ghcr.io/edwardsoaresjr/ark-core@'.$digest]);
    expect(BoxRuntimeObservation::payload()['image_digest'])->toBe($digest);

    config(['app.image_digest' => '13acaa0060e64233b72b1eb62b68cc6869bf1c11']);
    expect(BoxRuntimeObservation::payload()['image_digest'])->toBeNull();
});

test('box runtime observation ignores unknown placeholder commit', function (): void {
    config([
        'app.version' => null,
        'app.release' => null,
        'app.commit' => 'unknown',
    ]);

    expect(BoxRuntimeObservation::payload()['core_version'])->toBe('dev')
        ->and(BoxRuntimeObservation::payload()['commit'])->toBeNull();
});

test('platform heartbeat is scheduled every five minutes', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('ark:platform-heartbeat')
        ->expectsOutputToContain('*/5 * * * *')
        ->assertSuccessful();
});

test('public core publish identity is ark-core', function (): void {
    $script = (string) file_get_contents(base_path('scripts/assert-canonical-core-publish.sh'));

    expect($script)->toContain('ghcr.io/edwardsoaresjr/ark-core')
        ->and($script)->not->toContain('ghcr.io/edwardsoaresjr/ark"');
});
