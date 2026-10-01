<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(
            ShopSettings::defaultTelephonyCallFlow(),
            ['comms_attention_gate_enabled' => true],
        ),
    ]);
});

test('call queue exposes recording and voicemail playback for missed calls', function () {
        
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Voicemail',
        'last_name' => 'Caller',
        'phone' => '7195551099',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAqueue005',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551099',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551099',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $customer->id,
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/RE456',
        'voicemail_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/RE456',
        'recording_sid' => 'RE456',
        'voicemail_sid' => 'RE456',
        'started_at' => now()->subMinutes(12),
    ]);

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'));

    $response
        ->assertOk()
        ->assertJsonPath('items.0.call_session_id', $session->id)
        ->assertJsonPath('items.0.direction', 'inbound')
        ->assertJsonPath('items.0.direction_label', 'Incoming')
        ->assertJsonPath('items.0.has_recording', false)
        ->assertJsonPath('items.0.has_voicemail', false)
        ->assertJsonPath('items.0.show_play_recording_action', false)
        ->assertJsonPath('items.0.show_play_voicemail_action', false)
        ->assertJsonPath('items.0.dropdown_label', 'Call · Missed · Voicemail Caller');

    expect($response->json('html'))
        ->toContain('ops-queue-row')
        ->toContain('Voicemail');
});

test('call queue does not expose play actions when media sources are unavailable', function () {
        
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAqueue006',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551098',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551098',
        'status' => CallSessionStatus::Completed,
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/RE111',
        'voicemail_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/RE222',
        'recording_sid' => 'RE111',
        'voicemail_sid' => 'RE222',
        'started_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('items.0.has_recording', false)
        ->assertJsonPath('items.0.has_voicemail', false)
        ->assertJsonPath('items.0.show_play_recording_action', false)
        ->assertJsonPath('items.0.show_play_voicemail_action', false);
});

test('call queue returns unworked recent sessions for all advisors', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '7195551001',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    RepairOrder::query()->create([
        'repair_order_id' => 1432,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Queue test',
    ]);

    $ringing = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAqueue001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551001',
        'status' => CallSessionStatus::Ringing,
        'customer_id' => $customer->id,
        'started_at' => now()->subMinutes(2),
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAqueue002',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subSeconds(30),
        'worked_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('summary.urgency', 'live')
        ->assertJsonPath('summary.trigger_label', 'Live now')
        ->assertJsonPath('summary.breakdown_label', '1 call')
        ->assertJsonPath('calls.0.call_session_id', $ringing->id)
        ->assertJsonPath('calls.0.headline', 'John Smith')
        ->assertJsonPath('calls.0.status_label', 'Ringing')
        ->assertJsonPath('calls.0.context_summary', 'Waiting on Customer Approval');
});

test('call queue exposes text customer action for matched callers when messaging is enabled', function () {
    bindFakeOutboundSms();

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Text',
        'last_name' => 'Target',
        'phone' => '7195551002',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAqueue004',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551002',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551002',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $customer->id,
        'started_at' => now()->subMinute(),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('calls.0.call_session_id', $session->id)
        ->assertJsonPath('calls.0.show_text_customer_action', true)
        ->assertJsonPath('calls.0.text_customer_url', route('operations.customers.show', $customer).'?compose=text#customer-communication');
});

test('mark worked removes call from queue without deleting session', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAqueue003',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559988',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559988',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinute(),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.call-queue.worked', $session))
        ->assertOk()
        ->assertJsonPath('worked', true);

    $session->refresh();

    expect($session->worked_at)->not->toBeNull();

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 0);
});

test('mark worked clears all unworked sessions for the same caller', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    foreach (range(1, 5) as $index) {
        CallSession::query()->create([
            'provider' => 'twilio',
            'provider_call_sid' => 'CAhandled'.$index,
            'direction' => CallSessionDirection::Inbound,
            'from_number' => '+17195551001',
            'to_number' => '+17195559999',
            'normalized_from' => '7195551001',
            'status' => CallSessionStatus::Ringing,
            'started_at' => now()->subMinutes($index),
        ]);
    }

    $latest = CallSession::query()
        ->where('normalized_from', '7195551001')
        ->orderByDesc('id')
        ->first();

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.call-queue.worked', $latest))
        ->assertOk()
        ->assertJsonPath('cleared_count', 5);

    expect(CallSession::query()->where('normalized_from', '7195551001')->whereNull('worked_at')->count())->toBe(0);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 0);
});

test('call queue dedupes repeated calls from same caller', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    foreach (range(1, 5) as $index) {
        CallSession::query()->create([
            'provider' => 'twilio',
            'provider_call_sid' => 'CAdedupe'.$index,
            'direction' => CallSessionDirection::Inbound,
            'from_number' => '+17195551001',
            'to_number' => '+17195559999',
            'normalized_from' => '7195551001',
            'status' => CallSessionStatus::Ringing,
            'started_at' => now()->subMinutes($index),
        ]);
    }

    $latest = CallSession::query()
        ->where('normalized_from', '7195551001')
        ->orderByDesc('id')
        ->first();

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('calls.0.call_session_id', $latest->id);
});

test('call queue sorts ringing before missed and hides handled on completed calls', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $completed = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcompleted',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195552001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195552001',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'ended_at' => now()->subMinutes(4),
    ]);

    $missed = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAmissed',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195553001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195553001',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinutes(10),
    ]);

    $ringing = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAringing',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195554001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195554001',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subSeconds(20),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 3)
        ->assertJsonPath('calls.0.call_session_id', $ringing->id)
        ->assertJsonPath('calls.1.call_session_id', $missed->id)
        ->assertJsonPath('calls.2.call_session_id', $completed->id)
        ->assertJsonPath('calls.0.show_handled_action', true)
        ->assertJsonPath('calls.2.show_handled_action', true);
});

test('completed calls remain in queue until handled', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAstale',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195555001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195555001',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(45),
        'ended_at' => now()->subMinutes(40),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('calls.0.status_label', 'Completed');
});

test('operations layout exposes hidden call queue poller without topbar attention affordance', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('ops-call-queue__trigger', false)
        ->assertDontSee('ops-call-queue__label">Attention', false)
        ->assertSee('id="ark-call-queue-bootstrap"', false)
        ->assertSee('ops-call-queue--poller-only', false)
        ->assertSee(route('operations.telephony.call-queue'), false);
});

test('waiting sessions read does not reconcile stale live sessions', function () {
    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAreadstale001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '+17195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinutes(30),
    ]);

    app(\App\Ark\Operations\Telephony\CallSessionQueue::class)->waitingSessions();

    expect(CallSession::query()->where('provider_call_sid', 'CAreadstale001')->value('status'))
        ->toBe(CallSessionStatus::Ringing);
});

test('call queue open ros url targets open repair orders when customer has multiple active jobs', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Garcia',
        'phone' => '7195551001',
    ]);

    foreach ([1001, 1002] as $repairOrderId) {
        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'year' => 2014,
            'make' => 'Subaru',
            'model' => 'Outback '.$repairOrderId,
        ]);

        RepairOrder::query()->create([
            'repair_order_id' => $repairOrderId,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => RepairOrderStatus::InProgress,
            'concern_summary' => 'Queue multi-ro test',
        ]);
    }

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAmultiro',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551001',
        'status' => CallSessionStatus::Ringing,
        'customer_id' => $customer->id,
        'started_at' => now()->subMinutes(6),
    ]);

    $customerUrl = route('operations.customers.show', $customer);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('calls.0.context_summary', 'Repair In Progress')
        ->assertJsonPath('calls.0.primary_ro_url', null)
        ->assertJsonPath('calls.0.open_ros_url', $customerUrl.'#open-repair-orders')
        ->assertJsonPath('calls.0.customer_url', $customerUrl);
});

test('call queue api includes unread inbound sms in items and summary', function () {
    
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'phone' => '7195551234',
    ]);

    ingestInboundSms('7195551234', 'Is my vehicle ready?', 'SMcallqueue001');

        
    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('summary.message_count', 1)
        ->assertJsonPath('summary.breakdown_label', '1 SMS')
        ->assertJsonPath('items.0.kind', 'sms')
        ->assertJsonPath('items.0.headline', 'Jane Driver')
        ->assertJsonPath('items.0.snippet', 'Is my vehicle ready?');
});

test('operations layout exposes unified comms interrupt panel', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('arkCommsInterrupt()', false)
        ->assertSee('Mark Handled', false);
});

test('operations layout bootstraps comms queue with unread sms', function () {
    
    $advisor = actingAsLearnCurrentAdvisor();

    Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'phone' => '7195551234',
    ]);

    ingestInboundSms('7195551234', 'Where is my car?', 'SMlayout001');

        
    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('id="ark-call-queue-bootstrap"', false)
        ->assertSee('"message_count":1', false)
        ->assertSee('"kind":"sms"', false)
        ->assertSee('1 SMS', false);
});

test('stale answered calls stop projecting as live attention', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAstale001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '+17195551234',
        'status' => CallSessionStatus::Answered,
        'started_at' => now()->subHour(),
        'answered_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('summary.has_live_calls', false)
        ->assertJsonPath('summary.call_count', 1);
});

test('stale ringing calls stop projecting as live attention', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAringstale001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '+17195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinutes(30),
        'worked_at' => now()->subMinutes(20),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('summary.has_live_calls', false)
        ->assertJsonPath('summary.call_count', 0);
});

test('comms interrupt api projects actively ringing calls from queue without cache', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAringlive001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '+17195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('call.status', 'ringing')
        ->assertJsonPath('call.is_actively_live', true);
});
