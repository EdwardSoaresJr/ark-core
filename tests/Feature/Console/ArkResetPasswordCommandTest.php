<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

test('a staff password can be reset from the server without mail', function (): void {
    Mail::fake();
    Notification::fake();
    $user = User::factory()->create(['email' => 'owner@shop.test', 'remember_token' => 'old-token']);

    $this->artisan('ark:reset-password', ['email' => 'owner@shop.test'])
        ->expectsQuestion('New password', 'Correct-Horse-9-Battery')
        ->expectsQuestion('Repeat the new password', 'Correct-Horse-9-Battery')
        ->expectsOutputToContain('Password updated for owner@shop.test')
        ->assertSuccessful();

    $user->refresh();
    expect(Hash::check('Correct-Horse-9-Battery', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-token')
        ->and($user->password_set_at)->not->toBeNull();
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

test('a mismatched confirmation leaves the password unchanged', function (): void {
    $user = User::factory()->create(['email' => 'owner@shop.test']);
    $before = $user->password;

    $this->artisan('ark:reset-password', ['email' => 'owner@shop.test'])
        ->expectsQuestion('New password', 'Correct-Horse-9-Battery')
        ->expectsQuestion('Repeat the new password', 'Something-Else-9')
        ->assertFailed();

    expect($user->refresh()->password)->toBe($before);
});

test('an unknown email is refused', function (): void {
    $this->artisan('ark:reset-password', ['email' => 'nobody@shop.test'])
        ->expectsOutputToContain('No staff account uses that email.')
        ->assertFailed();
});
