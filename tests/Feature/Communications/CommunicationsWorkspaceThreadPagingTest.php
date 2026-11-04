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

function pagingAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

function pagingConversation(int $messages): Conversation
{
    $customer = Customer::query()->create([
        'first_name' => 'Lena',
        'last_name' => 'Longthread',
        'phone' => '7195559000',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195559000',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Shop,
        'posture_changed_at' => now(),
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    for ($i = 1; $i <= $messages; $i++) {
        ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'conversation_participant_id' => $participant->id,
            'channel' => OperationalCommunicationChannel::Sms,
            'direction' => OperationalCommunicationDirection::Inbound,
            'body' => sprintf('Paged message %03d', $i),
            'occurred_at' => now()->subMinutes($messages - $i + 1),
        ]);
    }

    return $conversation->fresh();
}

function pagingFragment($test, User $advisor, Conversation $conversation, array $query = []): array
{
    return $test->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', [
            'section' => 'inbox',
            'filter' => 'needs',
            'conversation' => $conversation->id,
            ...$query,
        ]))
        ->assertOk()
        ->json();
}

test('a long thread opens on its latest page and pages back through every earlier message in order', function (): void {
    $advisor = pagingAdvisor();
    $conversation = pagingConversation(180);

    $thread = app(CommunicationsWorkspaceProjection::class)
        ->inbox($advisor, $conversation->id, null, null, null, null, 'needs')['thread'];

    expect($thread['events'])->toHaveCount(CommunicationsWorkspaceProjection::THREAD_PAGE)
        ->and($thread['event_keys'])->toHaveCount(CommunicationsWorkspaceProjection::THREAD_PAGE)
        ->and($thread['older'])->toHaveKeys(['before', 'key']);

    $first = pagingFragment($this, $advisor, $conversation)['thread'];

    expect($first)
        ->toContain('Paged message 180')
        ->toContain('Paged message 131')
        ->not->toContain('Paged message 130')
        ->toContain('data-comms-older-load')
        ->and(substr_count($first, 'data-comms-entry="'))->toBe(50);

    $older = pagingFragment($this, $advisor, $conversation, [
        'older_before' => $thread['older']['before'],
        'older_key' => $thread['older']['key'],
        'loaded' => 50,
    ]);

    expect($older['count'])->toBe(CommunicationsWorkspaceProjection::OLDER_PAGE)
        ->and($older['older'])->not->toBeNull()
        ->and($older['older_html'])->toContain('Paged message 130')
        ->toContain('Paged message 031')
        ->not->toContain('Paged message 131')
        ->not->toContain('Paged message 030')
        ->and(strpos($older['older_html'], 'Paged message 031'))
        ->toBeLessThan(strpos($older['older_html'], 'Paged message 130'));

    $oldest = pagingFragment($this, $advisor, $conversation, [
        'older_before' => $older['older']['before'],
        'older_key' => $older['older']['key'],
        'loaded' => 150,
    ]);

    expect($oldest['count'])->toBe(30)
        ->and($oldest['older'])->toBeNull()
        ->and($oldest['older_html'])->toContain('Paged message 001')
        ->toContain('Paged message 030')
        ->not->toContain('Paged message 031');
});

test('a short thread has no earlier-messages control', function (): void {
    $advisor = pagingAdvisor();
    $conversation = pagingConversation(3);

    expect(pagingFragment($this, $advisor, $conversation)['thread'])
        ->toContain('Paged message 001')
        ->not->toContain('data-comms-older-load');
});

test('message details are written for people and the composer and context lane stay clean', function (): void {
    $advisor = pagingAdvisor();
    $conversation = pagingConversation(2);

    $workspace = app(CommunicationsWorkspaceProjection::class)
        ->inbox($advisor, $conversation->id, null, null, null, null, 'needs');

    expect($workspace['thread']['composer'] ?? [])->not->toHaveKey('draft_reply');

    $payload = pagingFragment($this, $advisor, $conversation);

    expect($payload['thread'])
        ->toContain('data-comms-details')
        ->toContain('Details')
        ->toContain('Text message')
        ->toContain('from Lena Longthread')
        ->not->toContain('sorry for the delay')
        ->and(strip_tags($payload['thread']))
        ->not->toContain('Evidence')
        ->not->toContain('conversation_message')
        ->not->toContain('ConversationMessage')
        ->and($payload['context'])
        ->not->toContain('comms-context-internal-note')
        ->not->toContain('Internal notes');
});
