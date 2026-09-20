<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Cache;

test('attention gate allows intake while website lead pressure is active', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => true,
        ]),
        'learn_training_gate_enabled' => false,
    ]);

    $advisor = actingAsLearnCurrentAdvisor();

    $lead = \App\Ark\Operations\Leads\Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brake quote',
        'contact_phone' => '7195553434',
        'contact_name' => 'Jeremiah',
    ]);

    $this->actingAs($advisor)
        ->followingRedirects()
        ->get(route('operations.intake.create', ['lead_id' => $lead->id]))
        ->assertOk()
        ->assertSee('Optional for website leads', false);
});

test('attention gate redirects advisors with unresolved comms pressure', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => true,
        ]),
    ]);

    $advisor = actingAsLearnCurrentAdvisor();

    Customer::query()->create([
        'first_name' => 'Gate',
        'last_name' => 'Test',
        'phone' => '7195557777',
    ]);

    ingestInboundSms('7195557777', 'Still waiting on an update', 'SMgate001');

    $this->actingAs($advisor)
        ->get(route('operations.workboard'))
        ->assertRedirect(CommunicationsNeedsYou::url())
        ->assertSessionHas('comms_gate');

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk();
});

test('attention gate does not block repair orders when only workboard lead pressure exists', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => true,
        ]),
    ]);

    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Stale',
        'last_name' => 'Lead',
        'phone' => '7195551212',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'LEAD1',
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
        'vin' => '1FTFW1E50KFA12345',
    ]);

    \App\Ark\Operations\Leads\Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brake noise',
        'contact_phone' => '7195553434',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Oil change.',
    ]);

    $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(CommunicationsNeedsYou::url())
        ->assertOk();

    $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Stale Lead', false)
        ->assertSee('F-150', false);
});

test('attention gate allows customer and ro reply destinations while pressure is active', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => true,
        ]),
    ]);

    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Gate',
        'last_name' => 'Reply',
        'phone' => '7195557777',
    ]);

    ingestInboundSms('7195557777', 'Still waiting on an update', 'SMgate003');

    $this->actingAs($advisor)
        ->get(route('operations.customers.show', ['customer' => $customer, 'compose' => 'text']))
        ->assertOk()
        ->assertSee('customer-communication', false);
});

test('attention gate does not block technicians with unresolved comms pressure', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => true,
        ]),
    ]);

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    Customer::query()->create([
        'first_name' => 'Production',
        'last_name' => 'Tech',
        'phone' => '7195559999',
    ]);

    ingestInboundSms('7195559999', 'Still waiting on an update', 'SMgate004');

    $customer = Customer::query()->create([
        'first_name' => 'Bay',
        'last_name' => 'Vehicle',
        'phone' => '7195551111',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'TECH1',
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
        'vin' => '4T1B11HK5KU123456',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'assigned_technician_id' => $technician->id,
        'concern_summary' => 'Brake noise.',
    ]);

    $this->actingAs($technician)
        ->get(route('operations.workboard'))
        ->assertOk();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk();
});

test('attention gate is disabled when shop setting is off', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => false,
        ]),
    ]);

    $advisor = actingAsLearnCurrentAdvisor();

    Customer::query()->create([
        'first_name' => 'Open',
        'last_name' => 'Board',
        'phone' => '7195558888',
    ]);

    ingestInboundSms('7195558888', 'Hello', 'SMgate002');

    $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.index'))
        ->assertOk();
});

test('comms escalation texts active advisors after delay', function () {
    bindFakeOutboundSms('SMesc001');

    $this->seed(ArkAuthorizationSeeder::class);

    User::factory()->create([
        'phone' => '3035551212',
    ])->assignRole(ArkRole::Advisor->value);

    Customer::query()->create([
        'first_name' => 'Escalate',
        'last_name' => 'Me',
        'phone' => '7195556666',
    ]);

    $result = ingestInboundSms('7195556666', 'Need a callback', 'SMesc001');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195559999',
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_escalation_enabled' => true,
            'comms_escalation_delay_minutes' => 1,
        ]),
    ]);

    $this->travel(2)->minutes();

    $this->artisan('comms:escalate-unhandled')->assertSuccessful();

    expect(Cache::has('comms:escalated:message:'.(string) $result['message']->id))->toBeTrue();
});
