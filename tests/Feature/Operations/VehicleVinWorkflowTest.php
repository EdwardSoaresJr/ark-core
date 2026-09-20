<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Vehicles\VehicleIdentityPressure;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
        
    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
});

test('intake can create vehicle without vin when plate is present', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'No',
        'last_name' => 'Vin',
        'phone' => '7195554400',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.customers.vehicles.store', $customer), [
            'plate' => 'N0VIN01',
            'year' => 2014,
            'make' => 'Jeep',
            'model' => 'Wrangler',
            'intake' => true,
        ])
        ->assertRedirect();

    expect(Vehicle::query()->where('plate', 'N0VIN01')->first())
        ->not->toBeNull()
        ->vin->toBeNull();
});

test('vin supplied during intake lands on vehicles vin', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'With',
        'last_name' => 'Vin',
        'phone' => '7195554401',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.customers.vehicles.store', $customer), [
            'vin' => '1C4HJXDG6EW123456',
            'year' => 2014,
            'make' => 'Jeep',
            'model' => 'Wrangler',
        ])
        ->assertRedirect();

    expect(Vehicle::query()->where('customer_id', $customer->id)->first())
        ->not->toBeNull()
        ->vin->toBe('1C4HJXDG6EW123456');
});

test('vehicle identity pressure is missing without vin on file', function () {
    $vehicle = Vehicle::query()->create([
        'customer_id' => Customer::query()->create([
            'first_name' => 'Pressure',
            'last_name' => 'Check',
            'phone' => '7195554410',
        ])->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'PRES01',
    ]);

    expect($vehicle->hasVin())->toBeFalse()
        ->and($vehicle->identityPressure())->toBe(VehicleIdentityPressure::NoVin)
        ->and($vehicle->identityPressure()->showsChip())->toBeTrue();
});

test('vehicle identity pressure is decoded after vin decode metadata exists', function () {
    $vehicle = Vehicle::query()->create([
        'customer_id' => Customer::query()->create([
            'first_name' => 'Decoded',
            'last_name' => 'Vin',
            'phone' => '7195554411',
        ])->id,
        'vin' => '1C4HJXDG6EW123456',
        'normalized_vin' => '1C4HJXDG6EW123456',
        'normalized_vehicle_key' => 'jeep-wrangler-2014',
        'vehicle_identity_built_at' => now(),
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
    ]);

    expect($vehicle->identityPressure())->toBe(VehicleIdentityPressure::VinDecoded)
        ->and($vehicle->identityPressure()->showsChip())->toBeFalse();
});

test('diagnostic repair order can be created without vin', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Diag',
        'last_name' => 'Visit',
        'phone' => '7195554402',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'DIAG01',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.customers.repair-orders.drafts.store', $customer), [
            'vehicle_id' => $vehicle->id,
            'concern_summary' => 'Check engine light — no VIN yet.',
        ])
        ->assertRedirect();

    $repairOrder = RepairOrder::query()->firstOrFail();

    expect($repairOrder->vehicle_id)->toBe($vehicle->id)
        ->and($repairOrder->status->is(RepairOrderStatus::Draft))->toBeTrue()
        ->and($repairOrder->vehicleIdentityPressure()->showsChip())->toBeTrue();
});

test('repair order workspace shows vin missing pressure and reads vin from vehicle', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = vinWorkflowRepairOrder(vin: null);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('VIN missing', false);

    $repairOrder->vehicle->update(['vin' => '1C4HJXDG6EW123456', 'normalized_vin' => '1C4HJXDG6EW123456']);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('1C4HJXDG6EW123456')
        ->assertDontSee('VIN missing');
});

test('send estimate blocks when vehicle vin is missing', function () {
    bindFakeOutboundSms();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = vinWorkflowRepairOrder(vin: null);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertStatus(422)
        ->assertJsonPath('message', VehicleIdentityPressure::NoVin->estimateSendBlockedMessage());
});


test('send estimate succeeds after vin is added to vehicle record', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMallowed', 'status' => 'queued'], 201),
    ]);
    bindFakeOutboundSms();

    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = vinWorkflowRepairOrder(vin: null);
    $repairOrder->vehicle->update(['vin' => '1C4HJXDG6EW123456', 'normalized_vin' => '1C4HJXDG6EW123456']);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder->fresh(['vehicle'])))
        ->assertOk();
});

test('updating vehicle vin updates repair order identity display', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = vinWorkflowRepairOrder(vin: null);

    $this->actingAs($advisor)
        ->patchJson(route('operations.customers.vehicles.update', [$repairOrder->customer, $repairOrder->vehicle]), [
            'year' => 2014,
            'make' => 'Jeep',
            'model' => 'Wrangler',
            'vin' => '1C4HJXDG6EW123456',
        ])
        ->assertOk()
        ->assertJsonPath('vehicle.lines.0.label', 'VIN')
        ->assertJsonPath('vehicle.lines.0.value', '1C4HJXDG6EW123456');
});

function vinWorkflowRepairOrder(?string $vin): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Vin',
        'last_name' => 'Workflow',
        'phone' => '7195554403',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'VINFLW',
        'vin' => $vin,
        'normalized_vin' => $vin,
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Noise on acceleration',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Diagnose noise',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return $repairOrder;
}
