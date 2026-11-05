<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Messaging\InboundSmsConversationIngress;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

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
        channel: OperationalCommunicationChannel::Sms,
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
        channel: OperationalCommunicationChannel::Sms,
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

test('a staff resolution still takes an unowned thread and records who resolved it', function () {
    $advisor = User::factory()->create();
    $work = app(ConversationWork::class);
    $conversation = $work->markNeedsAttention($work->ensureForPhone('7195550207'));

    $resolved = $work->resolve($conversation, $advisor);
    $event = resolutionEvents($resolved)->sole();

    expect($resolved->owned_by_user_id)->toBe($advisor->id)
        ->and($event->actor_user_id)->toBe($advisor->id)
        ->and($event->payload_json['source'])->toBe('staff')
        ->and($event->payload_json['previous']['status'])->toBe('open')
        ->and($event->payload_json['previous']['owned_by_user_id'])->toBeNull();
});

test('an automatic resolution records its reason and keeps the owner as it was', function () {
    $owner = User::factory()->create();
    $work = app(ConversationWork::class);
    $owned = $work->assign($work->markNeedsAttention($work->ensureForPhone('7195550208')), $owner);
    $unowned = $work->followUp($work->ensureForPhone('7195550209'), null, now()->addDay());

    $this->travel(5)->minutes();
    $resolvedOwned = $work->resolveAutomatically($owned, 'Backlog cleanup: repair order closed', ['repair_order_ids' => [41]]);
    $resolvedUnowned = $work->resolveAutomatically($unowned, 'Backlog cleanup: repair order closed');

    expect($resolvedOwned->status)->toBe(ConversationStatus::Resolved)
        ->and($resolvedOwned->owned_by_user_id)->toBe($owner->id)
        ->and($resolvedOwned->resolved_at?->equalTo(now()->startOfSecond()))->toBeTrue()
        ->and($work->lane($resolvedOwned))->toBe('resolved')
        ->and($resolvedUnowned->owned_by_user_id)->toBeNull()
        ->and($resolvedUnowned->follow_up_due_at)->toBeNull();

    $event = resolutionEvents($resolvedOwned)->sole();

    expect($event->actor_user_id)->toBeNull()
        ->and($event->occurred_at->equalTo($resolvedOwned->resolved_at))->toBeTrue()
        ->and($event->payload_json['source'])->toBe('system')
        ->and($event->payload_json['reason'])->toBe('Backlog cleanup: repair order closed')
        ->and($event->payload_json['context'])->toBe(['repair_order_ids' => [41]])
        ->and($event->payload_json['previous']['owned_by_user_id'])->toBe($owner->id)
        ->and($event->payload_json['previous']['waiting_on'])->toBe('shop');

    expect(resolutionEvents($resolvedUnowned)->sole()->payload_json)->not->toHaveKey('context')
        ->and(resolutionEvents($resolvedUnowned)->sole()->payload_json['previous']['follow_up_due_at'])->not->toBeNull();
});

test('an automatic resolution of a thread that is no longer open changes nothing', function () {
    $advisor = User::factory()->create();
    $work = app(ConversationWork::class);
    $conversation = $work->resolve($work->markNeedsAttention($work->ensureForPhone('7195550210')), $advisor);
    $resolvedAt = $conversation->resolved_at;

    $this->travel(1)->hour();
    $work->resolveAutomatically($conversation, 'Backlog cleanup: repair order closed');
    $work->resolveAutomatically($conversation, 'Backlog cleanup: repair order closed');

    expect($conversation->fresh()->resolved_at->equalTo($resolvedAt))->toBeTrue()
        ->and(resolutionEvents($conversation)->pluck('payload_json.source')->all())->toBe(['staff']);
});

test('an automatic resolution needs a reason', function () {
    $work = app(ConversationWork::class);
    $conversation = $work->markNeedsAttention($work->ensureForPhone('7195550211'));

    expect(fn () => $work->resolveAutomatically($conversation, '  '))
        ->toThrow(InvalidArgumentException::class);

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Open)
        ->and(resolutionEvents($conversation))->toHaveCount(0);
});

test('a customer reply reopens an automatically resolved thread for its owner', function () {
    $owner = User::factory()->create();
    $phone = '7195550212';
    $work = app(ConversationWork::class);
    $conversation = $work->assign($work->markNeedsAttention($work->ensureForPhone($phone)), $owner);
    $work->resolveAutomatically($conversation, 'Backlog cleanup: repair order closed');

    app(InboundSmsConversationIngress::class)->ingest(new InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: $phone,
        providerMessageId: 'SM-posture-auto-reopen',
        channel: OperationalCommunicationChannel::Sms,
        body: 'One more question about the brakes',
    ));

    $fresh = $conversation->fresh();

    expect($work->lane($fresh))->toBe('needs')
        ->and($fresh->owned_by_user_id)->toBe($owner->id)
        ->and($fresh->reopen_count)->toBe(1);
});

/**
 * @return Collection<int, OperationalEvent>
 */
function resolutionEvents(Conversation $conversation): Collection
{
    return OperationalEvent::query()
        ->where('event_name', OperationalEventName::ConversationResolved->value)
        ->where('aggregate_type', Conversation::class)
        ->where('aggregate_id', $conversation->id)
        ->orderBy('id')
        ->get();
}

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
function postPostureVoice(string $operation, array $payload): TestResponse
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
