<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Messaging\InboundSmsConversationIngress;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
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
    ]);
});

test('inbound customer message opens Needs attention and outbound send does not close it', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Avairee Foote')->customer;
    $customer->forceFill(['phone' => '7195550101'])->save();
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $work = app(ConversationWork::class);

    $conversation = $work->ensureForPhone($phone, $customer);
    $work->markNeedsAttention($conversation);
    $work->recordOutboundOwner($conversation->fresh(), $advisor);

    $conversation->refresh();

    expect($conversation->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($conversation->status)->toBe(ConversationStatus::Open)
        ->and($conversation->owned_by_user_id)->toBe($advisor->id)
        ->and($work->lane($conversation))->toBe('needs');

    $work->followUp($conversation, $advisor, now()->addDay());
    expect($work->lane($conversation->fresh()))->toBe('waiting')
        ->and($conversation->fresh()->waiting_on)->toBe(ConversationWaitingOn::Customer);

    $conversation->forceFill(['follow_up_due_at' => now()->subHour()])->save();
    expect($work->lane($conversation->fresh()))->toBe('needs');

    $work->resolve($conversation->fresh(), $advisor);
    expect($work->lane($conversation->fresh()))->toBe('resolved')
        ->and($conversation->fresh()->status)->toBe(ConversationStatus::Resolved);
});

test('platform inbound without core mirror still marks the conversation Needs attention', function () {
    $customer = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Jack Richards')->customer;
    $customer->forceFill(['phone' => '7195550102'])->save();
    $phone = PhoneNumber::normalize((string) $customer->phone);

    $result = app(InboundSmsConversationIngress::class)->ingest(new InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: $phone,
        providerMessageId: 'SM-action-inbox-1',
        channel: OperationalCommunicationChannel::Sms,
        body: 'Is there an update?',
    ));

    expect($result['message'])->toBeNull();

    $conversation = Conversation::query()
        ->where('contact_surface', ConversationContactSurface::Phone)
        ->where('contact_address', $phone)
        ->sole();

    expect($conversation->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($conversation->status)->toBe(ConversationStatus::Open)
        ->and(app(ConversationWork::class)->lane($conversation))->toBe('needs');
});

test('action inbox classifies platform threads from durable conversation work', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Avairee Foote');
    $customer = $repairOrder->customer;
    $customer->forceFill(['phone' => '7195557700'])->save();
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $work = app(ConversationWork::class);
    $work->markNeedsAttention($work->ensureForPhone($phone, $customer));

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => function ($request) {
            if (str_contains($request->url(), '/read')) {
                return Http::response(['ok' => true], 200);
            }

            if (str_contains($request->url(), '/conversations/pc_floor')) {
                return Http::response([
                    'ok' => true,
                    'conversation' => [
                        'public_id' => 'pc_floor',
                        'contact_address' => '+17195557700',
                        'core_customer_id' => null,
                    ],
                    'messages' => [[
                        'public_id' => 'pm_in_1',
                        'direction' => 'inbound',
                        'body' => 'Is there an update?',
                        'occurred_at' => '2026-09-16T16:12:00Z',
                    ]],
                ], 200);
            }

            return Http::response([
                'ok' => true,
                'conversations' => [[
                    'public_id' => 'pc_floor',
                    'contact_address' => '+17195557700',
                    'core_customer_id' => null,
                    'preview' => 'Is there an update?',
                    'last_message_at' => '2026-09-16T16:12:00Z',
                ]],
            ], 200);
        },
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'platform_conversation' => 'pc_floor',
        ]))
        ->assertOk()
        ->assertSee('Needs attention')
        ->assertSee('Waiting')
        ->assertSee('Resolved')
        ->assertSee('Avairee Foote')
        ->assertSee('Is there an update?')
        ->assertSee('Assign')
        ->assertSee('Follow-up')
        ->assertSee('Resolve');

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'waiting']))
        ->assertOk()
        ->assertSee('Nothing here yet.')
        ->assertSee('Waiting');
});

test('follow-up moves a platform thread to Waiting', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Jack Richards')->customer;
    $customer->forceFill(['phone' => '7195558800'])->save();

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => Http::response([
            'ok' => true,
            'conversation' => [
                'public_id' => 'pc_jack',
                'contact_address' => '+17195558800',
                'core_customer_id' => null,
            ],
            'messages' => [],
        ], 200),
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.communications.platform-conversations.work', [
            'platformConversation' => 'pc_jack',
        ]), [
            'action' => 'follow_up',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect();

    $phone = PhoneNumber::normalize((string) $customer->phone);
    $conversation = Conversation::query()
        ->where('contact_surface', ConversationContactSurface::Phone)
        ->where('contact_address', $phone)
        ->sole();

    expect($conversation->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($conversation->owned_by_user_id)->toBe($advisor->id)
        ->and($conversation->follow_up_due_at)->not->toBeNull()
        ->and(app(ConversationWork::class)->lane($conversation))->toBe('waiting');
});
