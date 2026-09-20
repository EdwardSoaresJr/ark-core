<?php

use App\Ark\Auth\OfflineRecoveryAuthorization;
use App\Ark\Auth\OfflineRecoveryService;
use App\Ark\Install\InstallationIdentity;
use App\Ark\Install\RecoveryOwnerIdentity;
use App\Models\OfflineRecoveryChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function arkOfflineSign(string $secretKey, string $installationUuid, string $challenge, int $exp, string $jti): string
{
    $claims = [
        'challenge' => $challenge,
        'exp' => $exp,
        'installation_uuid' => $installationUuid,
        'jti' => $jti,
        'purpose' => OfflineRecoveryAuthorization::PURPOSE,
        'v' => OfflineRecoveryAuthorization::VERSION,
    ];
    ksort($claims);
    $payload = json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $signature = sodium_crypto_sign_detached($payload, $secretKey);

    return OfflineRecoveryAuthorization::PREFIX
        .'.'.OfflineRecoveryAuthorization::base64Url($payload)
        .'.'.OfflineRecoveryAuthorization::base64Url($signature);
}

beforeEach(function () {
    Http::preventStrayRequests();
    $keypair = sodium_crypto_sign_keypair();
    $this->offlineSecret = sodium_crypto_sign_secretkey($keypair);
    config([
        'services.ark_cloud.offline_recovery_public_key' => OfflineRecoveryAuthorization::base64Url(
            sodium_crypto_sign_publickey($keypair)
        ),
        'services.ark_cloud.base_url' => 'https://cloud.example.test',
    ]);
    InstallationIdentity::write((string) Str::uuid());
    RecoveryOwnerIdentity::write('owner@example.test');
    User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('old-password'),
        'is_master_admin' => true,
    ]);
});

test('offline recovery resets password locally without calling cloud', function () {
    $challenge = app(OfflineRecoveryService::class)->issueChallenge();
    $artifact = arkOfflineSign(
        $this->offlineSecret,
        InstallationIdentity::uuid(),
        $challenge->public_id,
        time() + 900,
        (string) Str::uuid(),
    );

    app(OfflineRecoveryService::class)->resetPassword($artifact, 'NewOfflinePass1!');

    $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
    expect(Hash::check('NewOfflinePass1!', $user->password))->toBeTrue();
    expect($challenge->fresh()->consumed_at)->not->toBeNull();
});

test('offline recovery rejects replay of the same authorization', function () {
    $challenge = app(OfflineRecoveryService::class)->issueChallenge();
    $artifact = arkOfflineSign(
        $this->offlineSecret,
        InstallationIdentity::uuid(),
        $challenge->public_id,
        time() + 900,
        (string) Str::uuid(),
    );

    app(OfflineRecoveryService::class)->resetPassword($artifact, 'NewOfflinePass1!');

    expect(fn () => app(OfflineRecoveryService::class)->resetPassword($artifact, 'AnotherPass1!'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('offline recovery page displays challenge without contacting cloud', function () {
    $this->get('/app/offline-recovery')
        ->assertOk()
        ->assertSee(InstallationIdentity::uuid())
        ->assertSee('https://cloud.example.test/recovery/offline')
        ->assertSee('This Box stays offline');

    expect(OfflineRecoveryChallenge::query()->count())->toBe(1);
});

test('offline recovery form resets password and rejects replay', function () {
    $this->get('/app/offline-recovery')->assertOk();
    $challenge = OfflineRecoveryChallenge::query()->latest('id')->firstOrFail();
    $artifact = arkOfflineSign(
        $this->offlineSecret,
        InstallationIdentity::uuid(),
        $challenge->public_id,
        time() + 900,
        (string) Str::uuid(),
    );

    $this->post('/app/offline-recovery', [
        'authorization' => $artifact,
        'password' => 'NewOfflinePass1!',
        'password_confirmation' => 'NewOfflinePass1!',
    ])->assertRedirect(route('login'));

    $this->post('/app/offline-recovery', [
        'authorization' => $artifact,
        'password' => 'AnotherPass1!',
        'password_confirmation' => 'AnotherPass1!',
    ])->assertSessionHasErrors('authorization');
});

test('forgot password offers the offline path', function () {
    $this->get('/app/forgot-password')
        ->assertOk()
        ->assertSee('This Box cannot reach Cloud');
});
