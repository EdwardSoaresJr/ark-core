<?php

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\HostedCallOutcome;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    config()->set('broadcasting.default', 'null');
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);
});

test('the inbox row shows the platform unread flag without changing the lane', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $work = app(ConversationWork::class);
    $needs = $work->markNeedsAttention($work->ensureForPhone('7195550501'));
    $waiting = $work->assign($work->wait($work->ensureForPhone('7195550502')), $advisor);
    inboxContextCustomer('Unread Needs', '7195550501');
    inboxContextCustomer('Unread Waiting', '7195550502');

    fakeInboxThreads([
        inboxThread('pc_needs', '+17195550501', [], unread: true, preview: 'Need a tow'),
        inboxThread('pc_waiting', '+17195550502', [], unread: true, preview: 'I will wait'),
    ]);

    $needsPage = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('Unread Needs')
        ->assertSee('Needs attention')
        ->assertDontSee('Unread Waiting');

    expect(substr_count($needsPage->getContent(), 'ops-comms-inbox__unread'))->toBe(1)
        ->and($needs->fresh()->status)->toBe(ConversationStatus::Open)
        ->and($needs->fresh()->waiting_on)->toBe(ConversationWaitingOn::Shop);

    $before = inboxPosture($waiting);

    $waitingPage = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'waiting',
            'platform_conversation' => 'pc_waiting',
        ]))
        ->assertOk()
        ->assertSee('Unread Waiting')
        ->assertSee('Waiting')
        ->assertSee('ops-comms-inbox__unread', false);

    expect(inboxPosture($waiting->fresh()))->toBe($before)
        ->and($waiting->fresh()->status)->toBe(ConversationStatus::Open)
        ->and($waiting->fresh()->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($waiting->fresh()->owned_by_user_id)->toBe($advisor->id)
        ->and($waiting->fresh()->resolved_at)->toBeNull()
        ->and(substr_count($waitingPage->getContent(), 'ops-comms-inbox__unread'))->toBe(1);
});

test('a missed call appears once in the thread ahead of a later text', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $call = inboxCall('7195550510', 'CA-context-missed', HostedCallOutcome::Missed, CallSessionStatus::Missed, Carbon::parse('2026-09-29 15:05:00', 'UTC'));

    fakeInboxThreads([
        inboxThread('pc_chrono', '+17195550510', [
            [
                'public_id' => 'pm_after',
                'direction' => 'inbound',
                'body' => 'After the call',
                'occurred_at' => '2026-09-29T15:10:00Z',
            ],
            [
                'public_id' => 'pm_before',
                'direction' => 'inbound',
                'body' => 'Before the call',
                'occurred_at' => '2026-09-29T15:00:00Z',
            ],
        ], preview: 'Lane preview', lastMessageAt: '2026-09-29T15:10:00Z'),
    ]);

    $response = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'platform_conversation' => 'pc_chrono',
        ]))
        ->assertOk()
        ->assertSee('Before the call')
        ->assertSee('Missed call')
        ->assertSee('After the call');

    $html = $response->getContent();
    $thread = substr($html, (int) strpos($html, 'id="comms-workspace-thread-messages"'));
    $callAt = strpos($thread, 'data-call-session="'.$call->id.'"');
    expect($callAt)->not->toBeFalse()
        ->and(substr_count($html, 'data-call-session="'.$call->id.'"'))->toBe(1)
        ->and(strpos($thread, 'Before the call'))->toBeLessThan($callAt)
        ->and($callAt)->toBeLessThan(strpos($thread, 'After the call'))
        ->and(ConversationMessage::query()->where('body', 'After the call')->exists())->toBeFalse()
        ->and(ConversationMessage::query()->where('body', 'Before the call')->exists())->toBeFalse();
});

test('the latest missed call is explained in the thread it labels', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $call = inboxCall('7195550511', 'CA-context-latest', HostedCallOutcome::Missed, CallSessionStatus::Missed, Carbon::parse('2026-09-29 15:10:00', 'UTC'));

    fakeInboxThreads([
        inboxThread('pc_latest', '+17195550511', [
            [
                'public_id' => 'pm_earlier',
                'direction' => 'inbound',
                'body' => 'Earlier text',
                'occurred_at' => '2026-09-29T15:00:00Z',
            ],
        ], preview: 'Lane preview', lastMessageAt: '2026-09-29T15:00:00Z'),
    ]);

    $response = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'platform_conversation' => 'pc_latest',
        ]))
        ->assertOk()
        ->assertSee('Missed call')
        ->assertSee('Earlier text');

    $html = $response->getContent();
    $thread = substr($html, (int) strpos($html, 'id="comms-workspace-thread-messages"'));
    $callAt = strpos($thread, 'data-call-session="'.$call->id.'"');
    expect(substr_count($html, 'data-call-session="'.$call->id.'"'))->toBe(1)
        ->and(strpos($thread, 'Earlier text'))->toBeLessThan($callAt);
});

test('voicemail in the thread uses the existing playback route', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $sid = 'RE00000000000000000000000000000012';
    $call = inboxCall('7195550512', 'CA-context-vm', HostedCallOutcome::VoicemailLeft, CallSessionStatus::Missed, now()->subMinutes(8), [
        'voicemail_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/'.$sid,
        'voicemail_sid' => $sid,
    ]);

    fakeInboxThreads([
        inboxThread('pc_vm', '+17195550512', [], preview: 'Voicemail'),
    ]);

    $playback = route('operations.telephony.call-sessions.recording', [
        'callSession' => $call,
        'kind' => 'voicemail',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'platform_conversation' => 'pc_vm',
        ]))
        ->assertOk()
        ->assertSee('Voicemail')
        ->assertSee($playback, false)
        ->assertDontSee('Answered call')
        ->assertDontSee('api.twilio.com', false);
});

test('an answered call shows authoritative talk time and its recording is not voicemail', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $sid = 'RE00000000000000000000000000000013';
    $started = Carbon::parse('2026-09-29 16:00:00', 'UTC');
    $call = inboxCall('7195550513', 'CA-context-answered', HostedCallOutcome::Completed, CallSessionStatus::Completed, $started, [
        'answered_at' => $started->copy()->addSeconds(5),
        'ended_at' => $started->copy()->addSeconds(35),
        'dial_duration_seconds' => 125,
        'recording_duration_seconds' => 999,
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/'.$sid,
        'recording_sid' => $sid,
    ]);

    fakeInboxThreads([
        inboxThread('pc_answered', '+17195550513', [], preview: 'Answered call', lastMessageAt: '2026-09-29T15:00:00Z'),
    ]);

    $playback = route('operations.telephony.call-sessions.recording', [
        'callSession' => $call,
        'kind' => 'recording',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'all',
            'platform_conversation' => 'pc_answered',
        ]))
        ->assertOk()
        ->assertSee('Answered call')
        ->assertSee('2m 5s')
        ->assertSee('Recording')
        ->assertSee($playback, false)
        ->assertDontSee('Voicemail')
        ->assertDontSee('999')
        ->assertDontSee('api.twilio.com', false);
});

test('an unknown call stays unknown in the thread', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    inboxCall('7195550514', 'CA-context-unknown', HostedCallOutcome::Unknown, CallSessionStatus::Unknown, now()->subMinutes(6));

    fakeInboxThreads([
        inboxThread('pc_unknown', '+17195550514', [], preview: 'Unknown call'),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'all',
            'platform_conversation' => 'pc_unknown',
        ]))
        ->assertOk()
        ->assertSee('Unknown call')
        ->assertDontSee('Missed call')
        ->assertDontSee('Answered call')
        ->assertDontSee('Voicemail');
});

test('mark handled changes only that call', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $ended = Carbon::parse('2026-09-29 17:04:00', 'UTC');
    $other = inboxCall('7195550515', 'CA-context-other', HostedCallOutcome::Missed, CallSessionStatus::Missed, Carbon::parse('2026-09-29 16:40:00', 'UTC'));
    $call = inboxCall('7195550515', 'CA-context-handled', HostedCallOutcome::Missed, CallSessionStatus::Missed, Carbon::parse('2026-09-29 17:00:00', 'UTC'), [
        'ended_at' => $ended,
    ]);
    $conversation = Conversation::query()->where('contact_address', '7195550515')->firstOrFail();
    $work = app(ConversationWork::class);
    $due = now()->addDay()->startOfMinute();
    $work->assign($conversation, $advisor);
    $work->followUp($conversation, $advisor, $due);
    $conversation->refresh();

    fakeInboxThreads([
        inboxThread('pc_handled', '+17195550515', [
            [
                'public_id' => 'pm_handled',
                'direction' => 'inbound',
                'body' => 'Please call me back',
                'occurred_at' => '2026-09-29T16:00:00Z',
            ],
        ], preview: 'Please call me back', lastMessageAt: '2026-09-29T16:00:00Z'),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'waiting',
            'platform_conversation' => 'pc_handled',
        ]))
        ->assertOk()
        ->assertSee('Missed call')
        ->assertSee(route('operations.communications.inbox.calls.mark-handled', $call), false);

    $this->actingAs($advisor)
        ->post(route('operations.communications.inbox.calls.mark-handled', $call), [
            'filter' => 'waiting',
            'owner' => 'everyone',
            'platform_conversation' => 'pc_handled',
            'conversation' => $conversation->id,
        ])
        ->assertRedirect(route('operations.communications.inbox', [
            'filter' => 'waiting',
            'platform_conversation' => 'pc_handled',
            'conversation' => $conversation->id,
        ]));

    $call->refresh();
    $other->refresh();
    $conversation->refresh();

    expect($call->worked_at)->not->toBeNull()
        ->and($other->worked_at)->toBeNull()
        ->and($call->status)->toBe(CallSessionStatus::Missed)
        ->and($call->hosted_outcome)->toBe(HostedCallOutcome::Missed)
        ->and($call->ended_at?->equalTo($ended))->toBeTrue()
        ->and($conversation->status)->toBe(ConversationStatus::Open)
        ->and($conversation->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($conversation->owned_by_user_id)->toBe($advisor->id)
        ->and($conversation->resolved_at)->toBeNull()
        ->and($conversation->follow_up_due_at?->equalTo($due))->toBeTrue()
        ->and(ConversationMessage::query()->where('body', 'Please call me back')->exists())->toBeFalse();
});

test('the rail lists every open repair order', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = inboxContextCustomer('Multi Visit', '7195550516');
    $civic = inboxRepairOrder($customer, RepairOrderStatus::InProgress, 2019, 'Toyota', 'Camry');
    $truck = inboxRepairOrder($customer, RepairOrderStatus::Estimate, 2016, 'Ford', 'F-150');
    $closed = inboxRepairOrder($customer, RepairOrderStatus::Closed, 2012, 'Honda', 'Civic');

    fakeInboxThreads([
        inboxThread('pc_ros', '+17195550516', [
            [
                'public_id' => 'pm_ros',
                'direction' => 'inbound',
                'body' => 'Which car is ready?',
                'occurred_at' => now()->toIso8601String(),
            ],
        ], preview: 'Which car is ready?'),
    ]);

    $response = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'platform_conversation' => 'pc_ros',
        ]))
        ->assertOk()
        ->assertSee('2019 Toyota Camry')
        ->assertSee('2016 Ford F-150')
        ->assertSee('RO '.$civic->repair_order_id)
        ->assertSee('RO '.$truck->repair_order_id);

    $html = $response->getContent();
    expect(substr_count($html, 'data-open-ro='))->toBe(2)
        ->and($html)->toContain('data-open-ro="RO '.$civic->repair_order_id.'"')
        ->and($html)->toContain('data-open-ro="RO '.$truck->repair_order_id.'"')
        ->and($html)->not->toContain('data-open-ro="RO '.$closed->repair_order_id.'"');
});

/**
 * @param  list<array<string, mixed>>  $threads
 */
function fakeInboxThreads(array $threads): void
{
    $byId = [];
    foreach ($threads as $thread) {
        $byId[$thread['public_id']] = $thread;
    }

    Http::fake(function ($request) use ($byId) {
        $url = $request->url();

        if (str_contains($url, '/voice/recordings/availability')) {
            $body = json_decode($request->body(), true);

            return Http::response([
                'ok' => true,
                'available' => is_array($body) ? array_values($body) : [],
            ]);
        }

        if (str_contains($url, '/read')) {
            return Http::response(['ok' => true], 200);
        }

        foreach ($byId as $publicId => $thread) {
            if (str_contains($url, '/conversations/'.$publicId)) {
                return Http::response([
                    'ok' => true,
                    'conversation' => [
                        'public_id' => $publicId,
                        'contact_address' => $thread['contact_address'],
                    ],
                    'messages' => $thread['messages'],
                ], 200);
            }
        }

        return Http::response([
            'ok' => true,
            'conversations' => array_map(fn (array $thread): array => [
                'public_id' => $thread['public_id'],
                'contact_address' => $thread['contact_address'],
                'last_message_at' => $thread['last_message_at'],
                'preview' => $thread['preview'],
                'delivery_status' => 'received',
                'unread' => $thread['unread'],
            ], array_values($byId)),
        ], 200);
    });
}

/**
 * @param  list<array<string, mixed>>  $messages
 * @return array<string, mixed>
 */
function inboxThread(
    string $publicId,
    string $phone,
    array $messages,
    bool $unread = false,
    string $preview = '',
    ?string $lastMessageAt = null,
): array {
    return [
        'public_id' => $publicId,
        'contact_address' => $phone,
        'messages' => $messages,
        'unread' => $unread,
        'preview' => $preview,
        'last_message_at' => $lastMessageAt ?? now()->toIso8601String(),
    ];
}

function inboxContextCustomer(string $name, string $phone): Customer
{
    [$first, $last] = array_pad(explode(' ', $name, 2), 2, 'Customer');

    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'email' => $phone.'@example.test',
    ]);
}

/**
 * @param  array<string, mixed>  $extra
 */
function inboxCall(
    string $phone,
    string $sid,
    HostedCallOutcome $outcome,
    CallSessionStatus $status,
    Carbon $started,
    array $extra = [],
): CallSession {
    return CallSession::query()->create(array_merge([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => $sid,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+1'.$phone,
        'to_number' => '+17195550100',
        'normalized_from' => $phone,
        'status' => $status,
        'hosted_outcome' => $outcome,
        'started_at' => $started,
        'ended_at' => $extra['ended_at'] ?? $started->copy()->addMinute(),
    ], $extra));
}

function inboxRepairOrder(Customer $customer, RepairOrderStatus $status, int $year, string $make, string $model): RepairOrder
{
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => $year,
        'make' => $make,
        'model' => $model,
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => $year.' '.$make.' '.$model,
        'opened_at' => now(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function inboxPosture(Conversation $conversation): array
{
    return [
        'status' => $conversation->status?->value,
        'waiting_on' => $conversation->waiting_on?->value,
        'owned_by_user_id' => $conversation->owned_by_user_id,
        'resolved_at' => $conversation->resolved_at?->toIso8601String(),
        'follow_up_due_at' => $conversation->follow_up_due_at?->toIso8601String(),
    ];
}
