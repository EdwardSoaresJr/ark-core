<?php

use App\Ark\Operations\Communications\CommunicationWorkboardProjection;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Leads\ConversationLeadResolver;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadConfirmationAuditConversation;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('conversation lead resolver finds lead by email on sibling audit thread', function (): void {
    $phoneConversation = app(ConversationResolver::class)->forPhone('7195550300');
    $emailConversation = app(ConversationResolver::class)->forEmail('patty@example.test');

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brake job',
        'contact_phone' => '7195550300',
        'contact_email' => 'patty@example.test',
        'contact_name' => 'Patty',
        'conversation_id' => $phoneConversation->id,
        'first_contacted_at' => now()->subMinutes(10),
    ]);

    $resolved = app(ConversationLeadResolver::class)->forTurn($emailConversation);

    expect($resolved?->contact_name)->toBe('Patty')
        ->and($resolved?->conversation_id)->toBe($phoneConversation->id);
});

test('website lead email confirmation resolves sibling audit thread', function (): void {
    $phoneConversation = app(ConversationResolver::class)->forPhone('7195550301');
    $emailConversation = app(ConversationResolver::class)->forEmail('audit@example.test');

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'AC not cold',
        'contact_phone' => '7195550301',
        'contact_email' => 'audit@example.test',
        'contact_name' => 'Jordan',
        'conversation_id' => $phoneConversation->id,
    ]);

    $recorder = app(ConversationRecorder::class);
    $participant = app(ConversationParticipantResolver::class)->system($emailConversation, displayName: 'Shop');

    $recorder->record(
        $emailConversation,
        $participant,
        OperationalCommunicationChannel::Email,
        OperationalCommunicationDirection::Outbound,
        'Website request confirmation emailed to audit@example.test.',
        metadata: [
            'website_lead_confirmation' => true,
            'lead_id' => $lead->id,
        ],
    );

    app(LeadConfirmationAuditConversation::class)->finalizeEmailConfirmationAudit($lead, $emailConversation->fresh());

    expect($emailConversation->fresh()->status)->toBe(ConversationStatus::Resolved);
});

test('first advisor contact resolves sibling email audit thread', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $phoneConversation = app(ConversationResolver::class)->forPhone('7195550302');
    $emailConversation = app(ConversationResolver::class)->forEmail('followup@example.test');

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Oil change',
        'contact_phone' => '7195550302',
        'contact_email' => 'followup@example.test',
        'contact_name' => 'Sam',
        'conversation_id' => $phoneConversation->id,
    ]);

    $recorder = app(ConversationRecorder::class);
    $participant = app(ConversationParticipantResolver::class)->system($emailConversation, displayName: 'Shop');

    $recorder->record(
        $emailConversation,
        $participant,
        OperationalCommunicationChannel::Email,
        OperationalCommunicationDirection::Outbound,
        'Website request confirmation emailed to followup@example.test.',
        metadata: ['website_lead_confirmation' => true],
    );

    expect($emailConversation->fresh()->status)->toBe(ConversationStatus::Open);

    $advisorParticipant = app(ConversationParticipantResolver::class)->advisor($phoneConversation, $advisor);

    $recorder->record(
        $phoneConversation,
        $advisorParticipant,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        'We can get you in tomorrow morning.',
        metadata: ['actor_user_id' => $advisor->id],
    );

    expect($emailConversation->fresh()->status)->toBe(ConversationStatus::Resolved);
});

test('audit-only email thread is excluded from needs shop projection', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $phoneConversation = app(ConversationResolver::class)->forPhone('7195550303');
    $emailConversation = app(ConversationResolver::class)->forEmail('ghost@example.test');

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes',
        'contact_phone' => '7195550303',
        'contact_email' => 'ghost@example.test',
        'conversation_id' => $phoneConversation->id,
    ]);

    $recorder = app(ConversationRecorder::class);
    $participant = app(ConversationParticipantResolver::class)->system($emailConversation, displayName: 'Shop');

    $recorder->record(
        $emailConversation,
        $participant,
        OperationalCommunicationChannel::Email,
        OperationalCommunicationDirection::Outbound,
        'Website request confirmation emailed to ghost@example.test.',
        metadata: ['website_lead_confirmation' => true],
    );

    $projection = app(CommunicationWorkboardProjection::class)->resolve($advisor);

    $needsShopIds = collect($projection['needs_shop'])->pluck('conversation_id');

    expect($needsShopIds)->not->toContain($emailConversation->id);
});
