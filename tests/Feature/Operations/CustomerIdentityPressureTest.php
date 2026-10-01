<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerIdentityPressure;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('customer with phone email and address is complete', function () {
    $customer = customerIdentityFixture([
        'phone' => '7195551000',
        'email' => 'complete@example.test',
        'address_line_1' => '123 Main St',
        'city' => 'Demo City',
        'postal_code' => '80903',
    ]);

    expect($customer->identityPressure())->toBe(CustomerIdentityPressure::Complete)
        ->and($customer->missingIdentityFieldLabels())->toBe([]);
});

test('customer missing phone and email is critical', function () {
    $customer = customerIdentityFixture([
        'address_line_1' => '123 Main St',
        'city' => 'Demo City',
        'postal_code' => '80903',
    ]);

    expect($customer->identityPressure())->toBe(CustomerIdentityPressure::Critical)
        ->and($customer->missingIdentityFieldLabels())->toBe(['Phone', 'Email']);
});

test('customer with phone but missing email and address is incomplete', function () {
    $customer = customerIdentityFixture([
        'phone' => '7195551001',
    ]);

    expect($customer->identityPressure())->toBe(CustomerIdentityPressure::Incomplete)
        ->and($customer->missingIdentityFieldLabels())->toBe(['Email', 'Address']);
});

test('repair order workspace shows customer info pressure without blocking intake', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = customerIdentityFixture([
        'phone' => '7195551002',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'CUST01',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Noise on acceleration',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Customer info incomplete', false)
        ->assertSee('Missing: Email, Address', false);
});

test('intake open step shows customer information needed list', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = customerIdentityFixture([
        'phone' => '7195551003',
    ]);

    $this->actingAs($advisor)
        ->followingRedirects()
        ->get(route('operations.intake.create', ['customer_id' => $customer->id]))
        ->assertOk()
        ->assertSee('Customer information needed', false)
        ->assertSee('Missing Email', false)
        ->assertSee('Missing Address', false);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function customerIdentityFixture(array $attributes = []): Customer
{
    return Customer::query()->create([
        'first_name' => 'Customer',
        'last_name' => 'Identity',
        ...$attributes,
    ]);
}
