<?php

use App\Models\User;

test('self registration is disabled for the staff-only shop model', function () {
    $this->get('/app/register')->assertNotFound();

    $this->post('/app/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});
