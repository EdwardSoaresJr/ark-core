<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\UnreadInboundMessageQueue;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Timeline\Mappers\ConversationMessageEventMapper;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.ark_platform.communications_authority', false);
});

function inboundText(string $phone, array $metadata, string $minutesAgo = '30'): ConversationMessage
{
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => $phone,
    ]);
    $participant = app(ConversationParticipantResolver::class)->customer($conversation);

    return ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Is my truck ready?',
        'occurred_at' => now()->subMinutes((int) $minutesAgo),
        'metadata' => $metadata,
    ]);
}

test('imported history inside the unread window is not treated as a new arrival', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $live = inboundText('7195550601', ['twilio_message_sid' => 'SMlive']);
    inboundText('7195550602', ['twilio_message_sid' => 'SMimported', 'source' => 'twilio_backfill', 'backfilled_at' => now()->toIso8601String()]);

    $unread = app(UnreadInboundMessageQueue::class)->latestUnreadPerConversation($advisor);

    expect($unread->pluck('id')->all())->toBe([$live->id]);
});

test('imported history is labelled in the thread, and a missing photo is called out', function (): void {
    $mapper = app(ConversationMessageEventMapper::class);
    $photo = inboundText('7195550603', ['backfilled_at' => now()->toIso8601String(), 'media_count' => 1]);
    $text = inboundText('7195550604', ['backfilled_at' => now()->toIso8601String()]);
    $live = inboundText('7195550605', []);

    expect($mapper->map($photo->fresh())->metadata['imported_label'])->toBe('Imported history · photo not recovered')
        ->and($mapper->map($text->fresh())->metadata['imported_label'])->toBe('Imported history')
        ->and($mapper->map($live->fresh())->metadata['imported_label'])->toBeNull();
});

test('escalation never replays old website leads, only ones inside the live window', function (): void {
    $sms = bindFakeOutboundSms('SMcutoff1');
    User::factory()->create(['phone' => '3035551212'])->assignRole(ArkRole::Advisor->value);
    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195559999',
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_escalation_enabled' => true,
            'comms_escalation_delay_minutes' => 3,
        ]),
    ]);
    config()->set('services.twilio.account_sid', 'AC-test');
    config()->set('services.twilio.auth_token', 'test-token');

    $old = Lead::query()->create(['source' => LeadSource::Website, 'state' => LeadState::Received, 'concern' => 'Old brake noise', 'contact_phone' => '7195550701']);
    $old->forceFill(['created_at' => now()->subDays(9)])->saveQuietly();
    $fresh = Lead::query()->create(['source' => LeadSource::Website, 'state' => LeadState::Received, 'concern' => 'New brake noise', 'contact_phone' => '7195550702']);
    $fresh->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

    $this->artisan('comms:escalate-unhandled')->assertSuccessful();

    expect(collect($sms->sent)->pluck('body')->filter(fn ($body) => str_contains($body, 'Old brake noise')))->toBeEmpty()
        ->and(collect($sms->sent)->pluck('body')->filter(fn ($body) => str_contains($body, 'New brake noise')))->toHaveCount(1);
});
