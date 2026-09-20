<?php

use App\Ark\Platform\EssentialDeliveryClient;
use App\Ark\Install\EssentialDeliverySecret;
use App\Ark\Install\InstallationIdentity;
use App\Ark\Install\InstallationState;
use App\Ark\Install\RecoveryOwnerIdentity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    InstallationState::resetForTests();
    RecoveryOwnerIdentity::write('owner@example.test');
    InstallationIdentity::write((string) Str::uuid());
    $secretPath = EssentialDeliverySecret::path();
    if (is_file($secretPath)) {
        unlink($secretPath);
    }
    config(['services.ark_cloud.base_url' => 'https://cloud.example.test']);
});

afterEach(function () {
    InstallationState::resetForTests();
    $secretPath = EssentialDeliverySecret::path();
    if (is_file($secretPath)) {
        unlink($secretPath);
    }
});

test('essential registration is available after install but install itself does not require it', function () {
    Http::fake([
        'https://cloud.example.test/api/v1/essential/register' => Http::response(['ok' => true, 'status' => 'registered'], 200),
    ]);

    $client = app(EssentialDeliveryClient::class);

    $client->registerAtInstall();
    Http::assertNothingSent();

    InstallationState::markInstalled();
    // Explicit connect path may call registerAtInstall — still gated on installed.
    $client->registerAtInstall();

    Http::assertSent(fn ($request) => $request->url() === 'https://cloud.example.test/api/v1/essential/register'
        && ($request['installation_uuid'] ?? null) === InstallationIdentity::uuid()
        && ($request['recovery_owner_email'] ?? null) === 'owner@example.test');

    expect(is_file(EssentialDeliverySecret::path()))->toBeTrue();
});

test('standalone install completion does not phone home for essential registration', function () {
    Http::fake();

    InstallationState::markInstalled();
    // Simulates CompleteInstallationAction after Set up later — no Essential call.
    Http::assertNothingSent();
    expect(is_file(EssentialDeliverySecret::path()))->toBeFalse();
});
