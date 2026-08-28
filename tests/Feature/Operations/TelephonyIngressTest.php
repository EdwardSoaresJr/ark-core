<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\Realtime\SessionEventType;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\Events\IncomingCallReceived;
use App\Ark\Operations\Telephony\IncomingCallBroadcast;
use App\Ark\Operations\Telephony\IncomingCallContextBroadcaster;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use App\Ark\Operations\Telephony\IncomingCallContextPresenter;
use App\Ark\Operations\Telephony\TelephonyRingState;
use App\Ark\Operations\Telephony\ProcessIncomingCallAction;
use App\Ark\Operations\Telephony\TelephonyProviderManager;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('broadcasting.default', 'null');
    config()->set('services.twilio.auth_token', null);
});

test('twilio incoming webhook creates call session and returns twiml', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([IncomingCallReceived::class]);

    $customer = telephonyCustomer('John', 'Smith', '7195551234');
    $vehicle = telephonyVehicle($customer, 'Subaru', 'Outback');
    telephonyRepairOrder($customer, $vehicle, RepairOrderStatus::WaitingApproval, 1432);

    $payload = [
        'CallSid' => 'CA123incoming',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ];

    $response = $this->post(route('webhooks.communications.twilio.voice.incoming'), $payload);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertSee('<Response>', false);

    $session = CallSession::query()->where('provider_call_sid', 'CA123incoming')->first();

    expect($session)->not->toBeNull()
        ->and($session->customer_id)->toBe($customer->id)
        ->and($session->normalized_from)->toBe('7195551234')
        ->and($session->status)->toBe(CallSessionStatus::Ringing);

    Event::assertDispatched(IncomingCallReceived::class, function (IncomingCallReceived $event) use ($customer): bool {
        return $event->context['matched'] === true
            && $event->context['customer_id'] === $customer->id
            && count($event->context['open_repair_orders']) === 1;
    });
});

test('incoming call surfaces multiple open ros without selecting one', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([IncomingCallReceived::class]);

    $customer = telephonyCustomer('Acme', 'Plumbing', '3035550100');

    foreach ([
        ['F-150', RepairOrderStatus::WaitingApproval, 1001],
        ['Transit', RepairOrderStatus::InProgress, 1002],
        ['Silverado', RepairOrderStatus::WaitingParts, 1003],
    ] as [$model, $status, $roNumber]) {
        $vehicle = telephonyVehicle($customer, 'Ford', $model);
        telephonyRepairOrder($customer, $vehicle, $status, $roNumber);
    }

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAfleet001',
        'From' => '+13035550100',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk();

    $session = CallSession::query()->where('provider_call_sid', 'CAfleet001')->first();

    expect($session?->customer_id)->toBe($customer->id);

    Event::assertDispatched(IncomingCallReceived::class, function (IncomingCallReceived $event): bool {
        return count($event->context['open_repair_orders']) === 3
            && collect($event->context['open_repair_orders'])->pluck('repair_order_id')->sort()->values()->all() === [1001, 1002, 1003];
    });
});

test('unknown caller creates session without customer match', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([IncomingCallReceived::class]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAunknown01',
        'From' => '+15550100999',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk();

    $session = CallSession::query()->where('provider_call_sid', 'CAunknown01')->first();

    expect($session)->not->toBeNull()
        ->and($session->customer_id)->toBeNull();

    Event::assertDispatched(IncomingCallReceived::class, fn (IncomingCallReceived $event): bool => $event->context['matched'] === false);
});

test('incoming webhook is idempotent by provider call sid', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([IncomingCallReceived::class]);

    $payload = [
        'CallSid' => 'CAduplicate',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ];

    $this->post(route('webhooks.communications.twilio.voice.incoming'), $payload)->assertOk();
    $this->post(route('webhooks.communications.twilio.voice.incoming'), $payload)->assertOk();

    expect(CallSession::query()->where('provider_call_sid', 'CAduplicate')->count())->toBe(1);
    Event::assertDispatchedTimes(IncomingCallReceived::class, 1);
});

test('ringing call does not create conversation message', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([IncomingCallReceived::class]);

    $customer = telephonyCustomer('Jane', 'Doe', '5550100888');
    $vehicle = telephonyVehicle($customer, 'Honda', 'Civic');
    $repairOrder = telephonyRepairOrder($customer, $vehicle, RepairOrderStatus::WaitingApproval, 2001);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    app(ConversationRecorder::class)->recordAdvisorLog(
        $repairOrder,
        $advisor,
        OperationalCommunicationChannel::Phone,
        OperationalCommunicationDirection::Inbound,
        'Can I pick it up Friday?',
    );

    $beforeCount = ConversationMessage::query()->count();

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAringing01',
        'From' => '+15550100888',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk();

    expect(ConversationMessage::query()->count())->toBe($beforeCount);
});

test('resolver output feeds incoming call context payload', function () {
    $customer = telephonyCustomer('Pat', 'Rivera', '5550100777');
    $vehicle = telephonyVehicle($customer, 'Chevrolet', 'Malibu');
    telephonyRepairOrder($customer, $vehicle, RepairOrderStatus::InProgress, 3201);

    $provider = app(TelephonyProviderManager::class)->current();
    $simulated = Request::create('/', 'POST', [
        'CallSid' => 'CApresenter1',
        'From' => '+15550100777',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ]);

    $payload = $provider->parseIncomingVoiceRequest($simulated);
    $result = app(ProcessIncomingCallAction::class)->execute($payload);

    $presented = app(IncomingCallContextPresenter::class)->present($result['session'], $result['context']);

    expect($presented['customer_name'])->toBe('Pat Rivera')
        ->and($presented['open_repair_orders'][0]['repair_order_id'])->toBe(3201)
        ->and($presented['vehicles'][0]['display_name'])->toContain('Chevrolet');
});

test('unknown caller intake url prefills phone for new customer capture', function () {
    $provider = app(TelephonyProviderManager::class)->current();
    $simulated = Request::create('/', 'POST', [
        'CallSid' => 'CAintakeurl1',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ]);

    $payload = $provider->parseIncomingVoiceRequest($simulated);
    $result = app(ProcessIncomingCallAction::class)->execute($payload);

    $presented = app(IncomingCallContextPresenter::class)->present($result['session'], $result['context']);

    expect($presented['matched'])->toBeFalse()
        ->and($presented['intake_url'])->toContain('phone=7195551234');
});

test('incoming webhook still returns twiml when live broadcast fails', function () {
    config()->set('broadcasting.default', 'reverb');

    $broadcastException = new BroadcastException('Pusher error: Internal server error.');

    Broadcast::shouldReceive('event')->andThrow($broadcastException);
    Broadcast::shouldReceive('queue')->andThrow($broadcastException);

    $payload = [
        'CallSid' => 'CAbroadcastfail',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ];

    $this->post(route('webhooks.communications.twilio.voice.incoming'), $payload)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertSee('<Response>', false);

    expect(CallSession::query()->where('provider_call_sid', 'CAbroadcastfail')->exists())->toBeTrue();
});

test('twilio webhook rejects invalid signature when auth token configured', function () {
    config()->set('services.twilio.auth_token', 'test-auth-token');

    $url = route('webhooks.communications.twilio.voice.incoming');
    $payload = [
        'CallSid' => 'CAbadsignature',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ];

    $this->post($url, $payload, [
        'X-Twilio-Signature' => 'invalid-signature',
    ])->assertUnauthorized();
});

test('dismissing incoming call clears cache only for the dismissed session', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Cache::put(IncomingCallContextBroadcaster::cacheKey(), [
        'call_session_id' => 4,
        'display_phone' => '(719) 555-1234',
    ], now()->addMinute());

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.incoming-call.dismiss'), [
            'call_session_id' => 4,
        ])
        ->assertNoContent();

    expect(Cache::has(IncomingCallContextBroadcaster::cacheKey()))->toBeFalse();

    Cache::put(IncomingCallContextBroadcaster::cacheKey(), [
        'call_session_id' => 8,
        'display_phone' => '(719) 555-9999',
    ], now()->addMinute());

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.incoming-call.dismiss'), [
            'call_session_id' => 4,
        ])
        ->assertNoContent();

    expect(Cache::get(IncomingCallContextBroadcaster::cacheKey())['call_session_id'])->toBe(8);
});

test('dismissing call marks caller handled and clears queue pressure', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAdismisshandled01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinute(),
        'ended_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.incoming-call.dismiss'), [
            'call_session_id' => $session->id,
        ])
        ->assertNoContent();

    expect($session->fresh()->worked_at)->not->toBeNull();

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 0);
});

test('staff with operations access can authorize incoming call broadcast channel', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-'.IncomingCallBroadcast::channelName(),
            'socket_id' => '1.1',
        ])
        ->assertOk();
});

test('simulate incoming call route works in local testing', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([IncomingCallReceived::class]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $customer = telephonyCustomer('Sim', 'Caller', '7195554242');
    $vehicle = telephonyVehicle($customer, 'Toyota', 'Tacoma');
    telephonyRepairOrder($customer, $vehicle, RepairOrderStatus::WaitingApproval, 9001);

    $this->postJson(route('dev.telephony.simulate-incoming-call'), [
        'phone' => '719-555-4242',
    ])->assertOk()
        ->assertJsonPath('matched', true)
        ->assertJsonPath('open_repair_order_count', 1);

    Event::assertDispatched(IncomingCallReceived::class);
});

test('inbound caller display ignores shop inbound number stored on session', function () {
    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);

    app(TelephonyRingState::class)->initializeParallel('CAshopfrom01', '7195551234');

    $session = CallSession::query()->create([
        'provider' => \App\Ark\Operations\Telephony\TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CAshopfrom01',
        'direction' => \App\Ark\Operations\Telephony\CallSessionDirection::Inbound,
        'from_number' => '+17195550100',
        'to_number' => '+17195550100',
        'normalized_from' => '7195550100',
        'normalized_to' => '7195550100',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now(),
    ]);

    $display = app(InboundCallerDisplayPhone::class);

    expect($display->forSession($session))->toBe('(719) 555-1234')
        ->and($display->normalizedForSession($session))->toBe('7195551234');
});

test('incoming call popup shows customer phone instead of shop inbound number', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([IncomingCallReceived::class]);

    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAdisplay01',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'ringing',
    ])->assertOk();

    Event::assertDispatched(IncomingCallReceived::class, function (IncomingCallReceived $event): bool {
        return $event->context['display_phone'] === '(719) 555-1234'
            && $event->context['normalized_from'] === '7195551234';
    });
});

test('twilio production webhooks append session events through ingress', function () {
    $callSid = 'CAsessionevents01';

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => $callSid,
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk();

    $session = CallSession::query()->where('provider_call_sid', $callSid)->firstOrFail();

    expect(SessionEvent::query()->where('call_session_id', $session->id)->pluck('event_type')->all())
        ->toBe([SessionEventType::SessionStarted]);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => $callSid,
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'in-progress',
    ])->assertNoContent();

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => $callSid,
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'completed',
    ])->assertNoContent();

    $session->refresh();

    expect(SessionEvent::query()->where('call_session_id', $session->id)->pluck('event_type')->all())
        ->toBe([
            SessionEventType::SessionStarted,
            SessionEventType::SessionAnswered,
            SessionEventType::SessionEnded,
        ])
        ->and($session->status)->toBe(CallSessionStatus::Completed);

    $headlines = app(UnifiedOperationalTimeline::class)
        ->forCallSession($session)
        ->pluck('headline')
        ->all();

    expect($headlines)->toBe([
        'Incoming call · Completed',
    ]);
});

function telephonyCustomer(string $first, string $last, string $phone): Customer
{
    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'customer_type' => 'Retail',
    ]);
}

function telephonyVehicle(Customer $customer, string $make, string $model): Vehicle
{
    return Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => $make,
        'model' => $model,
    ]);
}

function telephonyRepairOrder(
    Customer $customer,
    Vehicle $vehicle,
    RepairOrderStatus $status,
    int $repairOrderNumber,
): RepairOrder {
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => $repairOrderNumber,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Telephony ingress test',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Test concern',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return $repairOrder;
}
