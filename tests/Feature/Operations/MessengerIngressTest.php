<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\Events\ConversationMessageReceived;
use App\Ark\Operations\Messaging\Messenger\MessengerHealth;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('broadcasting.default', 'null');
    Storage::fake('local');
    configureMessengerShop();
});

test('meta messenger webhook verify returns challenge', function () {
    $this->get(route('webhooks.communications.meta.messenger', [
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'verify-token',
        'hub_challenge' => 'challenge-123',
    ]))
        ->assertOk()
        ->assertSee('challenge-123', false);
});

test('meta messenger webhook rejects invalid verify token', function () {
    $this->get(route('webhooks.communications.meta.messenger', [
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'wrong-token',
        'hub_challenge' => 'challenge-123',
    ]))
        ->assertForbidden();
});

test('meta messenger webhook rejects invalid platform signature', function () {
    $body = json_encode(messengerPayload(
        psid: 'psid-sig-001',
        messageId: 'mid-sig-001',
        body: 'Hello',
    ), JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('webhooks.communications.meta.messenger'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=deadbeef',
        ],
        $body,
    )->assertUnauthorized();
});

test('meta messenger webhook creates conversation message for known customer', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([ConversationMessageReceived::class]);

    $customer = messengerCustomer('Jane', 'Driver', 'psid-known-001');

    $response = $this->postJson(route('webhooks.communications.meta.messenger'), messengerPayload(
        psid: 'psid-known-001',
        messageId: 'mid-inbound-001',
        body: 'Do you have availability tomorrow?',
    ));

    $response->assertOk()
        ->assertSee('EVENT_RECEIVED', false);

    $conversation = Conversation::query()->sole();

    expect($conversation->contact_surface)->toBe(ConversationContactSurface::Messenger)
        ->and($conversation->contact_address)->toBe('psid-known-001');

    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Messenger)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Inbound)
        ->and($message->body)->toBe('Do you have availability tomorrow?')
        ->and($message->metadata['provider_message_id'])->toBe('mid-inbound-001');

    expect(ConversationLink::query()
        ->where('linkable_type', Customer::class)
        ->where('linkable_id', $customer->id)
        ->exists())->toBeTrue();

    expect(cache()->has(MessengerHealth::webhookCacheKey('page-123')))->toBeTrue();

    Event::assertDispatched(ConversationMessageReceived::class, function (ConversationMessageReceived $event) use ($customer): bool {
        return $event->payload['customer_id'] === $customer->id
            && $event->payload['message']['body'] === 'Do you have availability tomorrow?';
    });
});

test('meta messenger webhook is idempotent for duplicate provider message id', function () {
    $payload = messengerPayload(
        psid: 'psid-dup-001',
        messageId: 'mid-dup-001',
        body: 'First message',
    );

    $this->postJson(route('webhooks.communications.meta.messenger'), $payload)->assertOk();
    $this->postJson(route('webhooks.communications.meta.messenger'), $payload)->assertOk();

    expect(ConversationMessage::query()->count())->toBe(1);
});

test('staff can link unmatched messenger conversation to customer via json', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $this->postJson(route('webhooks.communications.meta.messenger'), messengerPayload(
        psid: 'psid-unmatched-json',
        messageId: 'mid-unmatched-json',
        body: 'Need brakes checked.',
    ))->assertOk();

    $conversation = Conversation::query()->sole();
    $customer = messengerCustomer('Json', 'Link', null);

    $this->postJson(route('operations.conversations.link-customer', $conversation), [
        'customer_id' => $customer->id,
    ])
        ->assertOk()
        ->assertJsonPath('customer_id', $customer->id);

    $customer->refresh();

    expect($customer->messenger_psid)->toBe('psid-unmatched-json');
});

test('staff can link unmatched messenger conversation to customer', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $this->postJson(route('webhooks.communications.meta.messenger'), messengerPayload(
        psid: 'psid-unmatched-001',
        messageId: 'mid-unmatched-001',
        body: 'Hi, I need an oil change.',
    ))->assertOk();

    $conversation = Conversation::query()->sole();
    $customer = messengerCustomer('New', 'Messenger', null);

    $this->post(route('operations.conversations.link-customer', $conversation), [
        'customer_id' => $customer->id,
    ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $customer->refresh();

    expect($customer->messenger_psid)->toBe('psid-unmatched-001')
        ->and(ConversationLink::query()
            ->where('conversation_id', $conversation->id)
            ->where('linkable_type', Customer::class)
            ->where('linkable_id', $customer->id)
            ->exists())->toBeTrue();
});

test('outbound messenger rejects reply outside twenty four hour window', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = messengerCustomer('Expired', 'Window', 'psid-expired-001');

    $this->postJson(route('webhooks.communications.meta.messenger'), messengerPayload(
        psid: 'psid-expired-001',
        messageId: 'mid-expired-001',
        body: 'Old inbound message',
    ))->assertOk();

    $messageId = ConversationMessage::query()->sole()->id;

    DB::table('conversation_messages')
        ->where('id', $messageId)
        ->update(['occurred_at' => now()->subHours(25)]);

    $this->postJson(route('operations.customers.conversation-messages.store', $customer), [
        'channel' => 'messenger',
        'body' => 'Too late to reply.',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Messenger 24-hour reply window has expired. Select a message tag or wait for the customer to message again.');
});

test('inbound messenger image attachment is stored on conversation message', function () {
    Http::fake([
        'https://lookaside.fbsbx.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    messengerCustomer('Photo', 'Sender', 'psid-image-001');

    $this->postJson(route('webhooks.communications.meta.messenger'), messengerPayload(
        psid: 'psid-image-001',
        messageId: 'mid-image-001',
        body: '',
        attachments: [[
            'type' => 'image',
            'payload' => ['url' => 'https://lookaside.fbsbx.com/messenger-image'],
        ]],
    ))->assertOk();

    $message = ConversationMessage::query()->sole();

    expect($message->body)->toBe('(attachment)')
        ->and($message->attachments)->toHaveCount(1);

    $attachment = ConversationMessageAttachment::query()->sole();

    expect($attachment->content_type)->toBe('image/jpeg')
        ->and($attachment->storage_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($attachment->storage_path))->toBeTrue();
});

test('outbound messenger can use message tag outside twenty four hour window', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'message_id' => 'mid-tagged-001',
        ], 200),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = messengerCustomer('Tagged', 'Reply', 'psid-tagged-001');

    $this->postJson(route('webhooks.communications.meta.messenger'), messengerPayload(
        psid: 'psid-tagged-001',
        messageId: 'mid-tagged-inbound',
        body: 'Any update on my car?',
    ))->assertOk();

    $messageId = ConversationMessage::query()->sole()->id;

    DB::table('conversation_messages')
        ->where('id', $messageId)
        ->update(['occurred_at' => now()->subHours(25)]);

    $this->postJson(route('operations.customers.conversation-messages.store', $customer), [
        'channel' => 'messenger',
        'body' => 'Your vehicle is ready for pickup.',
        'messenger_message_tag' => MetaMessengerMessageTag::ConfirmedEventUpdate->value,
    ])
        ->assertOk()
        ->assertJsonPath('provider_message_id', 'mid-tagged-001');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->url() === 'https://graph.facebook.com/v23.0/me/messages'
            && ($body['messaging_type'] ?? null) === 'MESSAGE_TAG'
            && ($body['tag'] ?? null) === MetaMessengerMessageTag::ConfirmedEventUpdate->value;
    });
});

test('messenger delivery receipt records communication event on linked repair order', function () {
    $customer = messengerCustomer('Delivery', 'Target', 'psid-delivery-001');
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 8801,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Delivery receipt test',
    ]);
    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $this->postJson(route('webhooks.communications.meta.messenger'), messengerPayload(
        psid: 'psid-delivery-001',
        messageId: 'mid-delivery-inbound',
        body: 'Any update?',
    ))->assertOk();

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['message_id' => 'mid-delivery-outbound'], 200),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'channel' => 'messenger',
            'body' => 'We are working on it now.',
            'repair_order_id' => $repairOrder->repair_order_id,
        ])
        ->assertOk();

    $this->postJson(route('webhooks.communications.meta.messenger'), messengerReceiptPayload(
        psid: 'psid-delivery-001',
        kind: 'delivery',
        messageIds: ['mid-delivery-outbound'],
    ))->assertOk();

    $event = CommunicationEvent::query()->sole();

    expect($event->repair_order_id)->toBe($repairOrder->id)
        ->and($event->event_type)->toBe(OperationalCommunicationType::MessageDelivered)
        ->and($event->channel)->toBe(OperationalCommunicationChannel::Messenger);
});

test('staff can send outbound messenger attachment for linked customer', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['message_id' => 'mid-attach-outbound'], 200),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = messengerCustomer('Attach', 'Sender', 'psid-attach-001');

    $this->postJson(route('webhooks.communications.meta.messenger'), messengerPayload(
        psid: 'psid-attach-001',
        messageId: 'mid-attach-inbound',
        body: 'Can you see this photo?',
    ))->assertOk();

    $file = UploadedFile::fake()->image('leak.jpg');

    $this->postJson(route('operations.customers.conversation-messages.store', $customer), [
        'channel' => 'messenger',
        'body' => '',
        'attachment' => $file,
    ])
        ->assertOk()
        ->assertJsonPath('provider_message_id', 'mid-attach-outbound');

    Http::assertSent(function ($request): bool {
        $message = $request->data()['message'] ?? [];

        return isset($message['attachment']['type'])
            && ($message['attachment']['type'] ?? null) === 'image';
    });

    $message = ConversationMessage::query()
        ->where('direction', OperationalCommunicationDirection::Outbound)
        ->sole();

    expect($message->body)->toBe('(attachment)')
        ->and($message->attachments)->toHaveCount(1);
});

test('staff can send outbound messenger message for linked customer', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'message_id' => 'mid-outbound-001',
        ], 200),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = messengerCustomer('Reply', 'Target', 'psid-outbound-001');

    $this->postJson(route('webhooks.communications.meta.messenger'), messengerPayload(
        psid: 'psid-outbound-001',
        messageId: 'mid-outbound-inbound-001',
        body: 'Can I come in today?',
    ))->assertOk();

    $response = $this->postJson(route('operations.customers.conversation-messages.store', $customer), [
        'channel' => 'messenger',
        'body' => 'We can fit you in at 2pm.',
    ]);

    $response->assertOk()
        ->assertJsonPath('provider_message_id', 'mid-outbound-001');

    $message = ConversationMessage::query()
        ->where('direction', OperationalCommunicationDirection::Outbound)
        ->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Messenger)
        ->and($message->body)->toBe('We can fit you in at 2pm.')
        ->and($message->metadata['provider_message_id'])->toBe('mid-outbound-001');
});

function configureMessengerShop(): void
{
    config()->set('services.meta_messenger.app_secret', 'app-secret');
    config()->set('services.meta_messenger.verify_token', 'verify-token');
    config()->set('services.meta_messenger.graph_version', 'v23.0');

    ShopSettings::current()->persistTrusted([
        'communications_channels' => [
            'messenger' => [
                'enabled' => true,
                'page_id' => 'page-123',
                'page_name' => 'Demo Auto Repair',
            ],
        ],
        'messenger_page_id' => 'page-123',
        'messenger_page_access_token' => 'page-access-token',
    ]);
}

function messengerCustomer(string $first, string $last, ?string $psid): Customer
{
    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => null,
        'messenger_psid' => $psid,
        'customer_type' => 'Retail',
    ]);
}

/**
 * @return array<string, mixed>
 */
/**
 * @param  list<array<string, mixed>>  $attachments
 * @return array<string, mixed>
 */
/**
 * @param  list<string>  $messageIds
 * @return array<string, mixed>
 */
function messengerReceiptPayload(string $psid, string $kind, array $messageIds = [], ?int $watermark = null): array
{
    $event = [
        'sender' => ['id' => $psid],
        'recipient' => ['id' => 'page-123'],
        'timestamp' => 1710000002,
    ];

    if ($kind === 'delivery') {
        $event['delivery'] = [
            'mids' => $messageIds,
            'watermark' => $watermark ?? 1710000002000,
        ];
    }

    if ($kind === 'read') {
        $event['read'] = [
            'watermark' => $watermark ?? 1710000002000,
        ];
    }

    return [
        'object' => 'page',
        'entry' => [[
            'id' => 'page-123',
            'time' => 1710000000,
            'messaging' => [$event],
        ]],
    ];
}

/**
 * @param  list<array<string, mixed>>  $attachments
 * @return array<string, mixed>
 */
function messengerPayload(string $psid, string $messageId, string $body, array $attachments = []): array
{
    $message = [
        'mid' => $messageId,
        'text' => $body,
    ];

    if ($attachments !== []) {
        $message['attachments'] = $attachments;
    }

    return [
        'object' => 'page',
        'entry' => [
            [
                'id' => 'page-123',
                'time' => 1710000000,
                'messaging' => [
                    [
                        'sender' => ['id' => $psid],
                        'recipient' => ['id' => 'page-123'],
                        'timestamp' => 1710000001,
                        'message' => $message,
                    ],
                ],
            ],
        ],
    ];
}
