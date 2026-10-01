<?php

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Telephony\TelephonyRingState;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
    config()->set('broadcasting.default', 'null');
    Http::fake(['https://api.twilio.com/*' => Http::response([], 200)]);
});

test('ring-leg answer stamps answered_at even when the endpoint has no owner', function () {
    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CAringanswer01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
    ]);

    $endpoint = TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@example.com',
        'user_id' => null,
        'enabled' => true,
        'position' => 0,
    ]);

    app(TelephonyRingState::class)->initializeParallel('CAringanswer01', '7195551234');

    $this->post(route('webhooks.communications.twilio.voice.ring-status', [
        'parentCallSid' => 'CAringanswer01',
        'endpointId' => $endpoint->id,
    ]), [
        'CallSid' => 'CAchildleg99',
        'CallStatus' => 'answered',
    ])->assertNoContent();

    $session->refresh();

    expect($session->answered_at)->not->toBeNull()
        ->and($session->status)->toBe(CallSessionStatus::Answered)
        ->and($session->owned_by_user_id)->toBeNull();
});

test('child in-progress status callback sets answered_at on the parent session', function () {
    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CAparentstat01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAchildstat01',
        'ParentCallSid' => 'CAparentstat01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'in-progress',
        'Direction' => 'outbound-dial',
    ])->assertNoContent();

    $session->refresh();

    expect($session->status)->toBe(CallSessionStatus::Answered)
        ->and($session->answered_at)->not->toBeNull();

    expect(CallSession::query()->where('provider_call_sid', 'CAchildstat01')->exists())->toBeFalse();
});

test('losing child no-answer does not create a session or miss the parent', function () {
    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CAparentmiss01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Answered,
        'answered_at' => now()->subSeconds(20),
        'started_at' => now()->subMinute(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAlosingleg01',
        'ParentCallSid' => 'CAparentmiss01',
        'From' => '+17195559999',
        'To' => 'sip:102@example.com',
        'CallStatus' => 'no-answer',
        'Direction' => 'outbound-dial',
    ])->assertNoContent();

    $session->refresh();

    expect($session->status)->toBe(CallSessionStatus::Answered)
        ->and(CallSession::query()->where('provider_call_sid', 'CAlosingleg01')->exists())->toBeFalse();
});
