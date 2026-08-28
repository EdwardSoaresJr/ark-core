<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Mail::fake();
    config()->set('errors.report.enabled', true);
    config()->set('errors.report.email', 'alerts@example.test');
    config()->set('errors.report.queue', false);
    config()->set('errors.report.throttle_seconds', 0);
});

test('recording playback returns not found when twilio credentials are incomplete', function () {
    config()->set('services.twilio.account_sid', null);
    config()->set('services.twilio.auth_token', 'test-token-only');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CArec001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551001',
        'status' => CallSessionStatus::Completed,
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE123',
        'started_at' => now()->subHour(),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.telephony.call-sessions.recording', $session))
        ->assertNotFound();

    Mail::assertNothingSent();
});

test('communications queue omits recording playback link when twilio is incomplete', function () {
    config()->set('services.twilio.account_sid', null);
    config()->set('services.twilio.auth_token', 'test-token-only');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CArec002',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551002',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551002',
        'status' => CallSessionStatus::Completed,
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE456',
        'started_at' => now()->subMinutes(20),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk()
        ->assertJsonPath('recent_activity.0.has_recording', false)
        ->assertJsonPath('recent_activity.0.recording_url', null);
});

test('recording playback proxies twilio audio when credentials are complete', function () {
    config()->set('services.twilio.account_sid', 'AC123');
    config()->set('services.twilio.auth_token', 'secret');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CArec003',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551003',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551003',
        'status' => CallSessionStatus::Completed,
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE789',
        'started_at' => now()->subHour(),
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'https://api.twilio.com/*' => \Illuminate\Support\Facades\Http::response('audio-bytes', 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.telephony.call-sessions.recording', $session))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg');
});
