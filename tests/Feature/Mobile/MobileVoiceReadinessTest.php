<?php

use App\Ark\Mobile\MobileDevice;
use App\Ark\Mobile\RegisterMobileDeviceAction;
use App\Ark\Operations\Communications\CommunicationsShopProjection;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceIdentity;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Telephony\TelephonyRingSchedule;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
    config()->set('services.twilio.account_sid', 'AC-test-account');
    config()->set('services.twilio.api_key_sid', 'SK-test-key');
    config()->set('services.twilio.api_key_secret', 'test-api-secret');
    config()->set('services.twilio.voice_twiml_app_sid', 'AP-test-app');
    config()->set('services.twilio.apns_voip_credential_sid', 'CR-test-apns');
    config()->set('services.twilio.fcm_credential_sid', 'CR-test-fcm');

    ShopSettings::current()->update([
        'telephony_call_flow' => ShopSettings::defaultTelephonyCallFlow(),
        'telephony_provider' => TelephonyProviderType::Twilio->value,
        'twilio_account_sid' => 'AC-test-account',
        'twilio_auth_token' => 'test-auth-token',
        'twilio_api_key_sid' => 'SK-test-key',
        'twilio_api_key_secret' => 'test-api-secret',
        'twilio_voice_twiml_app_sid' => 'AP-test-app',
        'twilio_apns_voip_credential_sid' => 'CR-test-apns',
        'twilio_fcm_credential_sid' => 'CR-test-fcm',
    ]);

    TelephonyEndpoint::query()->delete();

    // Shop hours are weekday-open by default; freeze to an open moment.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('generic device registration alone does not make MobileApp ring-eligible', function (): void {
    $staff = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $staff->createToken('Edward iPhone')->plainTextToken;

    TelephonyEndpoint::query()->create([
        'name' => 'WP820 WiFi Phone',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:wp820@example.sip.us1.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Primary cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195550199',
        'ring_schedule' => TelephonyRingSchedule::Always,
        'enabled' => true,
        'position' => 1,
    ]);

    $this->withToken($token)
        ->postJson('/api/mobile/device', [
            'device_name' => 'Edward iPhone',
            'platform' => 'ios',
            'fcm_token' => 'fcm-only-token',
        ])
        ->assertOk();

    $device = MobileDevice::query()->where('user_id', $staff->id)->firstOrFail();
    $identity = MobileVoiceIdentity::fromDevice($device);

    expect($device->voice_ready_at)->toBeNull()
        ->and(TelephonyEndpoint::query()
            ->where('type', TelephonyEndpointType::MobileApp)
            ->where('destination', $identity)
            ->where('enabled', true)
            ->exists())->toBeTrue();

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAdeviceonly01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('sip:wp820@example.sip.us1.twilio.com</Sip>', false)
        ->assertSee('+17195550199</Number>', false)
        ->assertDontSee($identity.'</Client>', false)
        ->assertDontSee('<Client', false);
});

test('recent voice_ready marks MobileApp ring-eligible and coverage available', function (): void {
    $staff = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);
    $token = $staff->createToken('Edward iPhone')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $staff,
        deviceName: 'Edward iPhone',
        platform: 'ios',
        fcmToken: 'fcm-token',
        voipPushToken: 'voip-token',
    );

    $device = MobileDevice::query()->where('user_id', $staff->id)->firstOrFail();
    $identity = MobileVoiceIdentity::fromDevice($device);

    $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-registration-event', [
            'phase' => 'voice_ready',
            'message' => 'Twilio Client voice endpoint registered.',
            'device_name' => 'Edward iPhone',
        ])
        ->assertOk();

    $device->refresh();
    expect($device->voice_ready_at)->not->toBeNull();

    expect(app(MobileVoiceEndpointRegistrar::class)->isEndpointVoiceReady(
        TelephonyEndpoint::query()->where('destination', $identity)->firstOrFail()
    ))->toBeTrue();

    TelephonyEndpoint::query()->create([
        'name' => 'WP820 WiFi Phone',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:wp820@example.sip.us1.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAvoiceready01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee($identity.'</Client>', false)
        ->assertSee('sip:wp820@example.sip.us1.twilio.com</Sip>', false);

    $coverage = CommunicationsShopProjection::forCurrentShop()->resolve()['coverage'];
    $row = collect($coverage)->first(fn ($item) => $item->name === 'Alex Rivera');
    expect($row)->not->toBeNull()
        ->and($row->summary)->toBe('Available');
});

test('stale voice_ready does not ring MobileApp', function (): void {
    $staff = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    // Absolute Denver wall time — avoid SQLite timezone reinterpretation of relative now().
    $staleAt = CarbonImmutable::parse('2026-06-16 08:00:00', 'America/Denver');

    $device = MobileDevice::query()->create([
        'user_id' => $staff->id,
        'device_name' => 'Edward iPhone',
        'platform' => 'ios',
        'voip_push_token' => 'voip-token',
        'last_seen_at' => CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'),
        'voice_ready_at' => $staleAt,
    ]);

    $identity = MobileVoiceIdentity::fromDevice($device);

    TelephonyEndpoint::query()->create([
        'name' => 'Mobile · stale ready',
        'type' => TelephonyEndpointType::MobileApp,
        'destination' => $identity,
        'user_id' => $staff->id,
        'enabled' => true,
        'position' => 0,
        'presence_timeout_minutes' => 30,
    ]);

    expect(app(MobileVoiceEndpointRegistrar::class)->deviceHasRecentVoiceReady($device->fresh(), 30))->toBeFalse();

    TelephonyEndpoint::query()->create([
        'name' => 'WP820 WiFi Phone',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:wp820@example.sip.us1.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAstaleready01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('sip:wp820@example.sip.us1.twilio.com</Sip>', false)
        ->assertDontSee($identity.'</Client>', false);
});

test('logout clears voice readiness and disables MobileApp endpoint', function (): void {
    $staff = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);
    $token = $staff->createToken('Edward iPhone')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $staff,
        deviceName: 'Edward iPhone',
        platform: 'ios',
    );

    $device = MobileDevice::query()->where('user_id', $staff->id)->firstOrFail();
    app(MobileVoiceEndpointRegistrar::class)->markVoiceReady($device);

    $this->withToken($token)
        ->postJson('/api/mobile/auth/logout')
        ->assertOk();

    $device->refresh();
    expect($device->voice_ready_at)->toBeNull()
        ->and(TelephonyEndpoint::query()
            ->where('user_id', $staff->id)
            ->where('type', TelephonyEndpointType::MobileApp)
            ->where('enabled', true)
            ->exists())->toBeFalse();

    $coverage = CommunicationsShopProjection::forCurrentShop()->resolve()['coverage'];
    $row = collect($coverage)->first(fn ($item) => $item->name === 'Alex Rivera');
    expect($row)->not->toBeNull()
        ->and($row->summary)->toBe('Offline');
});

test('unregister phase clears voice readiness without requiring StaffCallPresence', function (): void {
    $staff = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $staff->createToken('Edward iPhone')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $staff,
        deviceName: 'Edward iPhone',
        platform: 'ios',
    );

    $device = MobileDevice::query()->where('user_id', $staff->id)->firstOrFail();
    app(MobileVoiceEndpointRegistrar::class)->markVoiceReady($device);

    $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-registration-event', [
            'phase' => 'unregistered',
            'message' => 'Twilio transport disposed.',
            'device_name' => 'Edward iPhone',
        ])
        ->assertOk();

    $device->refresh();
    expect($device->voice_ready_at)->toBeNull();
});
