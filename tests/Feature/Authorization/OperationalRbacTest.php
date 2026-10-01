<?php

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Route;

test('admin can access operations', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $user = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($user)
        ->get('/app')
        ->assertOk();
});

test('advisor can access operations', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($user)
        ->get('/app')
        ->assertOk();
});

test('technician can access production shell but not advisor work surface', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $user = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($user)
        ->get(route('operations.today'))
        ->assertOk();

    $this->actingAs($user)
        ->get('/app')
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('operations.communications.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('operations.customers.search'))
        ->assertForbidden();

    expect($user->can(ArkCapability::ProductionAccess->value))->toBeTrue()
        ->and($user->can(ArkCapability::OperationsAccess->value))->toBeFalse()
        ->and($user->can(ArkCapability::FinancialManage->value))->toBeFalse();
});

test('customer cannot access operations', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $user = User::factory()->create()->assignRole(ArkRole::Customer->value);

    $this->actingAs($user)
        ->get('/app')
        ->assertForbidden();
});

test('guest redirects to app login for operations', function () {
    $this->get('/app')
        ->assertRedirect(route('login'));
});

test('admin seeder assigns admin role to admin user', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@ark.test')->firstOrFail();

    expect($admin->name)->toBe('ARK Admin')
        ->and($admin->hasRole(ArkRole::Admin->value))->toBeTrue();
});

test('technician is forbidden from future financial manage routes', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    Route::middleware(['web', 'auth', 'permission:'.ArkCapability::FinancialManage->value])
        ->get('/app/test-financial-manage', fn () => 'ok');

    $user = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($user)
        ->get('/app/test-financial-manage')
        ->assertForbidden();
});

test('settings manage belongs only to admin', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    expect($admin->can(ArkCapability::SettingsManage->value))->toBeTrue()
        ->and($advisor->can(ArkCapability::SettingsManage->value))->toBeFalse()
        ->and($technician->can(ArkCapability::SettingsManage->value))->toBeFalse();
});

test('shop authority rails stay lightweight and operational', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    foreach ([
        ArkCapability::RepairOrdersCloseout,
        ArkCapability::RepairOrdersDestructive,
        ArkCapability::EstimateDocumentsManage,
        ArkCapability::PricingOverride,
        ArkCapability::ProcurementCancel,
    ] as $capability) {
        expect($admin->can($capability->value))->toBeTrue()
            ->and($advisor->can($capability->value))->toBeTrue()
            ->and($technician->can($capability->value))->toBeFalse();
    }
});
