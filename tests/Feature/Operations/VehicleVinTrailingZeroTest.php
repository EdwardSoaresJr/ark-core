<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

test('vehicle store persists vin column when vin ends in zero', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = Customer::query()->create([
        'first_name' => 'Vin',
        'last_name' => 'Zero',
        'phone' => '7195550400',
    ]);

    $vin = '4S3BP616X76430010';

    $this->post(route('operations.customers.vehicles.store', $customer), [
        'vin' => $vin,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ])->assertRedirect(route('operations.customers.show', $customer));

    expect(Vehicle::query()->firstOrFail())
        ->vin->toBe($vin)
        ->normalized_vin->toBe($vin);
});

test('vehicle update json accepts numeric vin and keeps trailing zero', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = Customer::query()->create([
        'first_name' => 'Numeric',
        'last_name' => 'Vin',
        'phone' => '7195550401',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2012,
        'make' => 'Honda',
        'model' => 'Pilot',
    ]);

    $vin = '2T3RFREV6KW020202';

    $this->patchJson(route('operations.customers.vehicles.update', [$customer, $vehicle]), [
        'year' => 2012,
        'make' => 'Honda',
        'model' => 'Pilot',
        'vin' => $vin,
        'plate' => 'ZERO20',
        'plate_state' => 'CO',
    ])
        ->assertOk()
        ->assertJsonPath('vehicle.lines', fn ($lines) => collect($lines)->contains(
            fn ($line) => $line['label'] === 'VIN' && $line['value'] === $vin,
        ));

    $vehicle->refresh();

    expect($vehicle->vin)->toBe($vin)
        ->and($vehicle->normalized_vin)->toBe($vin);
});
