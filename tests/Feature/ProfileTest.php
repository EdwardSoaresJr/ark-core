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

test('user can save personal parts tech credentials on profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.partstech.update'), [
            'partstech_username' => 'ben-advisor',
            'partstech_password' => 'ben-partstech',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit', ['tab' => 'partstech']))
        ->assertSessionHas('status', 'partstech-updated');

    $user->refresh();

    expect($user->partstech_username)->toBe('ben-advisor')
        ->and($user->partstech_password)->toBe('ben-partstech')
        ->and($user->usesPersonalPartsTechLogin())->toBeTrue();
});

test('profile parts tech username requires password when none stored', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit', ['tab' => 'partstech']))
        ->patch(route('profile.partstech.update'), [
            'partstech_username' => 'ben-advisor',
        ])
        ->assertRedirect(route('profile.edit', ['tab' => 'partstech']))
        ->assertSessionHasErrors('partstech_password');
});

test('user can clear personal parts tech login from profile', function () {
    $user = User::factory()->create([
        'partstech_username' => 'ben-advisor',
        'partstech_password' => 'ben-partstech',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.partstech.update'), [
            'partstech_username' => '',
            'partstech_password' => '',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->partstech_username)->toBeNull()
        ->and($user->partstech_password)->toBeNull()
        ->and($user->usesPersonalPartsTechLogin())->toBeFalse();
});
