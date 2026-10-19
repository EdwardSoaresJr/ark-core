<?php

use App\Models\User;

test('self registration is disabled for the staff-only shop model', function () {
    $this->get('/app/register')->assertNotFound();

    // The public site fallback is GET-only, so an unknown POST is rejected
    // before a missing page can return 404. Registration is still unavailable.
    $this->post('/app/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertMethodNotAllowed();

    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});
