<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/app/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email verification is skipped when disabled by config', function () {
    config(['auth.require_email_verification' => false]);

    $user = User::factory()->unverified()->create();

    expect(User::emailVerificationIsRequired())->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect();

    $this->actingAs($user)
        ->get('/app/verify-email')
        ->assertRedirect();
});

test('email verification is skipped on demo app hosts', function () {
    config([
        'auth.require_email_verification' => null,
        'app.url' => 'https://demo.autorepairkeeper.com',
    ]);

    $user = User::factory()->unverified()->create();

    expect(User::emailVerificationIsRequired())->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});
