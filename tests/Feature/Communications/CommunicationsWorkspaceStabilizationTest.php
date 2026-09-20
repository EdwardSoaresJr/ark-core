<?php

use App\Ark\Operations\Communications\CommunicationsWorkspaceProjection;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    Http::fake();
});

function stabilizationAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

function stabilizationConversation(): Conversation
{
    $customer = Customer::query()->create([
        'first_name' => 'Dana',
        'last_name' => 'Wait',
        'phone' => '7195553201',
        'email' => 'dana.wait@example.test',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195553201',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Customer,
        'posture_changed_at' => now()->subHour(),
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'body' => 'We sent the estimate.',
        'occurred_at' => now()->subHour(),
    ]);

    return $conversation->fresh();
}

test('empty communications workspace keeps the three-column landmarks', function (): void {
    $advisor = stabilizationAdvisor();
    stabilizationConversation();

    $html = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'waiting']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('ops-comms-workspace__grid')
        ->toContain('ops-comms-workspace__list')
        ->toContain('ops-comms-workspace__thread')
        ->toContain('ops-comms-workspace__context')
        ->toContain('ops-comms-workspace__thread-header')
        ->toContain('ops-comms-workspace__visit-strip')
        ->toContain('ops-comms-workspace__composer--idle')
        ->toContain('Select a conversation')
        ->toContain('Who')
        ->toContain('Current visit')
        ->toContain('Turn')
        ->toContain('Next')
        ->not->toContain('id="comms-thread-composer"');
});

test('explicit open still loads the composer without changing queue architecture', function (): void {
    $advisor = stabilizationAdvisor();
    $conversation = stabilizationConversation();

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        $conversation->id,
        null,
        null,
        null,
        null,
        'waiting',
    );

    expect($workspace['selected']['key'] ?? null)->toBe('conversation:'.$conversation->id)
        ->and($workspace['thread'])->not->toBeNull()
        ->and($workspace['thread']['composer'] ?? null)->not->toBeNull();

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'waiting',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->assertSee('Dana Wait', false)
        ->assertSee('id="comms-thread-composer"', false)
        ->assertSee('ops-comms-workspace__visit-strip', false)
        ->assertSee('Next', false);
});

test('empty fragment still returns the same workspace landmarks', function (): void {
    $advisor = stabilizationAdvisor();
    stabilizationConversation();

    $payload = $this->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', [
            'section' => 'inbox',
            'filter' => 'waiting',
        ]))
        ->assertOk()
        ->json();

    expect($payload['thread'] ?? '')
        ->toContain('Select a conversation')
        ->toContain('ops-comms-workspace__visit-strip')
        ->toContain('ops-comms-workspace__composer--idle')
        ->and($payload['context'] ?? '')
        ->toContain('Who')
        ->toContain('Current visit')
        ->toContain('Turn')
        ->toContain('Next')
        ->and($payload['list'] ?? '')
        ->toContain('data-comms-row-key');
});

test('fragment open returns thread and context without requiring a second queue architecture', function (): void {
    $advisor = stabilizationAdvisor();
    $conversation = stabilizationConversation();

    $payload = $this->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', [
            'section' => 'inbox',
            'filter' => 'waiting',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->json();

    expect($payload['unchanged'] ?? true)->toBeFalse()
        ->and($payload['thread'] ?? '')->toContain('Dana Wait')
        ->and($payload['thread'] ?? '')->toContain('ops-comms-workspace__visit-strip')
        ->and($payload['thread'] ?? '')->toContain('id="comms-thread-composer"')
        ->and($payload['context'] ?? '')->toContain('Who')
        ->and($payload['context'] ?? '')->toContain('Current visit')
        ->and($payload['context'] ?? '')->toContain('Turn');
});
