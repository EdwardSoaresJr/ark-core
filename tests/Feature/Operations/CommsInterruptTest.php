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
    
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(
            ShopSettings::defaultTelephonyCallFlow(),
            ['comms_attention_gate_enabled' => true],
        ),
    ]);
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
