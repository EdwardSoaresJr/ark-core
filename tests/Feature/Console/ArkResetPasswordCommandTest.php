<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
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

test('the password is only ever read from a hidden prompt, never from arguments or options', function (): void {
    $definition = Artisan::all()['ark:reset-password']->getDefinition();

    expect(array_keys($definition->getArguments()))->toBe(['email'])
        ->and(array_filter(array_keys($definition->getOptions()), fn (string $name) => str_contains($name, 'pass')))->toBe([]);
});

test('a non-interactive run changes nothing', function (): void {
    $user = User::factory()->create(['email' => 'owner@shop.test']);
    $before = $user->password;

    expect(Artisan::call('ark:reset-password', ['email' => 'owner@shop.test', '--no-interaction' => true]))->toBe(1)
        ->and($user->refresh()->password)->toBe($before)
        ->and(Artisan::output())->not->toContain($before);
});

test('an unknown email is refused', function (): void {
    $this->artisan('ark:reset-password', ['email' => 'nobody@shop.test'])
        ->expectsOutputToContain('No staff account uses that email.')
        ->assertFailed();
});
