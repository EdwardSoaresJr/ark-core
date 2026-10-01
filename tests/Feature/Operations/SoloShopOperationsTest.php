<?php

use App\Ark\Operations\Staff\SoloShopOperations;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('solo owner shop is detected when no active advisor staff exist', function () {
    $soloShop = new SoloShopOperations;

    User::factory()->create()->assignRole(ArkRole::Admin->value);

    expect($soloShop->isSoloOwnerShop())->toBeTrue()
        ->and($soloShop->requiresTechnicianAssignment())->toBeFalse();
});

test('shops with advisor staff require technician assignment', function () {
    $soloShop = new SoloShopOperations;

    User::factory()->create()->assignRole(ArkRole::Advisor->value);

    expect($soloShop->isSoloOwnerShop())->toBeFalse()
        ->and($soloShop->requiresTechnicianAssignment())->toBeTrue();
});

test('solo owner shop can assign active admin users as technician', function () {
    $soloShop = new SoloShopOperations;
    $owner = User::factory()->create()->assignRole(ArkRole::Admin->value);

    expect($soloShop->canAssignAsTechnician($owner))->toBeTrue()
        ->and($soloShop->assignableTechnicians()->pluck('id'))->toContain($owner->id);
});

test('multi advisor shop rejects admin technician assignment', function () {
    $soloShop = new SoloShopOperations;
    $owner = User::factory()->create()->assignRole(ArkRole::Admin->value);
    User::factory()->create()->assignRole(ArkRole::Advisor->value);

    expect($soloShop->canAssignAsTechnician($owner))->toBeFalse();
});

test('sole staff user is detected when exactly one active staff member exists', function () {
    $soloShop = new SoloShopOperations;
    $owner = User::factory()->create()->assignRole(ArkRole::Admin->value);

    expect($soloShop->isSingleUserShop())->toBeTrue()
        ->and($soloShop->soleStaffUser()?->id)->toBe($owner->id);
});

test('sole staff user is null once a second staff member exists', function () {
    $soloShop = new SoloShopOperations;
    User::factory()->create()->assignRole(ArkRole::Admin->value);
    User::factory()->create()->assignRole(ArkRole::Technician->value);

    expect($soloShop->isSingleUserShop())->toBeFalse()
        ->and($soloShop->soleStaffUser())->toBeNull();
});

test('inactive and customer accounts do not count toward the sole staff user', function () {
    $soloShop = new SoloShopOperations;
    $owner = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    User::factory()->create(['is_active' => false])->assignRole(ArkRole::Technician->value);
    User::factory()->create()->assignRole(ArkRole::Customer->value);

    expect($soloShop->isSingleUserShop())->toBeTrue()
        ->and($soloShop->soleStaffUser()?->id)->toBe($owner->id);
});

test('the sole staff member is assignable as technician regardless of role', function () {
    $soloShop = new SoloShopOperations;
    // Advisor-only solo: not a technician, and not an owner-only shop, yet still
    // the only person who can do the work.
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    expect($soloShop->canAssignAsTechnician($advisor))->toBeTrue();
});
