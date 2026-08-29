<?php

use App\Ark\Operations\Communications\Events\CommsInterruptReceived;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\IncomingCallContextBroadcaster;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.twilio.auth_token', null);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(
            ShopSettings::defaultTelephonyCallFlow(),
            ['comms_attention_gate_enabled' => true],
        ),
    ]);
});

test('comms interrupt api returns live call and unread messages', function () {
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');

    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'phone' => '7195551234',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMinterrupt001',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'Body' => 'Need an update please',
        'NumMedia' => '0',
    ])->assertOk();

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('call', null)
        ->assertJsonPath('messages.0.kind', 'sms')
        ->assertJsonPath('messages.0.state', 'unread')
        ->assertJsonPath('messages.0.headline', 'Jane Driver')
        ->assertJsonPath('messages.0.snippet', 'Need an update please');
});

test('operations layout exposes unified comms interrupt engine', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('arkCommsInterrupt()', false)
        ->assertSee('ark-comms-interrupt-url', false)
        ->assertSee('ark-comms-browser-notifications', false)
        ->assertSee('Mark Handled', false)
        ->assertSee('Dismiss', false)
        ->assertSee('ark-owned-popup-timeout-seconds', false);
});

test('comms interrupt api includes mms attachment urls for popup preview', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');

    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Customer::query()->create([
        'first_name' => 'Photo',
        'last_name' => 'Sender',
        'phone' => '3035550100',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMinterrupt003',
        'From' => '+13035550100',
        'To' => '+17195559999',
        'Body' => 'Test',
        'NumMedia' => '1',
        'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/ACtest/Messages/MM123/Media/ME456',
        'MediaContentType0' => 'image/jpeg',
    ])->assertOk();

    $attachment = ConversationMessageAttachment::query()->with('message')->firstOrFail();
    $message = $attachment->message;

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages.0.kind', 'mms')
        ->assertJsonPath('messages.0.has_attachment', true)
        ->assertJsonPath('messages.0.attachments.0.id', $attachment->id)
        ->assertJsonPath('messages.0.attachments.0.is_image', true)
        ->assertJsonPath('messages.0.attachments.0.url', route('operations.conversation-attachments.show', [
            'conversation' => $message->conversation_id,
            'message' => $message->id,
            'attachment' => $attachment,
        ]));
});

test('comms interrupt api includes audio attachment flags for voice memos', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response('audio-bytes', 200, ['Content-Type' => 'audio/mp4']),
    ]);

    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');

    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Customer::query()->create([
        'first_name' => 'Voice',
        'last_name' => 'Memo',
        'phone' => '3035550200',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMinterrupt004',
        'From' => '+13035550200',
        'To' => '+17195559999',
        'Body' => '',
        'NumMedia' => '1',
        'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/ACtest/Messages/MM789/Media/ME999',
        'MediaContentType0' => 'audio/mp4',
    ])->assertOk();

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages.0.kind', 'mms')
        ->assertJsonPath('messages.0.attachments.0.is_audio', true)
        ->assertJsonPath('messages.0.attachments.0.is_image', false);
});

test('inbound sms dispatches unified comms interrupt broadcast', function () {
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');
    config()->set('broadcasting.default', 'reverb');

    Event::fake([CommsInterruptReceived::class]);

    Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'phone' => '7195551234',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMinterrupt002',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'Body' => 'Any news?',
        'NumMedia' => '0',
    ])->assertOk();

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event): bool {
        return ($event->payload['kind'] ?? '') === 'sms'
            && ($event->payload['action'] ?? '') === 'show'
            && ($event->payload['interrupt']['state'] ?? '') === 'unread';
    });
});

test('comms interrupt api omits live outbound calls', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAoutboundpopup001',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => 'sip:101@example.sip.us1.twilio.com',
        'to_number' => '+17195551234',
        'normalized_from' => '7195550000',
        'normalized_to' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'owned_by_user_id' => $advisor->id,
        'started_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('call', null);
});

test('comms interrupt api omits live calls owned by another advisor', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $owner = User::factory()->create(['name' => 'Ben Tech'])->assignRole(ArkRole::Advisor->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAownedrefresh001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Answered,
        'owned_by_user_id' => $owner->id,
        'owned_at' => now(),
        'started_at' => now()->subMinute(),
        'answered_at' => now()->subSeconds(30),
    ]);

    Cache::put(IncomingCallContextBroadcaster::cacheKey(), [
        'call_session_id' => $session->id,
        'display_phone' => '(719) 555-1234',
        'status' => 'ringing',
        'owned_by_user_id' => null,
        'owned_by_name' => null,
        'is_actively_live' => true,
    ], now()->addMinutes(2));

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('call', null);

    $this->actingAs($owner)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('call.call_session_id', $session->id)
        ->assertJsonPath('call.owned_by_user_id', $owner->id)
        ->assertJsonPath('call.owned_by_name', 'Ben Tech')
        ->assertJsonPath('call.status', 'answered')
        ->assertJsonPath('call.is_actively_live', true);
});

test('comms interrupt api omits call dismissed by the viewing advisor', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $otherAdvisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAdismisspopup001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now(),
    ]);

    Cache::put(IncomingCallContextBroadcaster::cacheKey(), [
        'call_session_id' => $session->id,
        'display_phone' => '(719) 555-1234',
        'status' => 'ringing',
        'owned_by_user_id' => null,
        'owned_by_name' => null,
        'is_actively_live' => true,
    ], now()->addMinutes(2));

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('call.call_session_id', $session->id);

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.incoming-call.dismiss'), [
            'call_session_id' => $session->id,
        ])
        ->assertNoContent();

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('call', null);

    $this->actingAs($otherAdvisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('call', null);
});

test('completed call status clears comms interrupt broadcast', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([CommsInterruptReceived::class]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAclear001',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'answered',
        'started_at' => now()->subMinute(),
        'answered_at' => now()->subMinute(),
    ]);

    Cache::put(IncomingCallContextBroadcaster::cacheKey(), [
        'call_session_id' => $session->id,
    ], now()->addMinutes(2));

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAclear001',
        'CallStatus' => 'completed',
        'From' => '+17195551234',
        'To' => '+17195559999',
    ])->assertNoContent();

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event): bool {
        return ($event->payload['kind'] ?? '') === 'call'
            && ($event->payload['action'] ?? '') === 'clear';
    });

    expect(Cache::get(IncomingCallContextBroadcaster::cacheKey()))->toBeNull();
});

test('comms interrupt panel stays available when accountability gate is off', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(
            ShopSettings::defaultTelephonyCallFlow(),
            ['comms_attention_gate_enabled' => false],
        ),
    ]);

    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('ark-comms-attention-gate-enabled" content="0"', false)
        ->assertSee('arkCommsInterrupt()', false)
        ->assertDontSee('ops-comms-pressure-bar', false);
});
