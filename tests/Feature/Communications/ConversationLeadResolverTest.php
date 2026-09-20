<?php

use App\Ark\Operations\Communications\ConversationTurnReason;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Leads\ConversationLeadResolver;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('conversation lead resolver prefers contacted lead on shared thread', function (): void {
    $conversation = app(ConversationResolver::class)->forPhone('7195550100');

    $websiteLead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes squeal',
        'contact_phone' => '7195550100',
        'contact_name' => 'Jason Smith',
        'conversation_id' => $conversation->id,
        'first_contacted_at' => now()->subMinutes(5),
    ]);

    Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'Thursday works',
        'contact_phone' => '7195550100',
        'conversation_id' => $conversation->id,
    ]);

    $resolved = app(ConversationLeadResolver::class)->forTurn($conversation);

    expect($resolved?->id)->toBe($websiteLead->id);
});

test('turn reason uses contacted lead when sms sibling exists on conversation', function (): void {
    $conversation = app(ConversationResolver::class)->forPhone('7195550201');

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Oil change',
        'contact_phone' => '7195550201',
        'conversation_id' => $conversation->id,
        'first_contacted_at' => now()->subMinutes(5),
    ]);

    Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'Thursday works',
        'contact_phone' => '7195550201',
        'conversation_id' => $conversation->id,
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $recorder = app(ConversationRecorder::class);
    $participant = app(ConversationParticipantResolver::class)->advisor($conversation, $advisor);

    $recorder->record(
        $conversation,
        $participant,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        'We can get you on the schedule.',
        metadata: ['actor_user_id' => $advisor->id],
    );

    $turn = app(ConversationTurnReason::class)->for($conversation);

    expect($turn['turn_label'])->toBe('Customer replied');
});
