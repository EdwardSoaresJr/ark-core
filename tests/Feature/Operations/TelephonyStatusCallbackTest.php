<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\Events\CallSessionUpdated;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use Carbon\CarbonImmutable;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('broadcasting.default', 'null');
    config()->set('services.twilio.auth_token', null);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
});

test('incoming twiml wires status callback url', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => ShopSettings::defaultTelephonyCallFlow(),
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@example.com',
        'enabled' => true,
        'position' => 0,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAstatusurl1',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee(route('webhooks.communications.twilio.voice.status'), false)
        ->assertSee('statusCallbackEvent="initiated ringing answered completed"', false);

    CarbonImmutable::setTestNow();
});

test('answered status callback auto assigns ownership from mapped endpoint', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([CallSessionUpdated::class]);

    $edward = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);
    $molly = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195551001',
        'user_id' => $edward->id,
        'enabled' => true,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Molly Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195551002',
        'user_id' => $molly->id,
        'enabled' => true,
        'position' => 1,
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAautoown01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAautoown01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'in-progress',
        'Called' => '+17195551002',
    ])->assertNoContent();

    $session->refresh();

    expect($session->status)->toBe(CallSessionStatus::Answered)
        ->and($session->owned_by_user_id)->toBe($molly->id)
        ->and($session->owned_at)->not->toBeNull()
        ->and($session->worked_at)->toBeNull();
});

test('manual ownership is not overwritten by answered endpoint callback', function () {
    $edward = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);
    $molly = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195551001',
        'user_id' => $edward->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAmanualown1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
        'owned_by_user_id' => $molly->id,
        'owned_at' => now()->subSeconds(30),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAmanualown1',
        'From' => '+17195551234',
        'CallStatus' => 'in-progress',
        'Called' => '+17195551001',
    ])->assertNoContent();

    $session->refresh();

    expect($session->owned_by_user_id)->toBe($molly->id);
});

test('status callback updates call session to active and sets answered_at', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([CallSessionUpdated::class]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAactive001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAactive001',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'in-progress',
    ])->assertNoContent();

    $session->refresh();

    expect($session->status)->toBe(CallSessionStatus::Answered)
        ->and($session->answered_at)->not->toBeNull();

    Event::assertDispatched(CallSessionUpdated::class);
});

test('completed status callback sets ended_at and completed status', function () {
    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcomplete01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Answered,
        'started_at' => now()->subMinutes(3),
        'answered_at' => now()->subMinutes(2),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAcomplete01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'completed',
    ])->assertNoContent();

    $session->refresh();

    expect($session->status)->toBe(CallSessionStatus::Completed)
        ->and($session->ended_at)->not->toBeNull();
});

test('no answer busy and failed map to missed or failed statuses', function () {
    $missed = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAmissed01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551111',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551111',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
    ]);

    $failed = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAfailed01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195552222',
        'to_number' => '+17195559999',
        'normalized_from' => '7195552222',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAmissed01',
        'CallStatus' => 'no-answer',
        'From' => '+17195551111',
        'To' => '+17195559999',
    ])->assertNoContent();

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAfailed01',
        'CallStatus' => 'failed',
        'From' => '+17195552222',
        'To' => '+17195559999',
    ])->assertNoContent();

    $missed->refresh();
    $failed->refresh();

    expect($missed->status)->toBe(CallSessionStatus::Missed)
        ->and($missed->ended_at)->not->toBeNull()
        ->and($failed->status)->toBe(CallSessionStatus::Failed)
        ->and($failed->ended_at)->not->toBeNull();
});

test('status callbacks are idempotent by provider call sid', function () {
    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAidemstat1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195553333',
        'to_number' => '+17195559999',
        'normalized_from' => '7195553333',
        'status' => CallSessionStatus::Answered,
        'started_at' => now()->subMinutes(2),
        'answered_at' => now()->subMinute(),
    ]);

    $payload = [
        'CallSid' => 'CAidemstat1',
        'CallStatus' => 'completed',
        'From' => '+17195553333',
        'To' => '+17195559999',
    ];

    $this->post(route('webhooks.communications.twilio.voice.status'), $payload)->assertNoContent();
    $this->post(route('webhooks.communications.twilio.voice.status'), $payload)->assertNoContent();

    expect(CallSession::query()->where('provider_call_sid', 'CAidemstat1')->count())->toBe(1);

    $session->refresh();

    expect($session->status)->toBe(CallSessionStatus::Completed)
        ->and($session->ended_at)->not->toBeNull();
});

test('claiming sets owned_by_user_id and owned_at', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([CallSessionUpdated::class]);

    $edward = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAclaim001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195554444',
        'to_number' => '+17195559999',
        'normalized_from' => '7195554444',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subSeconds(30),
    ]);

    $this->actingAs($edward)
        ->postJson(route('operations.telephony.calls.claim', $session))
        ->assertOk()
        ->assertJsonPath('claimed', true)
        ->assertJsonPath('owned_by_name', 'Alex Rivera');

    $session->refresh();

    expect($session->owned_by_user_id)->toBe($edward->id)
        ->and($session->owned_at)->not->toBeNull()
        ->and($session->worked_at)->not->toBeNull();

    Event::assertDispatched(CallSessionUpdated::class);
});

test('claiming marks caller handled while preserving ownership', function () {
    $molly = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAseparate1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195555555',
        'to_number' => '+17195559999',
        'normalized_from' => '7195555555',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'ended_at' => now()->subMinutes(4),
    ]);

    $this->actingAs($molly)
        ->postJson(route('operations.telephony.calls.claim', $session))
        ->assertOk();

    $session->refresh();

    expect($session->worked_at)->not->toBeNull()
        ->and($session->owned_by_user_id)->toBe($molly->id);

    $this->actingAs($molly)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 0);
});

test('queue exposes ownership for advisors and allows reassignment', function () {
    $edward = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);
    $molly = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAowner001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195556666',
        'to_number' => '+17195559999',
        'normalized_from' => '7195556666',
        'status' => CallSessionStatus::Answered,
        'started_at' => now()->subMinute(),
        'answered_at' => now()->subSeconds(20),
        'owned_by_user_id' => $edward->id,
        'owned_at' => now(),
    ]);

    $this->actingAs($molly)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('calls.0.owned_by_name', 'Alex Rivera')
        ->assertJsonPath('calls.0.is_owned_by_me', false)
        ->assertJsonPath('calls.0.show_claim_action', true);

    $this->actingAs($molly)
        ->postJson(route('operations.telephony.calls.claim', $session))
        ->assertOk();

    $this->actingAs($molly)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('calls.0.owned_by_name', 'Molly Advisor')
        ->assertJsonPath('calls.0.is_owned_by_me', true)
        ->assertJsonPath('calls.0.show_claim_action', false);
});

test('fleet call status callback does not force repair order ownership', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Acme',
        'last_name' => 'Fleet',
        'phone' => '3035550100',
    ]);

    foreach ([1001, 1002, 1003] as $roNumber) {
        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'year' => 2020,
            'make' => 'Ford',
            'model' => 'F-150',
        ]);

        RepairOrder::query()->create([
            'repair_order_id' => $roNumber,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => RepairOrderStatus::WaitingApproval,
            'concern_summary' => 'Fleet telephony',
        ]);
    }

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAfleetstat1',
        'From' => '+13035550100',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk();

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAfleetstat1',
        'From' => '+13035550100',
        'To' => '+17195559999',
        'CallStatus' => 'completed',
    ])->assertNoContent();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('calls.0.open_repair_orders', fn ($orders) => count($orders) === 3)
        ->assertJsonPath('calls.0.primary_ro_url', null);
});

test('status callback does not create conversation message', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '5550100888',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 2001,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Status callback test',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    app(ConversationRecorder::class)->recordAdvisorLog(
        $repairOrder,
        $advisor,
        OperationalCommunicationChannel::Phone,
        OperationalCommunicationDirection::Inbound,
        'Prior phone note',
    );

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAstatusmsg1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+15550100888',
        'to_number' => '+17195559999',
        'normalized_from' => '5550100888',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
        'customer_id' => $customer->id,
    ]);

    $beforeCount = ConversationMessage::query()->count();

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAstatusmsg1',
        'From' => '+15550100888',
        'To' => '+17195559999',
        'CallStatus' => 'completed',
    ])->assertNoContent();

    expect(ConversationMessage::query()->count())->toBe($beforeCount);
});
