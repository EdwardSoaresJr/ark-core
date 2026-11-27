<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/app/forgot-password');

    $response->assertOk();
    $response->assertSee('Email password reset link', false);
    $response->assertDontSee('offline-recovery', false);
});

test('reset password link can be requested for an active staff account', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'owner@example.test',
    ]);

    $response = $this->post('/app/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertSessionHas(
        'status',
        'If that email belongs to an active staff account, we sent a password reset link.',
    );

    Notification::assertSentTo($user, ResetPassword::class);
});

test('staff password reset delivers through process MAIL_* without provider SDK mailers', function () {
    config(['mail.default' => 'array']);
    Mail::purge('array');
    Mail::mailer('array');

    $user = User::factory()->create([
        'email' => 'advisor@example.test',
    ]);

    $this->post('/app/forgot-password', [
        'email' => $user->email,
    ])->assertSessionHas('status');

    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->not->toBeEmpty();

    $email = $messages[0]->getOriginalMessage();
    $body = $email->getHtmlBody() ?: $email->getTextBody();

    expect($email->getTo()[0]->getAddress())->toBe('advisor@example.test')
        ->and($email->getSubject())->toContain('Reset your password')
        ->and((string) $body)->toContain('/app/reset-password/');
});

test('staff password reset resolves the smtp mailer name to laravel smtp not ark-platform', function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 2525,
    ]);

    expect((string) Mail::mailer()->getSymfonyTransport())->toContain('smtp')
        ->and((string) Mail::mailer()->getSymfonyTransport())->not->toBe('ark-platform://platform');
});

test('reset password notification link uses the current request host', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'advisor@shop-alpha.test',
    ]);

    $this->post('http://shop-alpha.test/app/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, '://shop-alpha.test/')
            && str_contains($url, '/app/reset-password/')
            && str_contains($url, 'email='.urlencode($user->email));
    });
});

test('forgot password does not enumerate missing accounts', function () {
    Notification::fake();

    $response = $this->post('/app/forgot-password', [
        'email' => 'missing@example.test',
    ]);

    $response->assertSessionHas(
        'status',
        'If that email belongs to an active staff account, we sent a password reset link.',
    );

    Notification::assertNothingSent();
});

test('reset password screen can be rendered', function () {
    $user = User::factory()->create();

    $token = Password::broker()->createToken($user);

    $response = $this->get('/app/reset-password/'.$token.'?email='.urlencode($user->email));

    $response->assertOk();
    $response->assertSee('Choose a new password', false);
});

test('password can be reset with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('old-password'),
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->post('/app/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-secure',
        'password_confirmation' => 'new-password-secure',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'Password updated. Sign in with your new password.');

    $user->refresh();

    expect(Hash::check('new-password-secure', $user->password))->toBeTrue();
    expect($user->password_set_at)->not->toBeNull();
});

test('password cannot be reset with an invalid token', function () {
    $user = User::factory()->create([
        'email' => 'owner@example.test',
    ]);

    $response = $this->from('/app/reset-password/bad-token')->post('/app/reset-password', [
        'token' => 'bad-token',
        'email' => $user->email,
        'password' => 'new-password-secure',
        'password_confirmation' => 'new-password-secure',
    ]);

    $response->assertSessionHasErrors('email');
});
