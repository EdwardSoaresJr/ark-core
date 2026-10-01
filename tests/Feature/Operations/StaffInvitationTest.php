<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('admin inviting staff sends setup email and leaves password unset', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    usePlatformPortalMail();

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->post(route('operations.settings.staff.store'), [
            'name' => 'New Advisor',
            'email' => 'new-advisor@ark.test',
            'roles' => [ArkRole::Advisor->value],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertSessionHas('status');

    $advisor = User::query()->where('email', 'new-advisor@ark.test')->firstOrFail();

    expect($advisor->hasRole(ArkRole::Advisor->value))->toBeTrue()
        ->and($advisor->needsPasswordSetup())->toBeTrue();

    assertPlatformTransactionalMail('employee.invitation', 'new-advisor@ark.test');
    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        $url = platformMailBody($request)['variables']['setup_url'] ?? '';

        return is_string($url) && str_contains($url, 'invitation');
    });
});

test('connected shop invites staff through platform', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    usePlatformPortalMail();
    Notification::fake();

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->post(route('operations.settings.staff.store'), [
            'name' => 'Hosted Advisor',
            'email' => 'hosted-advisor@ark.test',
            'roles' => [ArkRole::Advisor->value],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    $advisor = User::query()->where('email', 'hosted-advisor@ark.test')->firstOrFail();

    Notification::assertNothingSent();
    assertPlatformTransactionalMail('employee.invitation', 'hosted-advisor@ark.test');
});

test('invited staff can use signed link to set password and reach operations', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $advisor = User::factory()
        ->awaitingInvitation()
        ->create(['email' => 'invite@ark.test'])
        ->assignRole(ArkRole::Advisor->value);

    $url = URL::temporarySignedRoute(
        'staff.invitation.accept',
        now()->addDay(),
        ['user' => $advisor->id],
    );

    $this->get($url)
        ->assertRedirect(route('account.setup'));

    $this->assertAuthenticatedAs($advisor);

    $this->get(route('operations.index'))
        ->assertRedirect(route('account.setup'));

    $this->post(route('account.setup.store'), [
        'password' => 'new-password-9',
        'password_confirmation' => 'new-password-9',
    ])->assertRedirect(route('operations.index'));

    $advisor->refresh();

    expect($advisor->hasPasswordSet())->toBeTrue();

    auth()->logout();

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => $advisor->email,
            'password' => 'new-password-9',
        ])
        ->assertRedirect(route('operations.index', absolute: false));
});

test('invited staff cannot sign in with password until setup is complete', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $advisor = User::factory()
        ->awaitingInvitation()
        ->create(['email' => 'pending@ark.test'])
        ->assignRole(ArkRole::Advisor->value);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => $advisor->email,
            'password' => 'password',
        ])
        ->assertSessionHasErrors('email');
});

test('admin can resend invitation for pending staff', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    usePlatformPortalMail();

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $advisor = User::factory()->awaitingInvitation()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($admin)
        ->post(route('operations.settings.staff.resend-invitation', $advisor))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertSessionHas('status');

    assertPlatformTransactionalMail('employee.invitation', $advisor->email);
});
