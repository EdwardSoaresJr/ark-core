<?php

use App\Ark\Install\CompleteInstallationAction;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['auth.require_email_verification' => true]);
});

it('marks the installer-created administrator email as verified', function () {
    $action = app(CompleteInstallationAction::class);
    $method = new \ReflectionMethod(CompleteInstallationAction::class, 'ensureAdministrator');
    $method->setAccessible(true);

    /** @var User $admin */
    $admin = $method->invoke($action, [
        'name' => 'Shop Owner',
        'email' => 'owner@example.test',
        'password' => 'secure-password-123',
    ]);

    expect($admin->email_verified_at)->not->toBeNull()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->hasRole(ArkRole::Admin->value))->toBeTrue();
});

it('lets the installer-created administrator past the verification notice without mail', function () {
    $action = app(CompleteInstallationAction::class);
    $method = new \ReflectionMethod(CompleteInstallationAction::class, 'ensureAdministrator');
    $method->setAccessible(true);

    /** @var User $admin */
    $admin = $method->invoke($action, [
        'name' => 'Shop Owner',
        'email' => 'owner@example.test',
        'password' => 'secure-password-123',
    ]);

    $this->actingAs($admin->fresh())
        ->get('/app/verify-email')
        ->assertRedirect();
});

it('presents ARK branding on the verification notice for unverified users', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/app/verify-email')
        ->assertOk()
        ->assertSee('ARK', false)
        ->assertSee('Verify your email', false)
        ->assertDontSee('ARK-SMS', false)
        ->assertDontSee('AUTO REPAIR KEEPER', false);
});
