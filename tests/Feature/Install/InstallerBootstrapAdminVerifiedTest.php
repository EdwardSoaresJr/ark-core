<?php

use App\Ark\Install\CompleteInstallationAction;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Preferences\DisplayTheme;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Notification;

// Database refresh comes from tests/Pest.php (LazilyRefreshDatabase for Feature/).

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['auth.require_email_verification' => true]);
});

function invokeEnsureAdministrator(array $admin): User
{
    $action = app(CompleteInstallationAction::class);
    $method = new \ReflectionMethod(CompleteInstallationAction::class, 'ensureAdministrator');
    $method->setAccessible(true);

    return $method->invoke($action, $admin);
}

it('marks the installer-created administrator email as verified', function () {
    $admin = invokeEnsureAdministrator([
        'name' => 'Shop Owner',
        'email' => 'owner@example.test',
        'password' => 'secure-password-123',
    ]);

    expect($admin->email_verified_at)->not->toBeNull()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->hasRole(ArkRole::Admin->value))->toBeTrue();
});

it('lets the installer-created administrator past the verification notice without mail', function () {
    Notification::fake();

    $admin = invokeEnsureAdministrator([
        'name' => 'Shop Owner',
        'email' => 'owner@example.test',
        'password' => 'secure-password-123',
    ]);

    $this->actingAs($admin->fresh())
        ->get('/app/verify-email')
        ->assertRedirect();

    Notification::assertNothingSent();
});

it('gives the installer-created administrator the light appearance default', function () {
    expect(DisplayTheme::default())->toBe(DisplayTheme::Light);

    $admin = invokeEnsureAdministrator([
        'name' => 'Shop Owner',
        'email' => 'owner@example.test',
        'password' => 'secure-password-123',
    ]);

    expect($admin->display_theme)->toBe(DisplayTheme::Light->value)
        ->and($admin->displayTheme())->toBe(DisplayTheme::Light)
        ->and($admin->displayTheme()->resolvesToDark(true))->toBeFalse();
});

it('does not auto-verify later staff accounts created outside the installer', function () {
    Notification::fake();

    $bootstrap = invokeEnsureAdministrator([
        'name' => 'Shop Owner',
        'email' => 'owner@example.test',
        'password' => 'secure-password-123',
    ]);

    $this->actingAs($bootstrap)
        ->post(route('operations.settings.staff.store'), [
            'name' => 'New Advisor',
            'email' => 'advisor@example.test',
            'roles' => [ArkRole::Advisor->value],
        ])
        ->assertRedirect();

    $advisor = User::query()->where('email', 'advisor@example.test')->firstOrFail();

    expect($advisor->email_verified_at)->toBeNull()
        ->and($advisor->hasVerifiedEmail())->toBeFalse();
});

it('preserves explicit existing appearance preferences', function () {
    foreach ([DisplayTheme::Dark, DisplayTheme::Light, DisplayTheme::System] as $theme) {
        $user = User::factory()->create(['display_theme' => $theme->value]);

        expect($user->fresh()->displayTheme())->toBe($theme);
    }

    expect(DisplayTheme::tryFromStored('dark'))->toBe(DisplayTheme::Dark)
        ->and(DisplayTheme::tryFromStored('system'))->toBe(DisplayTheme::System)
        ->and(DisplayTheme::tryFromStored(null))->toBe(DisplayTheme::Light);
});
