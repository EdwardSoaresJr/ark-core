<?php

use App\Ark\Install\InstallationState;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Authorization\DevRolePretend;
use App\Http\Middleware\ApplyDevRolePretend;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

function runAsInstalledProductionShop(): void
{
    app()['env'] = 'production';
    InstallationState::markInstalled();
    test()->withoutMiddleware(PreventRequestForgery::class);
}

test('master admin can pretend to be technician and restore admin access', function () {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    DevRolePretend::activateTechnician();
    DevRolePretend::applyEffectiveRoles($admin);

    expect($admin->hasRole(ArkRole::Technician->value))->toBeTrue()
        ->and($admin->hasRole(ArkRole::Admin->value))->toBeFalse()
        ->and($admin->can(ArkCapability::ProductionAccess->value))->toBeTrue()
        ->and($admin->can(ArkCapability::OperationsAccess->value))->toBeFalse()
        ->and($admin->can(ArkCapability::SettingsManage->value))->toBeFalse()
        ->and($admin->can(ArkCapability::RepairOrdersLifecycle->value))->toBeTrue()
        ->and($admin->isMasterAdmin())->toBeFalse();

    DevRolePretend::clear();
    $admin = $admin->fresh();

    expect($admin->hasRole(ArkRole::Admin->value))->toBeTrue()
        ->and($admin->can(ArkCapability::SettingsManage->value))->toBeTrue()
        ->and($admin->isMasterAdmin())->toBeTrue();
});

test('master admin can toggle role pretend from the topbar routes', function () {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->post(route('dev-role-pretend.technician'))
        ->assertRedirect(route('operations.today'))
        ->assertSessionHas('dev_role_pretend', ArkRole::Technician->value);

    $this->post(route('dev-role-pretend.clear'))
        ->assertRedirect(route('operations.today'))
        ->assertSessionMissing('dev_role_pretend');
});

test('technician pretend from Work redirects to today instead of forbidden Work', function () {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('operations.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('dev-role-pretend.technician'))
        ->assertRedirect(route('operations.today'));

    $this->actingAs($admin)
        ->withSession(['dev_role_pretend' => ArkRole::Technician->value])
        ->get(route('operations.today'))
        ->assertOk();
});

test('non master admin cannot use dev role pretend routes', function () {
    $admin = User::factory()->create(['is_master_admin' => false])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->post(route('dev-role-pretend.technician'))
        ->assertForbidden();
});

test('master admin sees role pretend switcher on operations layout outside production', function () {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    expect(app()->environment('production'))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Test as technician', false)
        ->assertSee(route('dev-role-pretend.technician'), false);
});

test('production hides the test as technician control and refuses to start it', function () {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    runAsInstalledProductionShop();

    $this->actingAs($admin)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Test as technician', false)
        ->assertDontSee(route('dev-role-pretend.technician'), false);

    $this->actingAs($admin)
        ->post(route('dev-role-pretend.technician'))
        ->assertForbidden()
        ->assertSessionMissing('dev_role_pretend');
});

test('production keeps the way out visible and working while pretend is active', function () {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    runAsInstalledProductionShop();

    $this->actingAs($admin)
        ->withSession(['dev_role_pretend' => ArkRole::Technician->value])
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Return to admin access', false)
        ->assertSee(route('dev-role-pretend.clear'), false)
        ->assertDontSee('Test as technician', false);

    $this->post(route('dev-role-pretend.clear'))
        ->assertRedirect(route('operations.today'))
        ->assertSessionMissing('dev_role_pretend');

    expect($admin->fresh()->hasRole(ArkRole::Admin->value))->toBeTrue();
});

test('any user stuck in pretend mode can stop it even without pretend access', function () {
    $admin = User::factory()->create(['is_master_admin' => false])->assignRole(ArkRole::Admin->value);
    runAsInstalledProductionShop();

    $this->actingAs($admin)
        ->withSession(['dev_role_pretend' => ArkRole::Technician->value])
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Return to admin access', false);

    $this->post(route('dev-role-pretend.clear'))
        ->assertRedirect(route('operations.today'))
        ->assertSessionMissing('dev_role_pretend');
});

test('stopping pretend without an active pretend still requires pretend access', function () {
    $admin = User::factory()->create(['is_master_admin' => false])->assignRole(ArkRole::Admin->value);
    runAsInstalledProductionShop();

    $this->actingAs($admin)
        ->post(route('dev-role-pretend.clear'))
        ->assertForbidden();
});

test('shop settings tell a pretending admin how to return to admin access', function () {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    $message = 'Choose "Return to admin access" in the account menu before using shop settings.';

    $this->actingAs($admin)
        ->withSession(['dev_role_pretend' => ArkRole::Technician->value])
        ->get(route('operations.settings.shop.edit'))
        ->assertRedirect(route('operations.today'))
        ->assertSessionHas('status', $message);

    $this->actingAs($admin)
        ->withSession(['dev_role_pretend' => ArkRole::Technician->value])
        ->patchJson(route('operations.settings.shop.general.update'), ['shop_name' => 'Pretend'])
        ->assertForbidden()
        ->assertJson(['message' => $message]);
});

test('pretending technician uses production shell instead of advisor work surface', function () {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession(['dev_role_pretend' => ArkRole::Technician->value])
        ->get(route('operations.today'))
        ->assertOk();

    $this->actingAs($admin)
        ->withSession(['dev_role_pretend' => ArkRole::Technician->value])
        ->get(route('operations.index'))
        ->assertForbidden();
});

test('pretending technician hides new intake affordance', function () {
    Route::middleware(['web', 'auth', ApplyDevRolePretend::class, 'permission:'.ArkCapability::RepairOrdersManage->value])
        ->get('/app/test-intake-gate', fn () => 'ok');

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession(['dev_role_pretend' => ArkRole::Technician->value])
        ->get('/app/test-intake-gate')
        ->assertForbidden();
});
