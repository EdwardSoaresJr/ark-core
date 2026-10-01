<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Models\User;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('unified timeline maps conversation messages to read model shape', function (): void {
    $advisor = User::factory()->create(['name' => 'Alex Advisor'])->assignRole(ArkRole::Advisor->value);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195551212',
        'status' => ConversationStatus::Open,
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Advisor,
        'user_id' => $advisor->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Can you look at my brakes?',
        'occurred_at' => Carbon::parse('2026-06-10 09:00:00'),
    ]);

    $entry = app(UnifiedOperationalTimeline::class)
        ->forConversation($conversation)
        ->sole();

    $array = $entry->toArray();

    expect($array)->toMatchArray([
        'source' => OperationalEventSource::ConversationMessage->value,
        'kind' => OperationalEventKind::Sms->value,
        'headline' => 'Incoming',
        'body' => 'Can you look at my brakes?',
        'actor' => 'Alex Advisor',
        'tone' => OperationalEventTone::Customer->value,
    ])->and($array['metadata']['hub_filter'])->toBe('text');
});

test('unified timeline maps internal notes with internal tone', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195551313',
        'status' => ConversationStatus::Open,
    ]);

    app(ConversationRecorder::class)->recordInternalNote($conversation, $advisor, 'Follow up on estimate Monday.');

    $entry = app(UnifiedOperationalTimeline::class)
        ->forConversation($conversation)
        ->sole();

    expect($entry->source)->toBe(OperationalEventSource::ConversationMessage)
        ->and($entry->kind)->toBe(OperationalEventKind::InternalNote)
        ->and($entry->tone)->toBe(OperationalEventTone::Internal)
        ->and($entry->headline)->toBe('Internal note');
});

test('unified timeline merges call session and phone conversation chronologically', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195551414',
        'status' => ConversationStatus::Open,
    ]);

    app(ConversationRecorder::class)->recordCallNote(
        $conversation,
        $advisor,
        'Customer wants status update.',
        9001,
    );

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAunified001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551414',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551414',
        'status' => CallSessionStatus::Completed,
        'started_at' => Carbon::parse('2026-06-10 08:00:00'),
    ]);

    $entries = app(UnifiedOperationalTimeline::class)->forCallSession($session);

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->kind)->toBe(OperationalEventKind::InternalNote)
        ->and($entries[1]->source)->toBe(OperationalEventSource::CallSession)
        ->and($entries[1]->kind)->toBe(OperationalEventKind::Call);
});

test('conversation timeline orders messages newest first', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195551616',
        'status' => ConversationStatus::Open,
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Advisor,
        'user_id' => $advisor->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Older message',
        'occurred_at' => Carbon::parse('2026-06-10 08:00:00'),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'body' => 'Newest message',
        'occurred_at' => Carbon::parse('2026-06-10 10:00:00'),
    ]);

    $entries = app(UnifiedOperationalTimeline::class)->forConversation($conversation);

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->body)->toBe('Newest message')
        ->and($entries[1]->body)->toBe('Older message');
});

test('customer hub comms timeline uses unified entries newest first', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00'));

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195551515',
        'status' => ConversationStatus::Open,
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Advisor,
        'user_id' => $advisor->id,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-06-02 10:00:00'));
    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Text body.',
        'occurred_at' => now(),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-06-03 09:00:00'));
    $call = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAunified002',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551515',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551515',
        'status' => CallSessionStatus::Completed,
        'started_at' => now(),
    ]);

    Carbon::setTestNow();

    $messages = ConversationMessage::query()->where('conversation_id', $conversation->id)->get();
    $timeline = app(\App\Ark\Operations\Customers\CustomerHubCommsTimeline::class);
    $rows = $timeline->build($messages, collect([$call]));

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->kind)->toBe(OperationalEventKind::Call)
        ->and($rows[1]->kind)->toBe(OperationalEventKind::Sms)
        ->and($timeline->counts($rows)['call'])->toBe(1)
        ->and($timeline->counts($rows)['text'])->toBe(1);
});

test('customer relationship comms timeline omits sms delivery receipt events', function (): void {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Delivery Receipt Customer');
    $customer = $repairOrder->customer;

    app(\App\Ark\Operations\Communications\CommunicationEventRecorder::class)->record(
        $repairOrder,
        \App\Ark\Operations\Communications\OperationalCommunicationType::SmsDelivered,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        'SMS delivered to customer. (Twilio SMtest001)',
    );

    app(\App\Ark\Operations\Communications\CommunicationEventRecorder::class)->record(
        $repairOrder,
        \App\Ark\Operations\Communications\OperationalCommunicationType::EstimateViewed,
        OperationalCommunicationChannel::Website,
        OperationalCommunicationDirection::Inbound,
        'Customer opened estimate link.',
    );

    $rows = app(\App\Ark\Operations\Customers\CustomerHubCommsTimeline::class)
        ->buildForCustomer($customer, null, 50);

    expect($rows->contains(fn ($entry): bool => $entry->headline === 'SMS delivered'))->toBeFalse()
        ->and($rows->contains(fn ($entry): bool => $entry->headline === 'Estimate viewed'))->toBeTrue()
        ->and(\App\Ark\Operations\Communications\CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('event_type', \App\Ark\Operations\Communications\OperationalCommunicationType::SmsDelivered)
            ->exists())->toBeTrue();
});
