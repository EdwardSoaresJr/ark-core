<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'AC-test');
    config()->set('broadcasting.default', 'null');
    TelephonyEndpoint::query()->delete();

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);
});

test('sip outbound webhook dials pstn with shop caller id', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@example.sip.us1.twilio.com',
        'user_id' => $advisor->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '7195551234',
    ]);

    $response = $this->post(route('webhooks.communications.twilio.voice.sip-outbound'), [
        'CallSid' => 'CAoutbound001',
        'From' => 'sip:101@example.sip.us1.twilio.com',
        'To' => '+17195551234',
        'CallStatus' => 'ringing',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertSee('<Dial callerId="+17195550100"', false)
        ->assertSee('<Number>+17195551234</Number>', false)
        ->assertSee(route('webhooks.communications.twilio.voice.status'), false);

    $session = CallSession::query()->where('provider_call_sid', 'CAoutbound001')->first();

    expect($session)->not->toBeNull()
        ->and($session->direction)->toBe(CallSessionDirection::Outbound)
        ->and($session->customer_id)->toBe($customer->id)
        ->and($session->normalized_to)->toBe('7195551234')
        ->and($session->owned_by_user_id)->toBe($advisor->id)
        ->and($session->status)->toBe(CallSessionStatus::Ringing);
});

test('sip outbound parses sip uri dialed numbers', function () {
    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => '101@example.sip.us1.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.sip-outbound'), [
        'CallSid' => 'CAoutbound002',
        'From' => 'sip:101@example.sip.us1.twilio.com',
        'To' => 'sip:+17195559876@example.sip.us1.twilio.com',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('<Number>+17195559876</Number>', false);
});

test('sip outbound rejects unknown sip endpoints', function () {
    $this->post(route('webhooks.communications.twilio.voice.sip-outbound'), [
        'CallSid' => 'CAoutbound003',
        'From' => 'sip:999@example.sip.us1.twilio.com',
        'To' => '+17195551234',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('not authorized for outbound calling', false);

    expect(CallSession::query()->where('provider_call_sid', 'CAoutbound003')->value('direction'))
        ->toBe(CallSessionDirection::Outbound);
});

test('sip outbound stores long sip endpoint uris from production twilio domains', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $fromUri = 'sip:desk1@example.sip.us1.twilio.com';
    $toUri = 'sip:719@example.sip.us1.twilio.com;transport=tcp';

    TelephonyEndpoint::query()->create([
        'name' => 'Desk 1 SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => $fromUri,
        'user_id' => $advisor->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.sip-outbound'), [
        'CallSid' => 'CAff9e96f7f6041f739bc71f62f85bcca3',
        'From' => $fromUri,
        'To' => $toUri,
        'CallStatus' => 'ringing',
        'CallerName' => 'desk1',
    ])->assertOk()
        ->assertSee('<Number>+719</Number>', false);

    $session = CallSession::query()->where('provider_call_sid', 'CAff9e96f7f6041f739bc71f62f85bcca3')->first();

    expect($session)->not->toBeNull()
        ->and($session->from_number)->toBe($fromUri)
        ->and($session->normalized_from)->toBe($fromUri)
        ->and($session->normalized_to)->toBe('719')
        ->and($session->direction)->toBe(CallSessionDirection::Outbound);
});

test('sip outbound requires shop caller id number in settings', function () {
    ShopSettings::current()->update(['telephony_inbound_number' => null]);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@example.sip.us1.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.sip-outbound'), [
        'CallSid' => 'CAoutbound004',
        'From' => 'sip:101@example.sip.us1.twilio.com',
        'To' => '+17195551234',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('Save your shop Twilio number in Settings', false);
});
