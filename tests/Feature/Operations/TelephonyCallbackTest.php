<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyCallbackStore;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

function twilioSignedPost(string $routeName, array $routeParams, array $payload): TestResponse
{
    $path = route($routeName, $routeParams, false);
    $url = rtrim((string) config('app.url'), '/').$path;
    ksort($payload);
    $data = $url;

    foreach ($payload as $key => $value) {
        if (! is_array($value)) {
            $data .= $key.(string) $value;
        }
    }

    $signature = base64_encode(hash_hmac('sha1', $data, 'test-token', true));

    return test()->post($path, $payload, ['X-Twilio-Signature' => $signature]);
}

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'AC-test');
    config()->set('broadcasting.default', 'null');
    TelephonyEndpoint::query()->delete();

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
        'twilio_account_sid' => 'AC-test',
        'twilio_auth_token' => 'test-token',
    ]);
});

test('advisor can start a callback that rings their sip then dials the customer', function () {
    $advisor = actingAsLearnCurrentAdvisor();

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

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'CAcallback001'], 201),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.callback'), [
            'customer_id' => $customer->id,
        ])
        ->assertOk()
        ->assertJsonPath('initiated', true);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/Calls.json')
            && ($request['To'] ?? '') === 'sip:101@example.sip.us1.twilio.com'
            && str_contains((string) ($request['Url'] ?? ''), 'callback-answer');
    });
});

test('callback marks queued caller handled when call session id is provided', function () {
    $advisor = actingAsLearnCurrentAdvisor();

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

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcallbackqueue01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195550100',
        'normalized_from' => '7195551234',
        'customer_id' => $customer->id,
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'CAcallback002'], 201),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.callback'), [
            'customer_id' => $customer->id,
            'call_session_id' => $session->id,
        ])
        ->assertOk()
        ->assertJsonPath('initiated', true);

    expect($session->fresh()->worked_at)->not->toBeNull();

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 0);
});

test('callback answer webhook dials the customer after the advisor answers', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $endpoint = TelephonyEndpoint::query()->create([
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

    $token = app(TelephonyCallbackStore::class)->issue(new \App\Ark\Operations\Telephony\TelephonyCallbackIntent(
        initiatedByUserId: $advisor->id,
        endpointId: $endpoint->id,
        customerE164: '+17195551234',
        normalizedCustomerPhone: '7195551234',
        customerId: $customer->id,
    ));

    twilioSignedPost('webhooks.communications.twilio.voice.callback-answer', ['token' => $token], [
        'CallSid' => 'CAcallbackAnswer01',
        'From' => '+17195550100',
        'To' => 'sip:101@example.sip.us1.twilio.com',
        'CallStatus' => 'in-progress',
    ])->assertOk()
        ->assertSee('<Dial callerId="+17195550100"', false)
        ->assertSee('<Number>+17195551234</Number>', false);

    $session = CallSession::query()->where('provider_call_sid', 'CAcallbackAnswer01')->first();

    expect($session)->not->toBeNull()
        ->and($session->direction)->toBe(CallSessionDirection::Outbound)
        ->and($session->customer_id)->toBe($customer->id)
        ->and($session->owned_by_user_id)->toBe($advisor->id)
        ->and($session->status)->toBe(CallSessionStatus::Answered);
});

test('callback answer webhook uses twilio provider', function () {
    ShopSettings::current()->update([
        'telephony_provider' => TelephonyProviderType::Twilio->value,
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $endpoint = TelephonyEndpoint::query()->create([
        'name' => 'Advisor Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7195551001',
        'user_id' => $advisor->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Jason',
        'last_name' => 'Miller',
        'phone' => '7195550199',
    ]);

    $token = app(TelephonyCallbackStore::class)->issue(new \App\Ark\Operations\Telephony\TelephonyCallbackIntent(
        initiatedByUserId: $advisor->id,
        endpointId: $endpoint->id,
        customerE164: '+17195550199',
        normalizedCustomerPhone: '7195550199',
        customerId: $customer->id,
    ));

    twilioSignedPost('webhooks.communications.twilio.voice.callback-answer', ['token' => $token], [
        'CallSid' => 'CA15cf19d40d70ef99c28fa2e9dd1fceb3',
        'From' => '+17194136227',
        'To' => '+17195550199',
        'CallStatus' => 'in-progress',
        'Direction' => 'outbound-api',
    ])->assertOk()
        ->assertSee('<Number>+17195550199</Number>', false);

    expect(CallSession::query()->where('provider_call_sid', 'CA15cf19d40d70ef99c28fa2e9dd1fceb3')->exists())->toBeTrue();
});

test('callback answer does not trigger incoming call popup broadcast', function () {
    config()->set('broadcasting.default', 'log');
    \Illuminate\Support\Facades\Event::fake([\App\Ark\Operations\Telephony\Events\IncomingCallReceived::class]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $endpoint = TelephonyEndpoint::query()->create([
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

    $token = app(TelephonyCallbackStore::class)->issue(new \App\Ark\Operations\Telephony\TelephonyCallbackIntent(
        initiatedByUserId: $advisor->id,
        endpointId: $endpoint->id,
        customerE164: '+17195551234',
        normalizedCustomerPhone: '7195551234',
        customerId: $customer->id,
    ));

    twilioSignedPost('webhooks.communications.twilio.voice.callback-answer', ['token' => $token], [
        'CallSid' => 'CAcallbackPopup01',
        'From' => '+17195550100',
        'To' => 'sip:101@example.sip.us1.twilio.com',
        'CallStatus' => 'in-progress',
    ])->assertOk();

    \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Ark\Operations\Telephony\Events\IncomingCallReceived::class);
});

test('callback answer webhook retries still dial the customer after token is consumed', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $endpoint = TelephonyEndpoint::query()->create([
        'name' => 'Advisor Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7195551001',
        'user_id' => $advisor->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '7195551234',
    ]);

    $token = app(TelephonyCallbackStore::class)->issue(new \App\Ark\Operations\Telephony\TelephonyCallbackIntent(
        initiatedByUserId: $advisor->id,
        endpointId: $endpoint->id,
        customerE164: '+17195551234',
        normalizedCustomerPhone: '7195551234',
        customerId: $customer->id,
    ));

    $payload = [
        'CallSid' => 'CAcallbackRetry01',
        'From' => '+17195550100',
        'To' => '+17195551001',
        'CallStatus' => 'in-progress',
    ];

    twilioSignedPost('webhooks.communications.twilio.voice.callback-answer', ['token' => $token], $payload)
        ->assertOk()
        ->assertSee('<Number>+17195551234</Number>', false)
        ->assertDontSee('expired', false);

    twilioSignedPost('webhooks.communications.twilio.voice.callback-answer', ['token' => $token], $payload)
        ->assertOk()
        ->assertSee('<Number>+17195551234</Number>', false)
        ->assertDontSee('expired', false);

    expect(CallSession::query()->where('provider_call_sid', 'CAcallbackRetry01')->count())->toBe(1);
});

test('advisor can start a callback that rings their cell endpoint', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    TelephonyEndpoint::query()->create([
        'name' => 'Advisor Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7195551001',
        'user_id' => $advisor->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '7195551234',
    ]);

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'CAcallbackCell01'], 201),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.callback'), [
            'customer_id' => $customer->id,
        ])
        ->assertOk()
        ->assertJsonPath('initiated', true);
});

test('advisor can start a callback using staff profile cell when no telephony endpoint exists', function () {
    $advisor = User::factory()->create([
        'phone' => '7195551002',
    ])->assignRole(ArkRole::Advisor->value);
    $advisor->syncRoles([ArkRole::Advisor->value]);

    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '7195551234',
    ]);

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'CAcallbackStaff01'], 201),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.callback'), [
            'customer_id' => $customer->id,
        ])
        ->assertOk()
        ->assertJsonPath('initiated', true);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/Calls.json')
            && ($request['To'] ?? '') === '+17195551002';
    });
});

test('staggered ring starts with immediate twiml dial instead of conference hold', function () {
    TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'enabled' => true,
        'ring_delay_seconds' => 0,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7195551001',
        'ring_schedule' => \App\Ark\Operations\Telephony\TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 15,
        'enabled' => true,
        'position' => 1,
    ]);

    $xml = \App\Ark\Operations\Telephony\TelephonyIncomingCallFlow::forCurrentShop()->buildResponse('CAstaggerWait01');

    expect($xml)
        ->toContain('<Sip')
        ->toContain('ringTone="us"')
        ->not->toContain('<Pause')
        ->not->toContain('<Conference');
});
