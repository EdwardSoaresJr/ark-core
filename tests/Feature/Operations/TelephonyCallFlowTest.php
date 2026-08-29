<?php

use App\Ark\Mobile\MobileDevice;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceIdentity;
use App\Ark\Operations\Telephony\StaffCallPresence;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Telephony\TelephonyRingSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);

    ShopSettings::current()->update([
        'telephony_call_flow' => ShopSettings::defaultTelephonyCallFlow(),
    ]);

    TelephonyEndpoint::query()->delete();
});

function configureTwilioClientForMobileRing(): void
{
    config()->set('services.twilio.account_sid', 'AC-test-account');
    config()->set('services.twilio.api_key_sid', 'SK-test-key');
    config()->set('services.twilio.api_key_secret', 'test-api-secret');
    config()->set('services.twilio.voice_twiml_app_sid', 'AP-test-app');
    config()->set('services.twilio.apns_voip_credential_sid', 'CR-test-apns');
    config()->set('services.twilio.fcm_credential_sid', 'CR-test-fcm');

    ShopSettings::current()->update([
        'telephony_provider' => TelephonyProviderType::Twilio->value,
        'twilio_account_sid' => 'AC-test-account',
        'twilio_auth_token' => 'test-auth-token',
        'twilio_api_key_sid' => 'SK-test-key',
        'twilio_api_key_secret' => 'test-api-secret',
        'twilio_voice_twiml_app_sid' => 'AP-test-app',
        'twilio_apns_voip_credential_sid' => 'CR-test-apns',
        'twilio_fcm_credential_sid' => 'CR-test-fcm',
    ]);
}

function createVoiceReadyMobileDevice(
    User $user,
    string $platform = 'ios',
    string $deviceName = 'Edward iPhone',
    mixed $voiceReadyAt = null,
): MobileDevice {
    $device = MobileDevice::query()->create([
        'user_id' => $user->id,
        'device_name' => $deviceName,
        'platform' => $platform,
        'voip_push_token' => $platform === 'ios' || $platform === 'ipados' ? 'voip-token-test' : null,
        'fcm_token' => $platform === 'android' ? 'fcm-token-test' : null,
        'last_seen_at' => now(),
        'voice_ready_at' => $voiceReadyAt ?? now(),
    ]);

    TelephonyEndpoint::query()->updateOrCreate(
        [
            'type' => TelephonyEndpointType::MobileApp,
            'destination' => MobileVoiceIdentity::fromDevice($device),
        ],
        [
            'name' => 'Mobile · '.$deviceName,
            'user_id' => $user->id,
            'enabled' => true,
            'position' => 999,
        ],
    );

    return $device->fresh();
}

test('closed shop routes inbound callers directly to voicemail', function () {
    $closedFlow = ShopSettings::defaultTelephonyCallFlow();
    $closedFlow['weekly_hours']['monday']['enabled'] = false;
    ShopSettings::current()->update(['telephony_call_flow' => $closedFlow]);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@example.com',
        'enabled' => true,
        'position' => 0,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 10:00:00', 'America/Denver'));

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAclosed01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('<Record', false)
        ->assertDontSee('<Dial', false);

    CarbonImmutable::setTestNow();
});

test('listed test numbers bypass closed hours and ring the shop', function () {
    $closedFlow = ShopSettings::defaultTelephonyCallFlow();
    $closedFlow['weekly_hours']['monday']['enabled'] = false;
    $closedFlow['hours_bypass_numbers'] = ['7195551000'];
    ShopSettings::current()->update(['telephony_call_flow' => $closedFlow]);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@example.com',
        'enabled' => true,
        'position' => 0,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 10:00:00', 'America/Denver'));

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAbypass01',
        'From' => '+17195551000',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('<Dial', false)
        ->assertDontSee('We are currently closed', false);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAclosed02',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('<Record', false)
        ->assertDontSee('<Dial', false);

    CarbonImmutable::setTestNow();
});

test('open shop plays disclaimer and dials enabled endpoints with recording', function () {
    $edward = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $edward->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'enabled' => true,
        'position' => 0,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAopen01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('This call may be recorded', false)
        ->assertSee('record="record-from-answer"', false)
        ->assertSee('ringTone="us"', false)
        ->assertSee('answerOnBridge="true"', false)
        ->assertSee('+17195551001</Number>', false)
        ->assertSee(route('webhooks.communications.twilio.voice.dial-complete'), false);

    CarbonImmutable::setTestNow();
});

test('presence based cell endpoints only ring when staff is logged in', function () {
    $staff = User::factory()->create(['phone' => '7195551002']);

    TelephonyEndpoint::query()->create([
        'name' => 'Molly Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $staff->id,
        'ring_schedule' => TelephonyRingSchedule::WhenPresent,
        'enabled' => true,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@example.com',
        'enabled' => true,
        'position' => 1,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CApresent01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertDontSee('+17195551002</Number>', false)
        ->assertSee('sip:101@example.com</Sip>', false);

    app(StaffCallPresence::class)->markPresent($staff);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CApresent02',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('+17195551002</Number>', false);

    CarbonImmutable::setTestNow();
});

test('holiday closure overrides weekday hours', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'closed_dates' => ['2026-06-16'],
        ]),
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));

    expect(TelephonyCallFlowSettings::fromShopSettings()->isOpenAt())->toBeFalse();

    CarbonImmutable::setTestNow();
});

test('dial complete webhook marks parent call session completed', function () {
    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAparent01',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'answered',
        'started_at' => now()->subMinute(),
        'answered_at' => now()->subMinute(),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initializeParallel('CAparent01');
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->markAnswered('CAparent01', 1);

    $this->post(route('webhooks.communications.twilio.voice.dial-complete'), [
        'CallSid' => 'CAparent01',
        'DialCallStatus' => 'completed',
    ])->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=utf-8')
        ->assertSee('<Hangup/>', false);

    $session->refresh();

    expect($session->status->value)->toBe('completed')
        ->and($session->ended_at)->not->toBeNull();
});

test('dial complete routes unanswered completed dial to voicemail', function () {
    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAparent02',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'ringing',
        'started_at' => now()->subSeconds(20),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initializeParallel('CAparent02');
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->markCellScreening('CAparent02', 9);

    $this->post(route('webhooks.communications.twilio.voice.dial-complete'), [
        'CallSid' => 'CAparent02',
        'DialCallStatus' => 'completed',
    ])->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=utf-8')
        ->assertSee('<Record', false)
        ->assertDontSee('<Hangup/>', false);
});

test('dial complete ignores parent answered_at during cell screening', function () {
    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAparent03',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'answered',
        'started_at' => now()->subSeconds(20),
        'answered_at' => now()->subSeconds(15),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initializeParallel('CAparent03');
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->markCellScreening('CAparent03', 9);

    $this->post(route('webhooks.communications.twilio.voice.dial-complete'), [
        'CallSid' => 'CAparent03',
        'DialCallStatus' => 'completed',
    ])->assertOk()
        ->assertSee('<Record', false)
        ->assertDontSee('<Hangup/>', false);
});

test('recording webhook stores recording metadata on call session', function () {
    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CArecord01',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'normalized_to' => '7195559999',
        'status' => 'completed',
        'started_at' => now(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.recording'), [
        'CallSid' => 'CArecord01',
        'RecordingSid' => 'RE123',
        'RecordingUrl' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/RE123',
        'RecordingDuration' => '42',
    ])->assertNoContent();

    $session->refresh();

    expect($session->recording_url)->toContain('RE123')
        ->and($session->recording_duration_seconds)->toBe(42);
});

test('inbound calls skip recording and disclaimer when inbound recording is disabled', function () {
    $edward = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $edward->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'enabled' => true,
        'position' => 0,
    ]);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'record_inbound_calls' => false,
        ]),
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAnorec01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertDontSee('This call may be recorded', false)
        ->assertDontSee('record="record-from-answer"', false)
        ->assertSee('+17195551001</Number>', false);

    CarbonImmutable::setTestNow();
});

test('sip outbound twiml includes recording disclaimer and record attribute', function () {
    TelephonyEndpoint::query()->create([
        'name' => 'Desk1',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.us1.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'AC-test');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);

    $this->post(route('webhooks.communications.twilio.voice.sip-outbound'), [
        'CallSid' => 'CAoutrec01',
        'From' => 'sip:desk1@example.sip.us1.twilio.com',
        'To' => '7195550101',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('This call may be recorded', false)
        ->assertSee('record="record-from-answer"', false);
});

test('cell whisper prompt uses custom text or shop name fallback', function () {
    ShopSettings::current()->update([
        'shop_name' => "Lug's N Plugs",
        'telephony_call_flow' => ShopSettings::defaultTelephonyCallFlow(),
    ]);

    expect(TelephonyCallFlowSettings::fromShopSettings()->cellWhisperPrompt())->toBe("Call for Lug's N Plugs");

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'cell_whisper_prompt' => 'Shop line ringing',
        ]),
    ]);

    expect(TelephonyCallFlowSettings::fromShopSettings()->cellWhisperPrompt())->toBe('Shop line ringing');
});

test('owned popup timeout defaults to eight seconds and clamps configured values', function () {
    expect(TelephonyCallFlowSettings::fromShopSettings()->ownedPopupTimeoutSeconds())->toBe(8);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'owned_popup_timeout_seconds' => 2,
        ]),
    ]);

    expect(TelephonyCallFlowSettings::fromShopSettings()->ownedPopupTimeoutSeconds())->toBe(3);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'owned_popup_timeout_seconds' => 90,
        ]),
    ]);

    expect(TelephonyCallFlowSettings::fromShopSettings()->ownedPopupTimeoutSeconds())->toBe(60);
});

test('voice-ready mobile app endpoint rings as Client alongside Sip and cell', function () {
    configureTwilioClientForMobileRing();

    $staff = User::factory()->create();
    $device = createVoiceReadyMobileDevice($staff, platform: 'ios');
    $identity = MobileVoiceIdentity::fromDevice($device);

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

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));
    $device->forceFill(['voice_ready_at' => now()])->save();

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAmobileclient01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('sip:wp820@example.sip.us1.twilio.com</Sip>', false)
        ->assertSee('+17195550199</Number>', false)
        ->assertSee('<Client', false)
        ->assertSee($identity.'</Client>', false);

    expect(CallSession::query()->where('provider_call_sid', 'CAmobileclient01')->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
});

test('stale or disabled mobile app endpoints are omitted from inbound ring', function () {
    configureTwilioClientForMobileRing();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));

    $presentStaff = User::factory()->create();
    $staleStaff = User::factory()->create();

    $readyDevice = createVoiceReadyMobileDevice($presentStaff, platform: 'ios', deviceName: 'Ready Phone');
    $staleDevice = createVoiceReadyMobileDevice(
        $staleStaff,
        platform: 'ios',
        deviceName: 'Stale Phone',
        voiceReadyAt: CarbonImmutable::parse('2026-06-16 08:00:00', 'America/Denver'),
    );
    $disabledDevice = createVoiceReadyMobileDevice($presentStaff, platform: 'ios', deviceName: 'Disabled Phone');

    TelephonyEndpoint::query()
        ->where('destination', MobileVoiceIdentity::fromDevice($disabledDevice))
        ->update(['enabled' => false]);

    TelephonyEndpoint::query()->create([
        'name' => 'WP820 WiFi Phone',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:wp820@example.sip.us1.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    expect(app(\App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar::class)
        ->deviceHasRecentVoiceReady($staleDevice->fresh()))->toBeFalse();

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAmobileskip01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('sip:wp820@example.sip.us1.twilio.com</Sip>', false)
        ->assertSee(MobileVoiceIdentity::fromDevice($readyDevice).'</Client>', false)
        ->assertDontSee(MobileVoiceIdentity::fromDevice($staleDevice).'</Client>', false)
        ->assertDontSee(MobileVoiceIdentity::fromDevice($disabledDevice).'</Client>', false);

    CarbonImmutable::setTestNow();
});

test('missing twiml app sid omits Client while Sip and Number keep ringing', function () {
    configureTwilioClientForMobileRing();

    ShopSettings::current()->forceFill(['twilio_voice_twiml_app_sid' => ''])->save();
    config()->set('services.twilio.voice_twiml_app_sid', null);
    ShopSettings::forgetCurrent();

    expect(\App\Ark\Operations\Telephony\MobileVoice\MobileVoiceCredentials::forCurrentShop()->twilioClientConfigured())->toBeFalse();

    $staff = User::factory()->create();
    $device = createVoiceReadyMobileDevice($staff, platform: 'ios');
    $identity = MobileVoiceIdentity::fromDevice($device);

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

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));
    $device->forceFill(['voice_ready_at' => now()])->save();

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAmobilefailtwiml01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('sip:wp820@example.sip.us1.twilio.com</Sip>', false)
        ->assertSee('+17195550199</Number>', false)
        ->assertDontSee('<Client', false)
        ->assertDontSee($identity.'</Client>', false);

    CarbonImmutable::setTestNow();
});

test('missing ios voip push credential omits Client while Sip keeps ringing', function () {
    configureTwilioClientForMobileRing();

    ShopSettings::current()->forceFill(['twilio_apns_voip_credential_sid' => ''])->save();
    config()->set('services.twilio.apns_voip_credential_sid', null);
    ShopSettings::forgetCurrent();

    expect(\App\Ark\Operations\Telephony\MobileVoice\MobileVoiceCredentials::forCurrentShop()->inboundPushConfiguredForPlatform('ios'))->toBeFalse();

    $staff = User::factory()->create();
    $device = createVoiceReadyMobileDevice($staff, platform: 'ios');
    $identity = MobileVoiceIdentity::fromDevice($device);

    TelephonyEndpoint::query()->create([
        'name' => 'WP820 WiFi Phone',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:wp820@example.sip.us1.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));
    $device->forceFill(['voice_ready_at' => now()])->save();

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAmobilefailpush01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('sip:wp820@example.sip.us1.twilio.com</Sip>', false)
        ->assertDontSee('<Client', false)
        ->assertDontSee($identity.'</Client>', false);

    CarbonImmutable::setTestNow();
});

test('client identity matches ark-mobile user and device ids', function () {
    configureTwilioClientForMobileRing();

    $staff = User::factory()->create();
    $device = createVoiceReadyMobileDevice($staff, platform: 'ios');

    expect(MobileVoiceIdentity::fromDevice($device))->toBe('ark-mobile:'.$staff->id.':'.$device->id);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));
    $device->forceFill(['voice_ready_at' => now()])->save();

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAmobileidentity01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('<Client', false)
        ->assertSee('ark-mobile:'.$staff->id.':'.$device->id.'</Client>', false);

    CarbonImmutable::setTestNow();
});
