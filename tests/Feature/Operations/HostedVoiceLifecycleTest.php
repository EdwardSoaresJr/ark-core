<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\HostedCallOutcome;
use App\Ark\Operations\Telephony\Jobs\SendMissedCallRescueSmsJob;
use App\Ark\Operations\Telephony\SendMissedCallRescueSmsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('broadcasting.default', 'null');
    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Casey Auto Repair',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_shop_public_id' => (string) Str::uuid(),
        'cloud_credential' => 'test-credential-32-characters-min!!',
        'ark_mail_status' => 'connected',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('an answered hosted call keeps talk time and a normal recording', function () {
    $sid = 'CA-hosted-answered-short';
    $endedAt = Carbon::parse('2026-09-28 15:00:40');
    $answeredAt = $endedAt->copy()->subSeconds(26);

    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid, [
        'occurred_at' => '2026-09-28T15:00:00+00:00',
    ]))->assertOk();

    postHostedVoice('voice.incoming.answered', hostedVoicePayload($sid, [
        'outcome' => 'answered',
        'dial_duration_seconds' => 26,
        'answered_at' => $answeredAt->toIso8601String(),
        'occurred_at' => $endedAt->toIso8601String(),
    ]))->assertOk();

    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'completed',
        'call_status' => 'completed',
        'dial_duration_seconds' => 26,
        'answered_at' => $answeredAt->toIso8601String(),
        'ended_at' => $endedAt->toIso8601String(),
        'occurred_at' => $endedAt->toIso8601String(),
    ]))->assertOk();

    postHostedVoice('voice.recording.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-hosted-answered-short',
        'recording_url' => 'https://recordings.example.test/short',
        'duration_seconds' => 27,
    ])->assertOk();

    $session = hostedVoiceSession($sid);

    expect($session->status)->toBe(CallSessionStatus::Completed)
        ->and($session->hosted_outcome)->toBe(HostedCallOutcome::Completed)
        ->and($session->disposition_origin)->toBe('platform')
        ->and($session->answered_at?->equalTo($answeredAt))->toBeTrue()
        ->and($session->ended_at?->equalTo($endedAt))->toBeTrue()
        ->and($session->dial_duration_seconds)->toBe(26)
        ->and($session->recording_duration_seconds)->toBe(27)
        ->and($session->voicemail_url)->toBeNull();
});

test('a long answered call replaces the five-minute recovery end', function () {
    $sid = 'CA-hosted-answered-long';
    $startedAt = Carbon::parse('2026-09-28 15:50:51');
    $recoveryAt = Carbon::parse('2026-09-28 15:55:52');
    $endedAt = Carbon::parse('2026-09-28 15:59:43');
    $answeredAt = $endedAt->copy()->subSeconds(514);

    Carbon::setTestNow($startedAt);
    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid, [
        'occurred_at' => $startedAt->toIso8601String(),
    ]))->assertOk();

    Carbon::setTestNow($recoveryAt);
    app(CallSessionQueue::class)->reconcileStaleLiveSessions();

    $recovered = hostedVoiceSession($sid);
    expect($recovered->status)->toBe(CallSessionStatus::Missed)
        ->and($recovered->disposition_origin)->toBe('recovery')
        ->and($recovered->ended_at?->equalTo($recoveryAt))->toBeTrue()
        ->and($recovered->answered_at)->toBeNull();

    postHostedVoice('voice.incoming.answered', hostedVoicePayload($sid, [
        'outcome' => 'answered',
        'dial_duration_seconds' => 514,
        'answered_at' => $answeredAt->toIso8601String(),
        'occurred_at' => $endedAt->toIso8601String(),
    ]))->assertOk();

    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'completed',
        'call_status' => 'completed',
        'dial_duration_seconds' => 514,
        'answered_at' => $answeredAt->toIso8601String(),
        'ended_at' => $endedAt->toIso8601String(),
        'occurred_at' => $endedAt->toIso8601String(),
    ]))->assertOk();

    postHostedVoice('voice.recording.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-hosted-answered-long',
        'recording_url' => 'https://recordings.example.test/long',
        'duration_seconds' => 514,
    ])->assertOk();

    Carbon::setTestNow($endedAt->copy()->addMinutes(10));
    app(CallSessionQueue::class)->reconcileStaleLiveSessions();

    $session = hostedVoiceSession($sid);

    expect($session->status)->toBe(CallSessionStatus::Completed)
        ->and($session->hosted_outcome)->toBe(HostedCallOutcome::Completed)
        ->and($session->disposition_origin)->toBe('platform')
        ->and($session->answered_at?->equalTo($answeredAt))->toBeTrue()
        ->and($session->ended_at?->equalTo($endedAt))->toBeTrue()
        ->and($session->ended_at?->equalTo($recoveryAt))->toBeFalse()
        ->and($session->dial_duration_seconds)->toBe(514)
        ->and($session->started_at?->equalTo($startedAt))->toBeTrue()
        ->and($session->recording_url)->not->toBeNull()
        ->and($session->voicemail_url)->toBeNull();
});

test('marking a live call handled does not end it', function () {
    $sid = 'CA-hosted-handled-during-call';
    $startedAt = Carbon::parse('2026-09-28 16:01:49');
    $handledAt = Carbon::parse('2026-09-28 16:03:50');
    $recoveryAt = Carbon::parse('2026-09-28 16:07:00');
    $endedAt = Carbon::parse('2026-09-28 16:12:28');
    $answeredAt = $endedAt->copy()->subSeconds(639);

    Carbon::setTestNow($startedAt);
    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid, [
        'occurred_at' => $startedAt->toIso8601String(),
    ]))->assertOk();

    Carbon::setTestNow($handledAt);
    $session = hostedVoiceSession($sid);
    app(CallSessionQueue::class)->markCallerHandled($session);
    app(CallSessionQueue::class)->reconcileStaleLiveSessions();

    $handled = hostedVoiceSession($sid);
    expect($handled->worked_at?->equalTo($handledAt))->toBeTrue()
        ->and($handled->ended_at)->toBeNull()
        ->and($handled->status)->toBe(CallSessionStatus::Ringing)
        ->and($handled->answered_at)->toBeNull();

    Carbon::setTestNow($recoveryAt);
    app(CallSessionQueue::class)->reconcileStaleLiveSessions();
    expect(hostedVoiceSession($sid)->disposition_origin)->toBe('recovery');

    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'completed',
        'call_status' => 'completed',
        'dial_duration_seconds' => 639,
        'answered_at' => $answeredAt->toIso8601String(),
        'ended_at' => $endedAt->toIso8601String(),
        'occurred_at' => $endedAt->toIso8601String(),
    ]))->assertOk();

    $finished = hostedVoiceSession($sid);

    expect($finished->status)->toBe(CallSessionStatus::Completed)
        ->and($finished->hosted_outcome)->toBe(HostedCallOutcome::Completed)
        ->and($finished->answered_at?->equalTo($answeredAt))->toBeTrue()
        ->and($finished->ended_at?->equalTo($endedAt))->toBeTrue()
        ->and($finished->ended_at?->equalTo($handledAt))->toBeFalse()
        ->and($finished->worked_at?->equalTo($handledAt))->toBeTrue()
        ->and($finished->dial_duration_seconds)->toBe(639);
});

test('after-hours voicemail with nothing left stays missed', function () {
    $sid = 'CA-hosted-voicemail-empty';
    $endedAt = Carbon::parse('2026-09-28 14:02:04');

    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid, [
        'occurred_at' => '2026-09-28T14:02:00+00:00',
        'route' => 'after_hours_voicemail',
    ]))->assertOk();

    postHostedVoice('voice.voicemail.offered', hostedVoicePayload($sid, [
        'outcome' => 'voicemail_offered',
        'route' => 'after_hours_voicemail',
        'occurred_at' => '2026-09-28T14:02:00+00:00',
    ]))->assertOk();

    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'voicemail_offered',
        'call_status' => 'completed',
        'route' => 'after_hours_voicemail',
        'ended_at' => $endedAt->toIso8601String(),
        'occurred_at' => $endedAt->toIso8601String(),
    ]))->assertOk();

    $session = hostedVoiceSession($sid);

    expect($session->status)->toBe(CallSessionStatus::Missed)
        ->and($session->hosted_outcome)->toBe(HostedCallOutcome::VoicemailOffered)
        ->and($session->answered_at)->toBeNull()
        ->and($session->ended_at?->equalTo($endedAt))->toBeTrue()
        ->and($session->voicemail_url)->toBeNull()
        ->and($session->recording_url)->toBeNull()
        ->and($session->status->label())->toBe('Missed');
});

test('a voicemail recording does not become an answered call', function () {
    $sid = 'CA-hosted-voicemail-left';
    $endedAt = Carbon::parse('2026-09-28 14:05:20');

    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();
    postHostedVoice('voice.voicemail.offered', hostedVoicePayload($sid, [
        'outcome' => 'voicemail_offered',
    ]))->assertOk();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'voicemail_offered',
        'call_status' => 'completed',
        'ended_at' => $endedAt->toIso8601String(),
    ]))->assertOk();
    postHostedVoice('voice.voicemail.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-hosted-voicemail-left',
        'recording_url' => 'https://recordings.example.test/vm',
        'duration_seconds' => 18,
    ])->assertOk();

    $session = hostedVoiceSession($sid);

    expect($session->hosted_outcome)->toBe(HostedCallOutcome::VoicemailLeft)
        ->and($session->status)->toBe(CallSessionStatus::Missed)
        ->and($session->answered_at)->toBeNull()
        ->and($session->ended_at?->equalTo($endedAt))->toBeTrue()
        ->and($session->voicemail_duration_seconds)->toBe(18)
        ->and($session->recording_url)->toBeNull();
});

test('a fallback without a second dial result stays unknown when a recording arrives', function () {
    $sid = 'CA-hosted-fallback-unknown';
    $endedAt = Carbon::parse('2026-09-28 15:10:12');

    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid, [
        'route' => 'sip_then_fallback',
    ]))->assertOk();

    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'unknown',
        'call_status' => 'completed',
        'route' => 'sip_then_fallback',
        'ended_at' => $endedAt->toIso8601String(),
        'occurred_at' => $endedAt->toIso8601String(),
    ]))->assertOk();

    postHostedVoice('voice.recording.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-hosted-fallback-unknown',
        'recording_url' => 'https://recordings.example.test/fallback',
        'duration_seconds' => 12,
    ])->assertOk();

    $session = hostedVoiceSession($sid);

    expect($session->status)->toBe(CallSessionStatus::Unknown)
        ->and($session->hosted_outcome)->toBe(HostedCallOutcome::Unknown)
        ->and($session->answered_at)->toBeNull()
        ->and($session->dial_duration_seconds)->toBeNull()
        ->and($session->ended_at?->equalTo($endedAt))->toBeTrue()
        ->and($session->recording_duration_seconds)->toBe(12)
        ->and($session->voicemail_url)->toBeNull()
        ->and($session->status->label())->toBe('Unknown');
});

test('an ended event without a hosted outcome still accepts the parent status', function () {
    $sid = 'CA-hosted-legacy-parent';

    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'call_status' => 'completed',
    ]))->assertOk();

    $session = hostedVoiceSession($sid);

    expect($session->status)->toBe(CallSessionStatus::Completed)
        ->and($session->hosted_outcome)->toBe(HostedCallOutcome::Ringing)
        ->and($session->answered_at)->toBeNull();
});

test('late and duplicate voice events keep the authoritative call', function () {
    $sid = 'CA-hosted-event-order';
    $answeredAt = Carbon::parse('2026-09-28 15:00:14');
    $endedAt = Carbon::parse('2026-09-28 15:00:40');
    $later = Carbon::parse('2026-09-28 15:11:00');

    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid, [
        'occurred_at' => '2026-09-28T15:00:00+00:00',
    ]))->assertOk();

    postHostedVoice('voice.incoming.answered', hostedVoicePayload($sid, [
        'outcome' => 'answered',
        'dial_duration_seconds' => 26,
        'answered_at' => $answeredAt->toIso8601String(),
        'occurred_at' => $endedAt->toIso8601String(),
    ]))->assertOk();

    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid, [
        'occurred_at' => '2026-09-28T15:00:01+00:00',
    ]))->assertOk();

    postHostedVoice('voice.incoming.answered', hostedVoicePayload($sid, [
        'outcome' => 'answered',
        'dial_duration_seconds' => 9,
        'answered_at' => $later->toIso8601String(),
    ]))->assertOk();

    postHostedVoice('voice.recording.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-hosted-event-order',
        'recording_url' => 'https://recordings.example.test/order',
        'duration_seconds' => 27,
    ])->assertOk();

    Carbon::setTestNow($endedAt->copy()->addMinutes(10));
    app(CallSessionQueue::class)->reconcileStaleLiveSessions();

    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'completed',
        'call_status' => 'completed',
        'dial_duration_seconds' => 26,
        'answered_at' => $answeredAt->toIso8601String(),
        'ended_at' => $endedAt->toIso8601String(),
    ]))->assertOk();

    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'completed',
        'call_status' => 'completed',
        'dial_duration_seconds' => 90,
        'answered_at' => $later->toIso8601String(),
        'ended_at' => $later->toIso8601String(),
    ]))->assertOk();

    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'missed',
        'call_status' => 'no-answer',
        'ended_at' => $later->toIso8601String(),
    ]))->assertOk();
    postHostedVoice('voice.voicemail.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-hosted-event-order-vm',
        'recording_url' => 'https://recordings.example.test/not-vm',
        'duration_seconds' => 8,
    ])->assertOk();

    Carbon::setTestNow($later->copy()->addHour());
    app(CallSessionQueue::class)->reconcileStaleLiveSessions();

    $session = hostedVoiceSession($sid);

    expect($session->status)->toBe(CallSessionStatus::Completed)
        ->and($session->hosted_outcome)->toBe(HostedCallOutcome::Completed)
        ->and($session->disposition_origin)->toBe('platform')
        ->and($session->answered_at?->equalTo($answeredAt))->toBeTrue()
        ->and($session->ended_at?->equalTo($endedAt))->toBeTrue()
        ->and($session->dial_duration_seconds)->toBe(26)
        ->and($session->recording_url)->toBe('https://recordings.example.test/order')
        ->and($session->recording_duration_seconds)->toBe(27)
        ->and($session->voicemail_url)->toBeNull();
});

test('late events do not reclassify a voicemail', function () {
    $sid = 'CA-hosted-voicemail-order';
    $endedAt = Carbon::parse('2026-09-28 14:05:20');
    $later = Carbon::parse('2026-09-28 14:20:00');

    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();
    postHostedVoice('voice.voicemail.offered', hostedVoicePayload($sid, [
        'outcome' => 'voicemail_offered',
    ]))->assertOk();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'voicemail_offered',
        'call_status' => 'completed',
        'ended_at' => $endedAt->toIso8601String(),
    ]))->assertOk();
    postHostedVoice('voice.voicemail.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-hosted-vm-order',
        'recording_url' => 'https://recordings.example.test/vm-order',
        'duration_seconds' => 18,
    ])->assertOk();
    postHostedVoice('voice.recording.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-hosted-vm-as-rec',
        'recording_url' => 'https://recordings.example.test/should-not-replace',
        'duration_seconds' => 18,
    ])->assertOk();
    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'voicemail_offered',
        'call_status' => 'completed',
        'ended_at' => $later->toIso8601String(),
    ]))->assertOk();
    postHostedVoice('voice.incoming.answered', hostedVoicePayload($sid, [
        'outcome' => 'answered',
        'dial_duration_seconds' => 18,
        'answered_at' => $later->toIso8601String(),
    ]))->assertOk();

    $session = hostedVoiceSession($sid);

    expect($session->hosted_outcome)->toBe(HostedCallOutcome::VoicemailLeft)
        ->and($session->status)->toBe(CallSessionStatus::Missed)
        ->and($session->answered_at)->toBeNull()
        ->and($session->ended_at?->equalTo($endedAt))->toBeTrue()
        ->and($session->voicemail_url)->toBe('https://recordings.example.test/vm-order')
        ->and($session->recording_url)->toBeNull();
});

test('a normal recording promotes a missed call and a voicemail does not', function () {
    $answeredSid = 'CA-hosted-missed-with-talk-recording';
    postHostedVoice('voice.incoming.started', hostedVoicePayload($answeredSid, [
        'occurred_at' => '2026-09-28T16:00:00+00:00',
    ]))->assertOk();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($answeredSid, [
        'outcome' => 'missed',
        'call_status' => 'no-answer',
        'ended_at' => '2026-09-28T16:00:40+00:00',
    ]))->assertOk();

    $missed = hostedVoiceSession($answeredSid);
    expect($missed->status)->toBe(CallSessionStatus::Missed)
        ->and($missed->answered_at)->toBeNull();

    postHostedVoice('voice.recording.available', [
        'provider_call_sid' => $answeredSid,
        'recording_sid' => 'RE-hosted-talk',
        'recording_url' => 'https://recordings.example.test/talk',
        'duration_seconds' => 185,
    ])->assertOk();

    $promoted = hostedVoiceSession($answeredSid);
    expect($promoted->status)->toBe(CallSessionStatus::Completed)
        ->and($promoted->answered_at)->not->toBeNull()
        ->and($promoted->recording_duration_seconds)->toBe(185)
        ->and($promoted->voicemail_url)->toBeNull()
        ->and($promoted->hosted_outcome)->toBe(HostedCallOutcome::Missed);

    $voicemailSid = 'CA-hosted-voicemail-stays-missed';
    postHostedVoice('voice.incoming.started', hostedVoicePayload($voicemailSid))->assertOk();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($voicemailSid, [
        'outcome' => 'missed',
        'call_status' => 'no-answer',
    ]))->assertOk();
    postHostedVoice('voice.voicemail.available', [
        'provider_call_sid' => $voicemailSid,
        'recording_sid' => 'RE-hosted-vm-only',
        'recording_url' => 'https://recordings.example.test/vm-only',
        'duration_seconds' => 18,
    ])->assertOk();

    $voicemail = hostedVoiceSession($voicemailSid);
    expect($voicemail->status)->toBe(CallSessionStatus::Missed)
        ->and($voicemail->answered_at)->toBeNull()
        ->and($voicemail->voicemail_url)->toBe('https://recordings.example.test/vm-only')
        ->and($voicemail->recording_url)->toBeNull();

    $completedSid = 'CA-hosted-completed-without-answer';
    postHostedVoice('voice.incoming.started', hostedVoicePayload($completedSid))->assertOk();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($completedSid, [
        'outcome' => 'completed',
        'call_status' => 'completed',
    ]))->assertOk();
    postHostedVoice('voice.recording.available', [
        'provider_call_sid' => $completedSid,
        'recording_sid' => 'RE-hosted-zero',
        'recording_url' => 'https://recordings.example.test/zero',
        'duration_seconds' => 0,
    ])->assertOk();

    $completed = hostedVoiceSession($completedSid);
    expect($completed->status)->toBe(CallSessionStatus::Completed)
        ->and($completed->answered_at)->toBeNull()
        ->and($completed->dial_duration_seconds)->toBeNull();
});

test('an ended event rejects an unknown hosted outcome', function () {
    postHostedVoice('voice.incoming.ended', hostedVoicePayload('CA-hosted-bad-outcome', [
        'outcome' => 'parent_completed',
        'call_status' => 'completed',
    ]))->assertStatus(422);
});

test('a missed fabric end schedules one rescue at the shop delay', function (int $delaySeconds) {
    Queue::fake();
    hostedRescueSettings(enabled: true, delaySeconds: $delaySeconds);

    $sid = 'CA-fabric-rescue-delay-'.$delaySeconds;
    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();
    Queue::assertNotPushed(SendMissedCallRescueSmsJob::class);

    $scheduledAt = now();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'missed',
        'call_status' => 'no-answer',
    ]))->assertOk();

    $session = hostedVoiceSession($sid);
    expect($session->status)->toBe(CallSessionStatus::Missed)
        ->and($session->answered_at)->toBeNull();
    assertOneRescueDelayed($session->id, $delaySeconds, $scheduledAt);

    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'missed',
        'call_status' => 'no-answer',
    ]))->assertOk();
    postHostedVoice('voice.voicemail.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-fabric-rescue-'.$delaySeconds,
        'recording_url' => 'https://recordings.example.test/fabric-vm-'.$delaySeconds,
        'duration_seconds' => 12,
    ])->assertOk();

    Queue::assertPushed(SendMissedCallRescueSmsJob::class, 1);
    expect(hostedVoiceSession($sid)->status)->toBe(CallSessionStatus::Missed);
})->with([
    '45 seconds' => 45,
    '90 seconds' => 90,
]);

test('a missed fabric end does not schedule rescue when the shop has it disabled', function () {
    Queue::fake();
    hostedRescueSettings(enabled: false, delaySeconds: 45);

    $sid = 'CA-fabric-rescue-disabled';
    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'missed',
        'call_status' => 'no-answer',
    ]))->assertOk();

    expect(hostedVoiceSession($sid)->status)->toBe(CallSessionStatus::Missed);
    Queue::assertNotPushed(SendMissedCallRescueSmsJob::class);
});

test('fabric ends that are not missed do not schedule rescue', function (string $outcome, CallSessionStatus $status) {
    Queue::fake();
    hostedRescueSettings(enabled: true, delaySeconds: 45);

    $sid = 'CA-fabric-rescue-'.$outcome;
    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();

    if ($outcome === 'answered') {
        postHostedVoice('voice.incoming.answered', hostedVoicePayload($sid, [
            'outcome' => 'answered',
        ]))->assertOk();
        Queue::assertNotPushed(SendMissedCallRescueSmsJob::class);
    }

    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => $outcome,
        'call_status' => $outcome,
    ]))->assertOk();

    $session = hostedVoiceSession($sid);
    expect($session->status)->toBe($status);
    Queue::assertNotPushed(SendMissedCallRescueSmsJob::class);

    if ($outcome === 'completed') {
        expect($session->answered_at)->toBeNull();
    }
})->with([
    'answered' => ['answered', CallSessionStatus::Answered],
    'completed' => ['completed', CallSessionStatus::Completed],
    'unknown' => ['unknown', CallSessionStatus::Unknown],
]);

test('voicemail offered does not schedule rescue until a voicemail is left', function () {
    Queue::fake();
    hostedRescueSettings(enabled: true, delaySeconds: 45);

    $sid = 'CA-fabric-rescue-voicemail';
    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();
    postHostedVoice('voice.voicemail.offered', hostedVoicePayload($sid, [
        'outcome' => 'voicemail_offered',
    ]))->assertOk();

    expect(hostedVoiceSession($sid)->status)->toBe(CallSessionStatus::Missed);
    Queue::assertNotPushed(SendMissedCallRescueSmsJob::class);

    $scheduledAt = now();
    postHostedVoice('voice.voicemail.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-fabric-rescue-vm',
        'recording_url' => 'https://recordings.example.test/fabric-rescue-vm',
        'duration_seconds' => 18,
    ])->assertOk();

    $session = hostedVoiceSession($sid);
    expect($session->status)->toBe(CallSessionStatus::Missed)
        ->and($session->answered_at)->toBeNull()
        ->and($session->voicemail_url)->toBe('https://recordings.example.test/fabric-rescue-vm');
    assertOneRescueDelayed($session->id, 45, $scheduledAt);

    postHostedVoice('voice.voicemail.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-fabric-rescue-vm-again',
        'recording_url' => 'https://recordings.example.test/fabric-rescue-vm-again',
        'duration_seconds' => 18,
    ])->assertOk();
    Queue::assertPushed(SendMissedCallRescueSmsJob::class, 1);
});

test('a normal recording after a missed fabric end keeps rescue from sending', function () {
    Queue::fake();
    hostedRescueSettings(enabled: true, delaySeconds: 45);

    $sid = 'CA-fabric-rescue-recording';
    postHostedVoice('voice.incoming.started', hostedVoicePayload($sid))->assertOk();

    $scheduledAt = now();
    postHostedVoice('voice.incoming.ended', hostedVoicePayload($sid, [
        'outcome' => 'missed',
        'call_status' => 'no-answer',
    ]))->assertOk();

    $session = hostedVoiceSession($sid);
    assertOneRescueDelayed($session->id, 45, $scheduledAt);

    postHostedVoice('voice.recording.available', [
        'provider_call_sid' => $sid,
        'recording_sid' => 'RE-fabric-rescue-talk',
        'recording_url' => 'https://recordings.example.test/fabric-rescue-talk',
        'duration_seconds' => 185,
    ])->assertOk();
    Queue::assertPushed(SendMissedCallRescueSmsJob::class, 1);

    $promoted = hostedVoiceSession($sid);
    expect($promoted->status)->toBe(CallSessionStatus::Completed)
        ->and($promoted->answered_at)->not->toBeNull();

    $transport = bindFakeOutboundSms('SMfabricrescue');
    (new SendMissedCallRescueSmsJob($promoted->id))->handle(app(SendMissedCallRescueSmsAction::class));

    expect($transport->sent)->toBe([])
        ->and(ConversationMessage::query()->count())->toBe(0);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function hostedVoicePayload(string $sid, array $overrides = []): array
{
    return array_merge([
        'provider_call_sid' => $sid,
        'from_phone' => '+17195550199',
        'to_phone' => '+17195550100',
        'call_status' => 'ringing',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $payload
 */
function postHostedVoice(string $operation, array $payload): TestResponse
{
    $body = [
        'operation' => $operation,
        'installation_id' => InstallationIdentity::uuid(),
        'occurred_at' => $payload['occurred_at'] ?? now()->toIso8601String(),
        'payload' => $payload,
    ];
    [$raw, $server] = fabricSignedRequest($body);

    return test()->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw);
}

function hostedVoiceSession(string $sid): CallSession
{
    return CallSession::query()->where('provider_call_sid', $sid)->firstOrFail();
}

function hostedRescueSettings(bool $enabled, int $delaySeconds, int $cooldownMinutes = 45): void
{
    $flow = ShopSettings::defaultTelephonyCallFlow();
    $flow['missed_call_rescue_enabled'] = $enabled;
    $flow['missed_call_rescue_delay_seconds'] = $delaySeconds;
    $flow['missed_call_rescue_cooldown_minutes'] = $cooldownMinutes;

    ShopSettings::current()->update([
        'telephony_call_flow' => $flow,
        'telephony_inbound_number' => '7195550100',
    ]);
    ShopSettings::forgetCurrent();
}

function assertOneRescueDelayed(int $sessionId, int $delaySeconds, Carbon $scheduledAt): void
{
    Queue::assertPushed(SendMissedCallRescueSmsJob::class, 1);
    Queue::assertPushed(SendMissedCallRescueSmsJob::class, function (SendMissedCallRescueSmsJob $job) use ($sessionId, $delaySeconds, $scheduledAt): bool {
        $delay = $job->delay;

        return $job->callSessionId === $sessionId
            && $delay instanceof DateTimeInterface
            && abs($scheduledAt->diffInSeconds($delay, false) - $delaySeconds) < 3;
    });
}
