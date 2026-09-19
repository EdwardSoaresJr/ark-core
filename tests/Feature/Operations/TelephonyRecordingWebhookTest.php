<?php

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionMediaCaptureStatus;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
    config()->set('services.twilio.account_sid', null);
});

test('recording webhook attaches media using parent call sid', function () {
    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CAparentrec01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(3),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.recording'), [
        'CallSid' => 'CAchildleg01',
        'ParentCallSid' => 'CAparentrec01',
        'RecordingSid' => 'REaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'RecordingUrl' => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/REaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'RecordingDuration' => '12',
        'RecordingStatus' => 'completed',
    ])->assertNoContent();

    $session->refresh();

    expect($session->recording_sid)->toBe('REaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
        ->and($session->recording_url)->toContain('REaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
        ->and($session->recording_capture_status)->toBe(CallSessionMediaCaptureStatus::Available)
        ->and($session->answered_at)->not->toBeNull();
});

test('recording webhook returns 404 when the call session is unknown so Twilio retries', function () {
    $this->post(route('webhooks.communications.twilio.voice.recording'), [
        'CallSid' => 'CAmissingrec01',
        'RecordingSid' => 'REbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        'RecordingUrl' => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/REbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        'RecordingDuration' => '8',
        'RecordingStatus' => 'completed',
    ])->assertNotFound();
});

test('recording webhook records failure instead of silent success when completed without a url', function () {
    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CAemptyurl01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinute(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.recording'), [
        'CallSid' => 'CAemptyurl01',
        'RecordingSid' => 'REcccccccccccccccccccccccccccccccc',
        'RecordingUrl' => '',
        'RecordingDuration' => '0',
        'RecordingStatus' => 'completed',
    ])->assertNoContent();

    $session->refresh();

    expect($session->recording_url)->toBeNull()
        ->and($session->recording_capture_status)->toBe(CallSessionMediaCaptureStatus::Failed)
        ->and($session->recording_capture_error)->not->toBeNull();
});

test('backfill command attaches twilio recordings onto matching call sessions', function () {
    config()->set('services.twilio.account_sid', 'ACtest');
    config()->set('services.twilio.auth_token', 'secret');

    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CAbackfill01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subHour(),
    ]);

    Http::fake([
        'https://api.twilio.com/2010-04-01/Accounts/ACtest/Recordings.json*' => Http::response([
            'recordings' => [[
                'sid' => 'REdddddddddddddddddddddddddddddddd',
                'call_sid' => 'CAbackfill01',
                'duration' => '22',
                'status' => 'completed',
            ]],
            'next_page_uri' => null,
        ], 200),
    ]);

    $this->artisan('ark:telephony:backfill-recordings', ['--since' => '2026-09-16 00:00:00'])
        ->assertSuccessful();

    $session->refresh();

    expect($session->recording_sid)->toBe('REdddddddddddddddddddddddddddddddd')
        ->and($session->recording_url)->toContain('REdddddddddddddddddddddddddddddddd')
        ->and($session->answered_at)->not->toBeNull()
        ->and($session->status)->toBe(CallSessionStatus::Completed);
});
