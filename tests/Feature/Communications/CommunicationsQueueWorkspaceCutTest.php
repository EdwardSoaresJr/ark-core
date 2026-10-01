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
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
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

function queueCutAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

function queueCutWaitingConversation(string $phone, string $first = 'Ann', string $last = 'Cox'): Conversation
{
    $customer = Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'email' => strtolower($first).'.'.strtolower($last).'.'.$phone.'@example.test',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => $phone,
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Customer,
        'posture_changed_at' => now()->subHours(21),
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
        'occurred_at' => now()->subHours(21),
    ]);

    return $conversation->fresh();
}

test('waiting queue does not auto-open the relationship workspace', function (): void {
    $advisor = queueCutAdvisor();
    $conversation = queueCutWaitingConversation('7195553101');

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        null,
        null,
        null,
        null,
        null,
        'waiting',
    );

    expect($workspace['selected'])->toBeNull()
        ->and($workspace['thread'])->toBeNull()
        ->and($workspace['context'])->toBeNull()
        ->and($workspace['list_count'])->toBe(1)
        ->and($workspace['list_items'][0]['key'] ?? null)->toBe('conversation:'.$conversation->id)
        ->and($workspace['list_items'][0]['headline'] ?? null)->toBe('Ann Cox')
        ->and($workspace['list_items'][0]['snippet'] ?? null)->toBe('We sent the estimate.');

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'waiting']))
        ->assertOk()
        ->assertSee('Ann Cox', false)
        ->assertSee('Select a conversation', false)
        ->assertSee('Select a conversation to reply', false)
        ->assertSee('No current visit', false)
        ->assertSee('Shop Context', false)
        ->assertDontSee('id="comms-thread-composer"', false)
        ->assertDontSee('Open conversation', false);
});

test('explicit open loads the existing relationship workspace', function (): void {
    $advisor = queueCutAdvisor();
    $conversation = queueCutWaitingConversation('7195553102', 'Pedro', 'Benavides');

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
        ->and($workspace['thread']['composer'] ?? null)->not->toBeNull()
        ->and($workspace['context'])->not->toBeNull()
        ->and($workspace['thread']['identity']['name'] ?? null)->toBe('Pedro Benavides')
        ->and($workspace['thread']['identity']['turn_label'] ?? null)->toBe('Waiting on customer');

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'waiting',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->assertSee('Pedro Benavides', false)
        ->assertSee('id="comms-thread-composer"', false)
        ->assertSee('Shop Context', false);
});

test('waiting list does not call per-row customer context resolve', function (): void {
    $advisor = queueCutAdvisor();
    queueCutWaitingConversation('7195553103', 'Dana', 'Wait');
    queueCutWaitingConversation('7195553104', 'Sam', 'Shop');

    $this->partialMock(CustomerCallContextResolver::class, function ($mock): void {
        $mock->shouldNotReceive('resolve');
        $mock->shouldNotReceive('resolveForCustomer');
        $mock->shouldNotReceive('mapForAttentionList');
        $mock->shouldNotReceive('resolveForAttentionList');
    });

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        null,
        null,
        null,
        null,
        null,
        'waiting',
    );

    expect($workspace['thread'])->toBeNull()
        ->and($workspace['list_count'])->toBe(2);
});

test('inbox fragment with a matching signature does not rebuild the workspace', function (): void {
    $advisor = queueCutAdvisor();
    queueCutWaitingConversation('7195553105');

    $signature = app(CommunicationsWorkspaceProjection::class)->pollSignature('waiting');

    $measured = measureQueries(function () use ($advisor, $signature) {
        return $this->actingAs($advisor)
            ->getJson(route('operations.communications.workspace.fragment', [
                'section' => 'inbox',
                'filter' => 'waiting',
                'signature' => $signature,
            ]))
            ->assertOk()
            ->json();
    });

    expect($measured['result']['unchanged'] ?? null)->toBeTrue()
        ->and($measured['result']['list'] ?? null)->toBeNull()
        ->and($measured['result']['thread'] ?? null)->toBeNull()
        ->and($measured['count'])->toBeLessThan(40);
});

test('waiting queue query cost does not scale with relationship work per row', function (): void {
    $advisor = queueCutAdvisor();
    $now = now();
    $counts = [];

    foreach ([100, 500, 1000] as $target) {
        $existing = Conversation::query()
            ->where('status', ConversationStatus::Open->value)
            ->where('waiting_on', ConversationWaitingOn::Customer->value)
            ->count();

        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $rows[] = [
                'contact_surface' => ConversationContactSurface::Phone->value,
                'contact_address' => '555'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'status' => ConversationStatus::Open->value,
                'waiting_on' => ConversationWaitingOn::Customer->value,
                'posture_changed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Conversation::query()->insert($chunk);
        }

        $measured = measureQueries(function () use ($advisor) {
            return app(CommunicationsWorkspaceProjection::class)->inbox(
                $advisor,
                null,
                null,
                null,
                null,
                null,
                'waiting',
            );
        });

        expect($measured['result']['selected'])->toBeNull()
            ->and($measured['result']['thread'])->toBeNull()
            ->and($measured['result']['list_count'])->toBe($target);

        $counts[$target] = $measured['count'];
    }

    expect($counts[100])->toBeLessThan(80)
        ->and($counts[500])->toBeLessThan(80)
        ->and($counts[1000])->toBeLessThan(80)
        ->and($counts[1000] / max(1, $counts[100]))->toBeLessThan(3);
});
