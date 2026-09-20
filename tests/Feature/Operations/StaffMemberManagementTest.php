<?php

use App\Ark\Operations\Labor\TechnicianLaborPayBasis;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Notification;

test('admin can manage staff members', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Notification::fake();

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertOk()
        ->assertSee('Team logins and roles')
        ->assertSee('Invite member');

    $this->actingAs($admin)
        ->get(route('operations.staff.index'))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    $this->actingAs($admin)
        ->post(route('operations.settings.staff.store'), [
            'name' => 'New Advisor',
            'email' => 'new-advisor@ark.test',
            'phone' => '719-555-1212',
            'roles' => [ArkRole::Advisor->value],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertSessionHas('status');

    $advisor = User::query()->where('email', 'new-advisor@ark.test')->firstOrFail();

    expect($advisor->hasRole(ArkRole::Advisor->value))->toBeTrue()
        ->and($advisor->phone)->toBe('7195551212')
        ->and($advisor->display_phone)->toBe('(719) 555-1212');

    Notification::assertSentTo($advisor, StaffInvitationNotification::class);

    $this->actingAs($admin)
        ->patch(route('operations.settings.staff.update', $advisor), [
            'name' => 'Renamed Advisor',
            'email' => 'renamed-advisor@ark.test',
            'password' => '',
            'roles' => [ArkRole::Technician->value],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    $advisor->refresh();

    expect($advisor->name)->toBe('Renamed Advisor')
        ->and($advisor->email)->toBe('renamed-advisor@ark.test')
        ->and($advisor->hasRole(ArkRole::Technician->value))->toBeTrue();
});

test('advisor cannot access staff management', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    // Unauthorized settings access redirects to the operations home (not a 403 page).
    $this->actingAs($advisor)
        ->get(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertRedirect(url('/app'));

    $this->actingAs($advisor)
        ->post(route('operations.settings.staff.store'), [
            'name' => 'Blocked',
            'email' => 'blocked@ark.test',
            'roles' => [ArkRole::Advisor->value],
        ])
        ->assertRedirect(url('/app'));
});

test('staff management cannot remove the last admin', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->from(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->patch(route('operations.settings.staff.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [ArkRole::Advisor->value],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertSessionHasErrors('roles');

    expect($admin->fresh()->hasRole(ArkRole::Admin->value))->toBeTrue();
});

test('master admin cannot lose admin role even when another admin exists', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $masterAdmin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    $otherAdmin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($otherAdmin)
        ->from(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->patch(route('operations.settings.staff.update', $masterAdmin), [
            'name' => $masterAdmin->name,
            'email' => $masterAdmin->email,
            'roles' => [ArkRole::Advisor->value],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertSessionHasErrors('roles');

    expect($masterAdmin->fresh()->hasRole(ArkRole::Admin->value))->toBeTrue();
});

test('updating non technician staff preserves labor pay basis column', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $advisor = User::factory()->create([
        'labor_pay_basis' => TechnicianLaborPayBasis::Hourly->value,
    ])->assignRole(ArkRole::Advisor->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.staff.update', $advisor), [
            'name' => 'Updated Advisor',
            'email' => $advisor->email,
            'roles' => [ArkRole::Advisor->value],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    expect($advisor->fresh())
        ->name->toBe('Updated Advisor')
        ->labor_pay_basis->toBe(TechnicianLaborPayBasis::Hourly->value);
});

test('admin can set technician flag pay basis and loaded labor cost', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $technician = User::factory()->create([
        'labor_pay_basis' => TechnicianLaborPayBasis::Hourly->value,
        'labor_cost_cents' => null,
    ])->assignRole(ArkRole::Technician->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.staff.update', $technician), [
            'name' => $technician->name,
            'email' => $technician->email,
            'roles' => [ArkRole::Technician->value],
            'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
            'flag_rate' => '30.00',
            'floor_rate' => '15.16',
            'labor_cost' => '42.50',
            'workday_hours' => '9',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    expect($technician->fresh())
        ->labor_pay_basis->toBe(TechnicianLaborPayBasis::Flag->value)
        ->flag_rate_cents->toBe(3000)
        ->floor_rate_cents->toBe(1516)
        ->labor_cost_cents->toBe(4250)
        ->and((float) $technician->fresh()->workday_hours)->toBe(9.0);
});

test('flag and floor compensation agreement survives hourly pay basis toggle', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $technician = User::factory()->create([
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 3000,
        'floor_rate_cents' => 1516,
        'labor_cost_cents' => 4340,
    ])->assignRole(ArkRole::Technician->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.staff.update', $technician), [
            'name' => $technician->name,
            'email' => $technician->email,
            'roles' => [ArkRole::Technician->value],
            'labor_pay_basis' => TechnicianLaborPayBasis::Hourly->value,
            'labor_cost' => '50.00',
            // flag_rate / floor_rate omitted — Hourly UI hides them
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    expect($technician->fresh())
        ->labor_pay_basis->toBe(TechnicianLaborPayBasis::Hourly->value)
        ->flag_rate_cents->toBe(3000)
        ->floor_rate_cents->toBe(1516)
        ->labor_cost_cents->toBe(5000);

    $this->actingAs($admin)
        ->patch(route('operations.settings.staff.update', $technician), [
            'name' => $technician->name,
            'email' => $technician->email,
            'roles' => [ArkRole::Technician->value],
            'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
            'flag_rate' => '30.00',
            'floor_rate' => '15.16',
            'labor_cost' => '43.40',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    expect($technician->fresh())
        ->labor_pay_basis->toBe(TechnicianLaborPayBasis::Flag->value)
        ->flag_rate_cents->toBe(3000)
        ->floor_rate_cents->toBe(1516);
});

test('new flag technician seeds floor from suggestion without binding to future config changes', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Notification::fake();

    config(['technician_compensation.floor_wage_suggestion.amount_cents' => 1516]);

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->post(route('operations.settings.staff.store'), [
            'name' => 'Flag Tech',
            'email' => 'flag-tech@ark.test',
            'roles' => [ArkRole::Technician->value],
            'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
            'flag_rate' => '30.00',
            // floor_rate omitted — seed from suggestion
            'labor_cost' => '43.40',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    $tech = User::query()->where('email', 'flag-tech@ark.test')->firstOrFail();

    expect($tech)
        ->flag_rate_cents->toBe(3000)
        ->floor_rate_cents->toBe(1516)
        ->labor_cost_cents->toBe(4340);

    config(['technician_compensation.floor_wage_suggestion.amount_cents' => 1575]);

    expect($tech->fresh()->floor_rate_cents)->toBe(1516)
        ->and($tech->fresh()->floorWageNeedsReview())->toBeTrue();
});

test('non master admin can lose admin role when another admin remains', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $masterAdmin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    $otherAdmin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($masterAdmin)
        ->patch(route('operations.settings.staff.update', $otherAdmin), [
            'name' => $otherAdmin->name,
            'email' => $otherAdmin->email,
            'roles' => [ArkRole::Advisor->value],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    expect($otherAdmin->fresh()->hasRole(ArkRole::Admin->value))->toBeFalse()
        ->and($otherAdmin->fresh()->hasRole(ArkRole::Advisor->value))->toBeTrue();
});

test('admin can disable and re-enable staff without deleting them', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($admin)
        ->from(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->patch(route('operations.settings.staff.deactivate', $advisor))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertSessionHas('status');

    expect($advisor->fresh()->is_active)->toBeFalse();

    auth()->logout();

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => $advisor->email,
            'password' => 'password',
        ])
        ->assertSessionHasErrors('email');

    $this->actingAs($admin)
        ->patch(route('operations.settings.staff.activate', $advisor))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    expect($advisor->fresh()->is_active)->toBeTrue();
});

test('admin cannot disable the last active admin or their own account', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->from(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->patch(route('operations.settings.staff.deactivate', $admin))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']))
        ->assertSessionHasErrors('staff');

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('staff manage capability belongs only to admin', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    expect($admin->can(ArkCapability::StaffManage->value))->toBeTrue()
        ->and($advisor->can(ArkCapability::StaffManage->value))->toBeFalse();
});
