<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('vehicle show uses the shared Tabler shell and identity', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Hale',
        'phone' => '3035550142',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Subaru',
        'model' => 'Outback',
        'trim' => '2.5i Premium',
        'vin' => '4S4BSAFC5J3XXXXXX',
        'plate' => '7KMT42',
        'plate_state' => 'CO',
    ]);

    $this->actingAs($user)
        ->get(route('operations.vehicles.show', $vehicle))
        ->assertOk()
        ->assertSee('data-ark-topbar-global', false)
        ->assertSee('data-bs-toggle="sidebar-folded"', false)
        ->assertSee('data-ops-global-search-open', false)
        ->assertSee('Jordan Hale', false)
        ->assertSee('2018 Subaru Outback', false)
        ->assertSee('Create RO', false)
        ->assertSee('Current work', false)
        ->assertSee('Service history', false)
        ->assertSee('vehicle:'.$vehicle->id, false);
});

test('vehicle show requires vehicles.manage', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $customer = Customer::query()->create([
        'first_name' => 'No',
        'last_name' => 'Access',
        'phone' => '3035550199',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $this->actingAs($user)
        ->get(route('operations.vehicles.show', $vehicle))
        ->assertForbidden();
});
