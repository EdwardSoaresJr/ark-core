<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/app/forgot-password');

    $response->assertStatus(200);
});

test('reset password screen can be rendered', function () {
    $response = $this->get('/app/reset-password?email=owner@example.test');

    $response->assertStatus(200);
});

test('legacy laravel reset notification is not used for forgot password', function () {
    Notification::fake();

    $this->post('/app/forgot-password', ['email' => 'missing@example.test']);

    Notification::assertNothingSent();
});
