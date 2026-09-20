<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\InboundSmsConversationIngress;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
        'shop_timezone' => 'America/Denver',
    ]);
    ShopSettings::forgetCurrent();
});

function actionInboxAdvisor(string $name = 'Edward'): User
{
    return User::factory()->create(['name' => $name])->assignRole(ArkRole::Advisor->value);
}

function fakePlatformThread(string $publicId, string $e164, string $preview = 'Latest preview'): void
{
    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => function ($request) use ($publicId, $e164, $preview) {
            if (str_contains($request->url(), '/read')) {
                return Http::response(['ok' => true], 200);
            }

            if (str_contains($request->url(), '/conversations/'.$publicId)) {
                return Http::response([
                    'ok' => true,
                    'conversation' => [
                        'public_id' => $publicId,
                        'contact_address' => $e164,
                        'core_customer_id' => null,
                    ],
                    'messages' => [[
                        'public_id' => 'pm_gate',
                        'direction' => 'inbound',
                        'body' => $preview,
                        'occurred_at' => '2026-09-17T16:12:00Z',
                    ]],
                ], 200);
            }

            return Http::response([
                'ok' => true,
                'conversations' => [[
                    'public_id' => $publicId,
                    'contact_address' => $e164,
                    'core_customer_id' => null,
                    'preview' => $preview,
                    'last_message_at' => '2026-09-17T16:12:00Z',
                    'unread' => true,
                ]],
            ], 200);
        },
    ]);
}

test('gate: workflow state survives refresh logout and a new session', function () {
    $edward = actionInboxAdvisor('Edward');
    $molly = actionInboxAdvisor('Molly');
    $customer = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Avairee Foote')->customer;
    $customer->forceFill(['phone' => '7195556101'])->save();
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $work = app(ConversationWork::class);
    $conversation = $work->ensureForPhone($phone, $customer);
    $work->followUp($conversation, $edward, now()->addDay());
    $conversationId = $conversation->id;
    $due = $conversation->fresh()->follow_up_due_at?->utc()->toIso8601String();

    fakePlatformThread('pc_persist', '+17195556101', 'Still waiting');

    $this->actingAs($edward)
        ->get(route('operations.communications.inbox', ['filter' => 'waiting', 'platform_conversation' => 'pc_persist']))
        ->assertOk()
        ->assertSee('Avairee Foote');

    $this->flushSession();
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    $this->actingAs($molly)
        ->get(route('operations.communications.inbox', ['filter' => 'waiting', 'platform_conversation' => 'pc_persist']))
        ->assertOk()
        ->assertSee('Avairee Foote')
        ->assertSee('Edward');

    $fresh = Conversation::query()->findOrFail($conversationId);
    expect($fresh->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($fresh->owned_by_user_id)->toBe($edward->id)
        ->and($fresh->follow_up_due_at?->utc()->toIso8601String())->toBe($due)
        ->and($fresh->status)->toBe(ConversationStatus::Open);
});

test('gate: platform message refresh cannot overwrite core classification or assignment', function () {
    $edward = actionInboxAdvisor('Edward');
    $customer = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Jack Richards')->customer;
    $customer->forceFill(['phone' => '7195556102'])->save();
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $work = app(ConversationWork::class);
    $conversation = $work->ensureForPhone($phone, $customer);
    $work->followUp($conversation, $edward, now()->addDay());

    fakePlatformThread('pc_refresh', '+17195556102', 'Brand new inbound preview');

    $before = $conversation->fresh();

    $this->actingAs($edward)
        ->get(route('operations.communications.inbox', ['filter' => 'waiting', 'platform_conversation' => 'pc_refresh']))
        ->assertOk()
        ->assertSee('Brand new inbound preview')
        ->assertSee('Edward');

    $after = $conversation->fresh();
    expect($after->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($after->owned_by_user_id)->toBe($edward->id)
        ->and($after->status)->toBe(ConversationStatus::Open)
        ->and($after->posture_changed_at?->utc()->timestamp)->toBe($before->posture_changed_at?->utc()->timestamp)
        ->and($after->follow_up_due_at?->utc()->timestamp)->toBe($before->follow_up_due_at?->utc()->timestamp);
});

test('gate: two advisors cannot silently overwrite each other', function () {
    $edward = actionInboxAdvisor('Edward');
    $molly = actionInboxAdvisor('Molly');
    $customer = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Dallas Driver')->customer;
    $customer->forceFill(['phone' => '7195556103'])->save();

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => Http::response([
            'ok' => true,
            'conversation' => [
                'public_id' => 'pc_race',
                'contact_address' => '+17195556103',
                'core_customer_id' => null,
            ],
            'messages' => [],
        ], 200),
    ]);

    $this->actingAs($edward)
        ->post(route('operations.communications.platform-conversations.work', ['platformConversation' => 'pc_race']), [
            'action' => 'follow_up',
            'due_at' => '2026-09-18T08:00',
        ])
        ->assertRedirect();

    $conversation = Conversation::query()
        ->where('contact_surface', ConversationContactSurface::Phone)
        ->where('contact_address', '7195556103')
        ->sole();

    expect($conversation->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($conversation->owned_by_user_id)->toBe($edward->id);

    $this->actingAs($molly)
        ->from(route('operations.communications.inbox', ['filter' => 'waiting']))
        ->post(route('operations.communications.platform-conversations.work', ['platformConversation' => 'pc_race']), [
            'action' => 'resolve',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($conversation->fresh()->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($conversation->fresh()->status)->toBe(ConversationStatus::Open)
        ->and($conversation->fresh()->owned_by_user_id)->toBe($edward->id);

    $this->actingAs($molly)
        ->post(route('operations.communications.platform-conversations.work', ['platformConversation' => 'pc_race']), [
            'action' => 'resolve',
            'posture_changed_at' => $conversation->fresh()->posture_changed_at->utc()->toIso8601String(),
        ])
        ->assertRedirect();

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Resolved)
        ->and($conversation->fresh()->owned_by_user_id)->toBe($edward->id);
});

test('gate: follow-up deadlines and overdue transitions use the shop timezone', function () {
    $edward = actionInboxAdvisor();
    $customer = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Maricruz Floor')->customer;
    $customer->forceFill(['phone' => '7195556104'])->save();

    $dueLocal = ShopDisplayTimezone::parseLocal('2026-09-18T08:00');
    expect($dueLocal->timezoneName)->toBe('America/Denver')
        ->and($dueLocal->copy()->utc()->format('Y-m-d H:i'))->toBe('2026-09-18 14:00');

    fakePlatformThread('pc_due', '+17195556104', 'Need a callback');

    $this->actingAs($edward)
        ->post(route('operations.communications.platform-conversations.work', ['platformConversation' => 'pc_due']), [
            'action' => 'follow_up',
            'due_at' => '2026-09-18T08:00',
        ])
        ->assertRedirect();

    $conversation = Conversation::query()
        ->where('contact_surface', ConversationContactSurface::Phone)
        ->where('contact_address', '7195556104')
        ->sole();
    $work = app(ConversationWork::class);

    expect($conversation->follow_up_due_at->utc()->format('Y-m-d H:i'))->toBe('2026-09-18 14:00')
        ->and(ShopDisplayTimezone::format($conversation->follow_up_due_at, 'D M j · g:i A'))->toBe('Fri Sep 18 · 8:00 AM');

    Carbon::setTestNow(Carbon::parse('2026-09-18 13:59:00', 'UTC'));
    expect($work->lane($conversation->fresh()))->toBe('waiting');

    Carbon::setTestNow(Carbon::parse('2026-09-18 14:00:00', 'UTC'));
    expect($work->lane($conversation->fresh()))->toBe('needs');

    Carbon::setTestNow();
});

test('gate: unknown numbers stay actionable and keep history after customer match', function () {
    $edward = actionInboxAdvisor();
    $phone = '7195556105';

    app(InboundSmsConversationIngress::class)->ingest(new InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: $phone,
        providerMessageId: 'SM-unknown-1',
        channel: OperationalCommunicationChannel::Sms,
        body: 'Do you have my car?',
    ));

    $conversation = Conversation::query()
        ->where('contact_surface', ConversationContactSurface::Phone)
        ->where('contact_address', $phone)
        ->sole();

    expect($conversation->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and(app(ConversationWork::class)->lane($conversation))->toBe('needs');

    $conversationId = $conversation->id;

    fakePlatformThread('pc_unknown', '+17195556105', 'Do you have my car?');

    $this->actingAs($edward)
        ->get(route('operations.communications.inbox', ['filter' => 'needs', 'platform_conversation' => 'pc_unknown']))
        ->assertOk()
        ->assertSee('(719) 555-6105')
        ->assertSee('Unknown Customer')
        ->assertSee('Do you have my car?');

    Customer::query()->create([
        'first_name' => 'Unknown',
        'last_name' => 'Matched',
        'phone' => $phone,
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);

    $this->actingAs($edward)
        ->get(route('operations.communications.inbox', ['filter' => 'needs', 'platform_conversation' => 'pc_unknown']))
        ->assertOk()
        ->assertSee('Unknown Matched')
        ->assertSee('Do you have my car?');

    $fresh = Conversation::query()->findOrFail($conversationId);
    expect($fresh->id)->toBe($conversationId)
        ->and($fresh->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($fresh->contact_address)->toBe($phone);
});

test('gate: platform outage cannot erase conversations or mark them resolved', function () {
    $edward = actionInboxAdvisor();
    $customer = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Kept Open')->customer;
    $customer->forceFill(['phone' => '7195556106'])->save();
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $work = app(ConversationWork::class);
    $conversation = $work->ensureForPhone($phone, $customer);
    $work->followUp($conversation, $edward, now()->addDay());

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => Http::response([
            'ok' => false,
            'message' => 'ARK Communications is unavailable.',
        ], 503),
    ]);

    $this->actingAs($edward)
        ->get(route('operations.communications.inbox', ['filter' => 'waiting']))
        ->assertOk()
        ->assertSee('ARK Communications is unavailable.')
        ->assertSee('(719) 555-6106');

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Open)
        ->and($conversation->fresh()->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and(Conversation::query()->whereKey($conversation->id)->exists())->toBeTrue();
});

test('gate: calls SMS delivery and recording routes remain registered', function () {
    expect(route('operations.communications.calls'))->toContain('/app/communications/calls')
        ->and(route('webhooks.communications.twilio.voice.recording'))->toContain('/webhooks/communications/twilio/voice/recording')
        ->and(route('webhooks.communications.twilio.messaging.incoming'))->toContain('/webhooks/communications/twilio/messaging/incoming')
        ->and(route('operations.telephony.call-sessions.recording', ['callSession' => 1]))->toContain('/app/telephony/call-sessions/1/recording');
});
