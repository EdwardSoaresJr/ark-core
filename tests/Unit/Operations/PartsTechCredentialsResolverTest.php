<?php

use App\Ark\Operations\Parts\PartsTechCredentialsResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('parts tech credentials resolver prefers user profile over shop default', function () {
    config()->set('services.partstech.username', 'shop-user');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $user = User::factory()->create([
        'partstech_username' => 'ben-advisor',
        'partstech_password' => 'ben-password',
    ]);

    $credentials = app(PartsTechCredentialsResolver::class)->forUser($user);

    expect($credentials->username)->toBe('ben-advisor')
        ->and($credentials->password)->toBe('ben-password')
        ->and($credentials->usesPersonalLogin())->toBeTrue()
        ->and($credentials->sessionIdentity)->toBe('user:'.$user->id);
});

test('parts tech credentials resolver falls back to shop default', function () {
    config()->set('services.partstech.username', 'shop-user');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $user = User::factory()->create();

    $credentials = app(PartsTechCredentialsResolver::class)->forUser($user);

    expect($credentials->username)->toBe('shop-user')
        ->and($credentials->password)->toBe('shop-password')
        ->and($credentials->usesPersonalLogin())->toBeFalse()
        ->and($credentials->sessionIdentity)->toBe('shop');
});
