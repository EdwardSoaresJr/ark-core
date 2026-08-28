<?php

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyProgrammableVoiceGuard;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
});

test('programmable voice guard is always active after twilio native reset', function (): void {
    ShopSettings::current()->update([
        'telephony_provider' => 'asterisk',
    ]);

    expect(TelephonyProgrammableVoiceGuard::isActive())->toBeTrue();
});

test('inbound twilio webhook creates call session on shared authority path', function (): void {
    $response = $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CA-twilio-native-inbound',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertDontSee('<Hangup/>', false);

    $session = CallSession::query()->where('provider_call_sid', 'CA-twilio-native-inbound')->first();

    expect($session)->not->toBeNull()
        ->and($session->provider)->toBe(TelephonyProviderType::Twilio)
        ->and($session->normalized_from)->toBe('7195551234');
});

test('missed call status maps to missed_call timeline kind', function (): void {
    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-missed-native',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'direction' => CallSessionDirection::Inbound,
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $entries = app(UnifiedOperationalTimeline::class)->forCallSession($session);

    expect($entries)->not->toBeEmpty()
        ->and($entries[0]->kind)->toBe(OperationalEventKind::MissedCall);
});

test('recording webhook stores recording url on call session', function (): void {
    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-recording-native',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'direction' => CallSessionDirection::Inbound,
        'status' => CallSessionStatus::Completed,
        'started_at' => now(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.recording'), [
        'CallSid' => 'CA-recording-native',
        'RecordingUrl' => 'https://api.twilio.com/recording.wav',
        'RecordingSid' => 'RE123',
        'RecordingDuration' => '42',
    ])->assertNoContent();

    $session->refresh();

    expect($session->recording_url)->toBe('https://api.twilio.com/recording.wav')
        ->and($session->recording_sid)->toBe('RE123');
});

test('voicemail webhook stores voicemail url on call session', function (): void {
    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-voicemail-native',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'direction' => CallSessionDirection::Inbound,
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.voicemail'), [
        'CallSid' => 'CA-voicemail-native',
        'RecordingUrl' => 'https://api.twilio.com/voicemail.wav',
        'RecordingSid' => 'REvm123',
        'RecordingDuration' => '18',
    ])->assertOk();

    $session->refresh();

    expect($session->voicemail_url)->toBe('https://api.twilio.com/voicemail.wav')
        ->and($session->voicemail_sid)->toBe('REvm123');
});

test('sms and call share unified timeline for phone conversation', function (): void {
    app(ConversationRecorder::class)->recordInboundSms(
        normalizedPhone: '7195551234',
        body: 'Need an oil change',
        providerMessageSid: 'SM-timeline-native',
    );

    $conversation = Conversation::query()
        ->where('contact_surface', ConversationContactSurface::Phone)
        ->where('contact_address', '7195551234')
        ->firstOrFail();

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-timeline-native',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'direction' => CallSessionDirection::Inbound,
        'status' => CallSessionStatus::Completed,
        'started_at' => now(),
    ]);

    $entries = app(UnifiedOperationalTimeline::class)->forConversationRelationship($conversation);

    $kinds = collect($entries)->map(fn ($entry) => $entry->kind->value)->all();

    expect($kinds)->toContain('sms', 'call');
});

test('asterisk call-events route no longer exists', function (): void {
    expect(Route::has('voice.call-events'))->toBeFalse();
});
