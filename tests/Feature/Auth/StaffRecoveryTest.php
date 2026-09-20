<?php

use App\Ark\Auth\StaffRecoveryService;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Platform\EssentialDeliveryClient;
use App\Ark\Install\EssentialDeliverySecret;
use App\Ark\Install\RecoveryOwnerIdentity;
use App\Models\StaffRecoveryChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

test('staff recovery sends code through essential delivery without laravel reset notification', function () {
    config(['services.ark_cloud.base_url' => 'https://cloud.example.test']);
    RecoveryOwnerIdentity::write('owner@example.test');
    EssentialDeliverySecret::write('arkessential_test_secret_'.Str::random(32));

    $user = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('old-password'),
        'is_master_admin' => true,
    ]);

    Http::fake([
        'https://cloud.example.test/api/v1/essential/register' => Http::response(['ok' => true, 'status' => 'registered'], 200),
        'https://cloud.example.test/api/v1/essential/recovery-owner' => Http::response(['ok' => true], 200),
        'https://cloud.example.test/api/v1/essential/delivery/password-recovery' => Http::response(['ok' => true, 'status' => 'sent'], 200),
    ]);

    $message = app(StaffRecoveryService::class)->requestCode('owner@example.test');

    expect($message)->toContain('recovery owner email');
    expect(StaffRecoveryChallenge::query()->where('user_id', $user->id)->count())->toBe(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/essential/delivery/password-recovery')
        && ($request->data()['code'] ?? '') !== '');
});

test('staff recovery resets password locally after valid code', function () {
    RecoveryOwnerIdentity::write('owner@example.test');

    $user = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('old-password'),
    ]);

    $code = '654321';
    StaffRecoveryChallenge::query()->create([
        'public_id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'code_hash' => StaffRecoveryChallenge::hashCode($code),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(15),
    ]);

    app(StaffRecoveryService::class)->resetPassword('owner@example.test', $code, 'NewPassword1!');

    $user->refresh();
    expect(Hash::check('NewPassword1!', $user->password))->toBeTrue();
});

test('paired box uses cloud credential for essential delivery signing', function () {
    config(['services.ark_cloud.base_url' => 'https://cloud.example.test']);
    RecoveryOwnerIdentity::write('owner@example.test');

    PlatformConnection::current()->completePairing(
        'https://cloud.example.test',
        'paired-credential-secret-value-1234567890',
        'shop-public-id',
    );

    Http::fake([
        'https://cloud.example.test/api/v1/essential/recovery-owner' => Http::response(['ok' => true], 200),
        'https://cloud.example.test/api/v1/essential/delivery/password-recovery' => Http::response(['ok' => true], 200),
    ]);

    $result = app(EssentialDeliveryClient::class)->deliverPasswordRecoveryCode(
        (string) Str::uuid(),
        '111222',
        'recovery-test',
    );

    expect($result['ok'])->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/essential/delivery/password-recovery')
            && $request->hasHeader('X-Ark-Signature');
    });
});

test('forgot password flow redirects to code entry screen', function () {
    config(['services.ark_cloud.base_url' => 'https://cloud.example.test']);
    RecoveryOwnerIdentity::write('owner@example.test');
    EssentialDeliverySecret::write('arkessential_test_secret_'.Str::random(32));

    User::factory()->create(['email' => 'owner@example.test']);

    Http::fake([
        'https://cloud.example.test/*' => Http::response(['ok' => true, 'status' => 'sent'], 200),
    ]);

    $this->post('/app/forgot-password', ['email' => 'owner@example.test'])
        ->assertRedirect(route('password.reset', ['email' => 'owner@example.test']));
});
