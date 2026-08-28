<?php

use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('owner workspace access matches active admin role holders', function () {
    $admin = User::factory()->create()->assignRole([
        ArkRole::Admin->value,
        ArkRole::Advisor->value,
        ArkRole::Technician->value,
    ]);

    expect(OwnerWorkspaceAccess::allows($admin))->toBeTrue()
        ->and($admin->canAccessOwnerWorkspace())->toBeTrue();
});

test('owner workspace access rejects advisors and inactive admins', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $inactiveAdmin = User::factory()->inactive()->create()->assignRole(ArkRole::Admin->value);

    expect(OwnerWorkspaceAccess::allows($advisor))->toBeFalse()
        ->and(OwnerWorkspaceAccess::allows($inactiveAdmin))->toBeFalse();
});
