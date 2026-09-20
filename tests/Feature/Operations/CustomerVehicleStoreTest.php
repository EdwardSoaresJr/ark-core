<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Vehicles\VehicleIdentityInput;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('vehicle store requires a vin plate or year make model identity', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Sam',
        'last_name' => 'Nguyen',
    ]);

    $this->post(route('operations.customers.vehicles.store', $customer), [])
        ->assertSessionHasErrors('vin');

    expect(Vehicle::query()->where('customer_id', $customer->id)->count())->toBe(0);

    $this->post(route('operations.customers.vehicles.store', $customer), [
        'plate' => 'ABC123',
    ])->assertRedirect(route('operations.customers.show', $customer));

    expect(Vehicle::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('vehicle identity input accepts vin plate or year make model', function () {
    expect(VehicleIdentityInput::hasMinimumIdentity([]))->toBeFalse()
        ->and(VehicleIdentityInput::hasMinimumIdentity(['color' => 'Blue']))->toBeFalse()
        ->and(VehicleIdentityInput::hasMinimumIdentity(['year' => 2020, 'make' => 'Honda']))->toBeFalse()
        ->and(VehicleIdentityInput::hasMinimumIdentity(['vin' => '1HGCM82633A004352']))->toBeTrue()
        ->and(VehicleIdentityInput::hasMinimumIdentity(['plate' => 'ARK123']))->toBeTrue()
        ->and(VehicleIdentityInput::hasMinimumIdentity([
            'year' => 2020,
            'make' => 'Honda',
            'model' => 'Accord',
        ]))->toBeTrue();
});
