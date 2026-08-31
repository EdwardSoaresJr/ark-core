<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit', ['tab' => 'profile']));

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('profile appearance can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.appearance.update'), [
            'accent_theme' => 'ark2',
            'display_theme' => 'system',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit', ['tab' => 'appearance']));

    $user->refresh();

    $this->assertSame('ark2', $user->accent_theme);
    $this->assertSame('system', $user->display_theme);
});

test('profile accent theme must be a supported value', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.appearance.update'), [
            'accent_theme' => 'chartreuse',
            'display_theme' => 'system',
        ])
        ->assertSessionHasErrors('accent_theme');
});

test('profile custom accent color can be saved', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.appearance.update'), [
            'accent_theme' => 'custom',
            'accent_color' => '#12abef',
            'display_theme' => 'system',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit', ['tab' => 'appearance']));

    $user->refresh();

    expect($user->accent_theme)->toBe('custom')
        ->and($user->accent_color)->toBe('#12abef');
});

test('preset accent clears stored custom color', function () {
    $user = User::factory()->create([
        'accent_theme' => 'custom',
        'accent_color' => '#12abef',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.appearance.update'), [
            'accent_theme' => 'ark2',
            'display_theme' => 'system',
            'accent_color' => '#12abef',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->accent_theme)->toBe('ark2')
        ->and($user->accent_color)->toBeNull();
});

test('custom accent requires a valid hex color', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.appearance.update'), [
            'accent_theme' => 'custom',
            'accent_color' => 'not-a-color',
            'display_theme' => 'system',
        ])
        ->assertSessionHasErrors('accent_color');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit', ['tab' => 'profile']));

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('users cannot delete their own account from profile settings', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response->assertMethodNotAllowed();

    $this->assertNotNull($user->fresh());
});
