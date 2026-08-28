<?php

use App\Ark\Mobile\RegisterMobileDeviceAction;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    config()->set('services.twilio.auth_token', 'test-auth-token');
    config()->set('services.twilio.account_sid', 'AC-test-account');
    config()->set('services.twilio.api_key_sid', 'SK-test-key');
    config()->set('services.twilio.api_key_secret', 'test-api-secret');
    config()->set('services.twilio.voice_twiml_app_sid', 'AP-test-app');
    config()->set('services.twilio.fcm_credential_sid', 'CR-test-fcm');
    config()->set('services.twilio.apns_voip_credential_sid', 'CR-test-apns');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
        'telephony_provider' => TelephonyProviderType::Twilio->value,
        'twilio_account_sid' => 'AC-test-account',
        'twilio_auth_token' => 'test-auth-token',
        'twilio_api_key_sid' => 'SK-test-key',
        'twilio_api_key_secret' => 'test-api-secret',
        'twilio_voice_twiml_app_sid' => 'AP-test-app',
        'twilio_fcm_credential_sid' => 'CR-test-fcm',
        'twilio_apns_voip_credential_sid' => 'CR-test-apns',
    ]);
});

test('mobile me projects in_app dial method when twilio voice is ready', function (): void {
    $advisor = User::factory()->create(['phone' => '7195551200'])->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('Lenovo Tablet')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $advisor,
        deviceName: 'Lenovo Tablet',
        platform: 'android',
        fcmToken: 'fcm-test-token',
    );

    $this->withToken($token)
        ->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('telephony.dial_method', 'in_app')
        ->assertJsonPath('telephony.voice.in_app_ready', true)
        ->assertJsonPath('telephony.voice.transport', 'twilio')
        ->assertJsonPath('telephony.voice.fallback', 'shop_callback');
});

test('advisor can refresh twilio voice session token', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('Pixel Phone')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $advisor,
        deviceName: 'Pixel Phone',
        platform: 'android',
    );

    $first = $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-session')
        ->assertOk()
        ->json('session.access_token');

    $second = $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-session')
        ->assertOk()
        ->json('session.access_token');

    expect($second)->toBe($first);
});

test('twilio voice session response shape', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('Pixel Phone')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $advisor,
        deviceName: 'Pixel Phone',
        platform: 'android',
    );

    $response = $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-session')
        ->assertOk()
        ->assertJsonStructure([
            'session' => [
                'transport',
                'identity',
                'access_token',
                'expires_in',
                'supports_inbound',
                'endpoint_id',
            ],
        ]);

    expect($response->json('session.transport'))->toBe('twilio')
        ->and($response->json('session.identity'))->toStartWith('ark-mobile:');

    expect(TelephonyEndpoint::query()
        ->where('type', TelephonyEndpointType::MobileApp)
        ->where('user_id', $advisor->id)
        ->exists())->toBeTrue();
});

test('advisor can start twilio voice connect payload for customer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('Pixel Phone')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $advisor,
        deviceName: 'Pixel Phone',
        platform: 'android',
    );

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'InApp',
        'last_name' => 'Caller',
        'phone' => '7195558800',
    ]);

    $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-connect', [
            'customer_id' => $customer->id,
        ])
        ->assertOk()
        ->assertJsonStructure([
            'connect' => [
                'transport',
                'identity',
                'access_token',
                'connect_token',
                'customer_e164',
                'params',
            ],
        ])
        ->assertJsonPath('connect.transport', 'twilio')
        ->assertJsonPath('connect.customer_e164', '+17195558800');
});

test('voice connect accepts shop repair order number', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('Pixel Phone')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $advisor,
        deviceName: 'Pixel Phone',
        platform: 'android',
    );

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Shop',
        'last_name' => 'Number',
        'phone' => '7195558811',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'SHOP1',
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => '2HGFC2F59KH123456',
    ]);

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => 8812,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::InProgress,
        'concern_summary' => 'Oil change due.',
    ]);

    $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-connect', [
            'customer_id' => $customer->id,
            'repair_order_id' => $repairOrder->repair_order_id,
        ])
        ->assertOk()
        ->assertJsonPath('connect.transport', 'twilio');
});

test('ios voice session is blocked when voip push credential is missing', function (): void {
    ShopSettings::current()->update(['twilio_apns_voip_credential_sid' => null]);
    config()->set('services.twilio.apns_voip_credential_sid', null);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('iPhone Floor')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $advisor,
        deviceName: 'iPhone Floor',
        platform: 'ios',
        voipPushToken: 'voip-token-test',
    );

    $this->withToken($token)
        ->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('telephony.voice.in_app_ready', false)
        ->assertJsonPath('telephony.voice.transport', 'twilio');

    expect($this->withToken($token)->getJson('/api/mobile/me')->json('telephony.voice.block_reason'))
        ->toContain('iOS VoIP push credential');
});

test('voice session jwt includes identity and inbound support when configured', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('iPhone Floor')->plainTextToken;

    $device = app(RegisterMobileDeviceAction::class)->execute(
        user: $advisor,
        deviceName: 'iPhone Floor',
        platform: 'ios',
        voipPushToken: 'voip-token-test',
    );

    $response = $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-session')
        ->assertOk();

    expect($response->json('session.identity'))->toBe('ark-mobile:'.$advisor->id.':'.$device->id)
        ->and($response->json('session.supports_inbound'))->toBeTrue()
        ->and($response->json('session.transport'))->toBe('twilio')
        ->and($response->json('session.access_token'))->not->toBeEmpty();
});

test('mobile voice lifecycle events accept structured client evidence', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('iPhone Floor Test')->plainTextToken;

    Log::spy();

    $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-registration-event', [
            'phase' => 'lifecycle',
            'category' => 'wss',
            'event' => 'disconnected',
            'client_ts' => '2026-07-03T17:55:14.123Z',
            'extension' => '8105',
            'detail' => 'WebSocket closed',
            'build_mode' => 'release',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('mobile.voice.lifecycle', Mockery::on(function (array $context): bool {
            return ($context['extension'] ?? '') === '8105'
                && ($context['event'] ?? '') === 'disconnected'
                && ($context['category'] ?? '') === 'wss'
                && ($context['build_mode'] ?? '') === 'release';
        }));
});
