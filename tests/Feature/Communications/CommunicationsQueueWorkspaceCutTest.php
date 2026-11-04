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
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\DB;
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
            ->and($measured['result']['list_count'])->toBe(min($target, CommunicationsWorkspaceProjection::LIST_PAGE_SIZE))
            ->and($measured['result']['list_total'])->toBe($target);

        $counts[$target] = $measured['count'];
    }

    expect($counts[100])->toBeLessThan(80)
        ->and($counts[500])->toBeLessThan(80)
        ->and($counts[1000])->toBeLessThan(80)
        ->and($counts[1000] / max(1, $counts[100]))->toBeLessThan(3);
});

function pagingConversations(int $count, ?User $owner = null, int $minutesAgo = 0): void
{
    $work = app(ConversationWork::class);

    foreach (range(1, $count) as $index) {
        $conversation = $work->ensureForPhone('719556'.str_pad((string) (Conversation::query()->count() + 1), 4, '0', STR_PAD_LEFT));
        $work->markNeedsAttention($conversation);
        $conversation->forceFill([
            'owned_by_user_id' => $owner?->id,
            'posture_changed_at' => now()->subMinutes($minutesAgo + $index),
        ])->save();
    }
}

test('the inbox lane loads one page and offers the next', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    pagingConversations(CommunicationsWorkspaceProjection::LIST_PAGE_SIZE + 10);

    $response = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('data-comms-list-more', false)
        ->assertSee('shown=100', false);

    $workspace = $response->viewData('workspace');
    expect($workspace['list_items'])->toHaveCount(CommunicationsWorkspaceProjection::LIST_PAGE_SIZE)
        ->and($workspace['list_truncated'])->toBeTrue()
        ->and($workspace['list_total'])->toBe(CommunicationsWorkspaceProjection::LIST_PAGE_SIZE + 10);

    $next = $this->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', ['section' => 'inbox', 'filter' => 'needs', 'shown' => 100]))
        ->assertOk()
        ->json('list');

    expect(substr_count($next, 'data-comms-list-row'))->toBe(CommunicationsWorkspaceProjection::LIST_PAGE_SIZE + 10)
        ->and($next)->not->toContain('data-comms-list-more');
});

test('the advisor filter queries the whole lane, not the loaded page', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    pagingConversations(CommunicationsWorkspaceProjection::LIST_PAGE_SIZE + 5);
    pagingConversations(3, $advisor, minutesAgo: 600);

    $workspace = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs', 'owner' => 'mine']))
        ->assertOk()
        ->viewData('workspace');

    expect($workspace['list_items'])->toHaveCount(3)
        ->and($workspace['owner_counts'])->toBe([
            'everyone' => CommunicationsWorkspaceProjection::LIST_PAGE_SIZE + 8,
            'mine' => 3,
            'unassigned' => CommunicationsWorkspaceProjection::LIST_PAGE_SIZE + 5,
        ])
        ->and($workspace['list_truncated'])->toBeFalse();
});

test('list rows do not load their owner one query at a time', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    pagingConversations(20, $advisor);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($advisor)->get(route('operations.communications.inbox', ['filter' => 'needs']))->assertOk();
    $ownerLookups = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'from "users" where "users"."id" = ?'))
        ->count();

    expect($ownerLookups)->toBeLessThan(5);
});

test('the requested page size is bounded', function (): void {
    expect(CommunicationsWorkspaceProjection::listLimit(null))->toBe(50)
        ->and(CommunicationsWorkspaceProjection::listLimit(51))->toBe(100)
        ->and(CommunicationsWorkspaceProjection::listLimit(100000))->toBe(CommunicationsWorkspaceProjection::LIST_MAX);
});
