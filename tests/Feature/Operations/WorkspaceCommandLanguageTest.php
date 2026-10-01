<?php

use App\Ark\Operations\Appointments\ScheduleUrl;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    ShopSettings::current()->update(['appointments_enabled' => true]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

test('customer hub exposes Call Text Schedule and Create RO commands', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Command',
        'last_name' => 'Hub',
        'phone' => '7195550401',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('aria-label="Customer commands"', false)
        ->assertSee('Call', false)
        ->assertSee('Text', false)
        ->assertSee('compose=text#customer-communication', false)
        ->assertSee('Schedule', false)
        ->assertSee('Create RO', false)
        ->assertSee(PhoneNumber::telUri($customer->phone), false)
        ->assertSee(ScheduleUrl::to(['customer' => $customer->id]), false);
});

test('vehicle row exposes Schedule and Start RO commands', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Fleet',
        'last_name' => 'Cmd',
        'phone' => '7195550402',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Chevy',
        'model' => 'Equinox',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.customers.show', ['customer' => $customer, 'vehicle' => $vehicle->id]))
        ->assertOk()
        ->assertSee('aria-label="Vehicle commands"', false)
        ->assertSee('Schedule', false)
        ->assertSee('Start RO', false)
        ->assertSee('schedule?customer='.$customer->id, false)
        ->assertSee('vehicle='.$vehicle->id, false);
});

test('repair order identity exposes Message, Schedule Follow-up, and New RO', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Ro',
        'last_name' => 'Commands',
        'phone' => '7195550403',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Ford',
        'model' => 'Escape',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => 9403,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Follow-up booking',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Message', false)
        ->assertSee('#communication-rail', false)
        ->assertSee('Schedule Follow-up', false)
        ->assertSee(ScheduleUrl::to(['repair_order' => $repairOrder->id]), false)
        ->assertSee('New RO', false)
        ->assertSee('customer_id='.$customer->id, false)
        ->assertSee('vehicle_id='.$vehicle->id, false)
        ->assertSee('source_repair_order_id='.$repairOrder->id, false)
        ->assertSee('/app/intake/new', false);
});

test('conversation quick reply exposes Call beside Schedule', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Text',
        'last_name' => 'Thread',
        'phone' => '7195550404',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.customers.show', ['customer' => $customer, 'tab' => 'comms']))
        ->assertOk()
        ->assertSee('aria-label="Conversation commands"', false)
        ->assertSee('Call', false)
        ->assertSee('Schedule', false)
        ->assertSee(PhoneNumber::telUri($customer->phone), false)
        ->assertSee(ScheduleUrl::to(['customer' => $customer->id]), false);
});
