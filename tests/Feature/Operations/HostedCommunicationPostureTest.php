<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Messaging\InboundSmsConversationIngress;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('broadcasting.default', 'null');
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_core_mirror', false);
    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Casey Auto Repair',
        'platform_status' => 'connected',
        'platform_base_url' => 'https://cloud.example.test',
        'platform_credential' => 'test-credential-32-characters-min!!',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_shop_public_id' => (string) Str::uuid(),
        'cloud_credential' => 'test-credential-32-characters-min!!',
        'ark_mail_status' => 'connected',
    ]);
});

test('resolving clears a shop turn', function () {
    $advisor = User::factory()->create();
    $work = app(ConversationWork::class);
    $conversation = $work->ensureForPhone('7195550199');
    $work->markNeedsAttention($conversation);

    $work->resolve($conversation->fresh(), $advisor);
    $resolved = $conversation->fresh();

    expect($resolved->status)->toBe(ConversationStatus::Resolved)
        ->and($resolved->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($work->lane($resolved))->toBe('resolved');

    $resolved->forceFill([
        'waiting_on' => ConversationWaitingOn::Shop,
    ])->save();

    expect($resolved->fresh()->status)->toBe(ConversationStatus::Resolved)
        ->and($resolved->fresh()->waiting_on)->toBe(ConversationWaitingOn::Customer);
});

test('hosted inbound sms reopens a resolved thread into Needs', function () {
    expect(ManagedCommunicationsGate::coreMirrorEnabled())->toBeFalse();

    $advisor = User::factory()->create();
    $phone = '7195550201';
    $work = app(ConversationWork::class);
    $conversation = $work->resolve($work->ensureForPhone($phone), $advisor);

    app(InboundSmsConversationIngress::class)->ingest(new InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: $phone,
        providerMessageId: 'SM-posture-sms-1',
        channel: \App\Ark\Operations\Communications\OperationalCommunicationChannel::Sms,
        body: 'Are you open?',
    ));

    $fresh = $conversation->fresh();

    expect($fresh->status)->toBe(ConversationStatus::Open)
        ->and($fresh->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($fresh->reopen_count)->toBe(1)
        ->and($work->lane($fresh))->toBe('needs');
});

test('hosted inbound sms into an open waiting thread gives the shop the turn', function () {
    expect(ManagedCommunicationsGate::coreMirrorEnabled())->toBeFalse();

    $advisor = User::factory()->create();
    $phone = '7195550207';
    $work = app(ConversationWork::class);
    $conversation = $work->followUp($work->ensureForPhone($phone), $advisor, now()->addDay());

    expect($work->lane($conversation->fresh()))->toBe('waiting');

    app(InboundSmsConversationIngress::class)->ingest(new InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: $phone,
        providerMessageId: 'SM-posture-open-1',
        channel: \App\Ark\Operations\Communications\OperationalCommunicationChannel::Sms,
        body: 'I can come in tomorrow',
    ));

    $fresh = $conversation->fresh();

    expect($fresh->status)->toBe(ConversationStatus::Open)
        ->and($fresh->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($fresh->reopen_count)->toBe(0)
        ->and($fresh->follow_up_due_at)->toBeNull()
        ->and($work->lane($fresh))->toBe('needs');
});

test('a missed hosted call reopens a resolved thread into Needs', function () {
    $advisor = User::factory()->create();
    $phone = '7195550202';
    $work = app(ConversationWork::class);
    $conversation = $work->resolve($work->ensureForPhone($phone), $advisor);

    postPostureVoice('voice.incoming.ended', postureVoicePayload('CA-posture-missed', $phone, [
        'outcome' => 'missed',
        'call_status' => 'no-answer',
        'ended_at' => '2026-09-28T15:10:00+00:00',
    ]))->assertOk();

    $fresh = $conversation->fresh();

    expect($fresh->status)->toBe(ConversationStatus::Open)
        ->and($fresh->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($fresh->reopen_count)->toBe(1)
        ->and($work->lane($fresh))->toBe('needs');
});

test('voicemail left reopens a resolved thread and a duplicate does not reopen twice', function () {
    $advisor = User::factory()->create();
    $phone = '7195550203';
    $work = app(ConversationWork::class);
    $conversation = $work->resolve($work->ensureForPhone($phone), $advisor);
    $sid = 'CA-posture-voicemail';

    postPostureVoice('voice.incoming.ended', postureVoicePayload($sid, $phone, [
        'outcome' => 'voicemail_offered',
        'call_status' => 'completed',
        'ended_at' => '2026-09-28T15:12:00+00:00',
    ]))->assertOk();

    postPostureVoice('voice.voicemail.available', [
        'provider_call_sid' => $sid,
        'from_phone' => '+1'.$phone,
        'to_phone' => '+17195550100',
        'recording_sid' => 'RE-posture-voicemail',
        'recording_url' => 'https://recordings.example.test/posture-vm',
        'duration_seconds' => 12,
    ])->assertOk();

    postPostureVoice('voice.voicemail.available', [
        'provider_call_sid' => $sid,
        'from_phone' => '+1'.$phone,
        'to_phone' => '+17195550100',
        'recording_sid' => 'RE-posture-voicemail',
        'recording_url' => 'https://recordings.example.test/posture-vm',
        'duration_seconds' => 12,
    ])->assertOk();

    $fresh = $conversation->fresh();

    expect($fresh->status)->toBe(ConversationStatus::Open)
        ->and($fresh->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($fresh->reopen_count)->toBe(1)
        ->and($work->lane($fresh))->toBe('needs');
});

test('voicemail offered with nothing left is missed work', function () {
    $advisor = User::factory()->create();
    $phone = '7195550204';
    $work = app(ConversationWork::class);
    $conversation = $work->resolve($work->ensureForPhone($phone), $advisor);

    postPostureVoice('voice.incoming.ended', postureVoicePayload('CA-posture-offered', $phone, [
        'outcome' => 'voicemail_offered',
        'call_status' => 'completed',
        'ended_at' => '2026-09-28T15:14:00+00:00',
    ]))->assertOk();

    $fresh = $conversation->fresh();

    expect($fresh->status)->toBe(ConversationStatus::Open)
        ->and($fresh->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($work->lane($fresh))->toBe('needs');
});

test('an answered hosted call does not create Needs', function () {
    $advisor = User::factory()->create();
    $phone = '7195550205';
    $work = app(ConversationWork::class);
    $conversation = $work->resolve($work->ensureForPhone($phone), $advisor);
    $sid = 'CA-posture-answered';

    postPostureVoice('voice.incoming.started', postureVoicePayload($sid, $phone))->assertOk();
    postPostureVoice('voice.incoming.answered', postureVoicePayload($sid, $phone, [
        'outcome' => 'answered',
        'dial_duration_seconds' => 40,
        'answered_at' => '2026-09-28T15:16:00+00:00',
    ]))->assertOk();
    postPostureVoice('voice.incoming.ended', postureVoicePayload($sid, $phone, [
        'outcome' => 'completed',
        'call_status' => 'completed',
        'dial_duration_seconds' => 40,
        'answered_at' => '2026-09-28T15:16:00+00:00',
        'ended_at' => '2026-09-28T15:16:40+00:00',
    ]))->assertOk();

    $fresh = $conversation->fresh();

    expect($fresh->status)->toBe(ConversationStatus::Resolved)
        ->and($fresh->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($fresh->reopen_count)->toBe(0)
        ->and($work->lane($fresh))->toBe('resolved');

    postPostureVoice('voice.incoming.ended', postureVoicePayload('CA-posture-answered-new', '7195550299', [
        'outcome' => 'completed',
        'call_status' => 'completed',
        'dial_duration_seconds' => 15,
        'answered_at' => '2026-09-28T15:20:00+00:00',
        'ended_at' => '2026-09-28T15:20:15+00:00',
    ]))->assertOk();

    expect(Conversation::query()->where('contact_address', '7195550299')->exists())->toBeFalse();
});

test('a website request reopens a resolved thread into Needs', function () {
    $advisor = User::factory()->create();
    $phone = '7195550206';
    $work = app(ConversationWork::class);
    $conversation = $work->resolve($work->ensureForPhone($phone), $advisor);

    app(ConversationRecorder::class)->recordWebsiteLead(null, 'Need brakes checked', $phone, 'Sam');

    $fresh = $conversation->fresh();

    expect($fresh->status)->toBe(ConversationStatus::Open)
        ->and($fresh->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($fresh->reopen_count)->toBe(1)
        ->and($work->lane($fresh))->toBe('needs');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function postureVoicePayload(string $sid, string $phone, array $overrides = []): array
{
    return array_merge([
        'provider_call_sid' => $sid,
        'from_phone' => '+1'.$phone,
        'to_phone' => '+17195550100',
        'call_status' => 'ringing',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $payload
 */
function postPostureVoice(string $operation, array $payload): \Illuminate\Testing\TestResponse
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
