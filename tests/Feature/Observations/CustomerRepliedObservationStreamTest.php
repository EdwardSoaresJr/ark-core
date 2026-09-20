<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Messaging\InboundSmsConversationIngress;
use App\Ark\Operations\Observations\OperationalObservationStream;
use App\Ark\Operations\Observations\OperationalObservationStreamEntry;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('inbound sms emits customer_replied into operational observation stream', function (): void {
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195554411',
    ]);

    $result = app(InboundSmsConversationIngress::class)->ingest(new \App\Ark\Operations\Conversations\InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: '7195554411',
        providerMessageId: 'SMobservation001',
        channel: OperationalCommunicationChannel::Sms,
        body: 'Can you call me today?',
        metadata: ['to_number' => '7195559999'],
    ));

    expect($result['created'])->toBeTrue();

    $message = $result['message'];
    expect($message)->toBeInstanceOf(ConversationMessage::class);

    $entry = OperationalObservationStreamEntry::query()->first();
    expect($entry)->not->toBeNull()
        ->and($entry->observation_type)->toBe(OperationalObservationType::CustomerReplied->value)
        ->and($entry->headline)->toBe('Sarah replied')
        ->and($entry->customer_id)->toBe($customer->id)
        ->and($entry->conversation_id)->toBe($message->conversation_id)
        ->and($entry->resolved_at)->toBeNull();

    expect(OperationalEvent::query()
        ->where('event_name', OperationalEventName::ConversationMessageReceived->value)
        ->where('aggregate_id', $message->id)
        ->exists())->toBeTrue();
});

test('shop reply resolves customer_replied observation for conversation', function (): void {
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
    bindFakeOutboundSms();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195554412',
    ]);

    app(InboundSmsConversationIngress::class)->ingest(new \App\Ark\Operations\Conversations\InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: '7195554412',
        providerMessageId: 'SMobservation002',
        channel: OperationalCommunicationChannel::Sms,
        body: 'Are you open Saturday?',
        metadata: ['to_number' => '7195559999'],
    ));

    $conversation = Conversation::query()->firstOrFail();

    expect(OperationalObservationStreamEntry::query()->whereNull('resolved_at')->count())->toBe(1);

    app(\App\Ark\Operations\Messaging\SendOutboundMessageAction::class)->execute(
        customer: $customer,
        actor: $advisor,
        body: 'Yes — drop off before noon.',
        conversation: $conversation,
    );

    expect(OperationalObservationStreamEntry::query()->whereNull('resolved_at')->count())->toBe(0)
        ->and(OperationalObservationStreamEntry::query()->whereNotNull('resolved_at')->count())->toBe(1);
});

test('mobile orientation home surfaces customer_replied from observation stream', function (): void {
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195554413',
    ]);

    app(InboundSmsConversationIngress::class)->ingest(new \App\Ark\Operations\Conversations\InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: '7195554413',
        providerMessageId: 'SMobservation003',
        channel: OperationalCommunicationChannel::Sms,
        body: 'Need an update please',
        metadata: ['to_number' => '7195559999'],
    ));

    $this->withToken($token)
        ->getJson('/api/mobile/orientation')
        ->assertOk()
        ->assertJsonPath('observation_stream_count', 1)
        ->assertJsonPath('continuity.badge', 1)
        ->assertJsonPath('continuity.continuity.count', 1)
        ->assertJsonPath('continuity.continuity.highest_priority', 'customer_replied')
        ->assertJsonPath('continuity_badge.count', 1)
        ->assertJsonPath('items.0.title', 'Sarah replied')
        ->assertJsonPath('items.0.observation', 'customer_replied')
        ->assertJsonPath('items.0.deep_link', 'customer')
        ->assertJsonPath('next_best_action', 'Need an update please');
});

test('continuity badge counts unresolved observations not unread messages', function (): void {
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMreply002', 'status' => 'queued'], 201),
    ]);
    bindFakeOutboundSms();

    Customer::query()->create([
        'first_name' => 'Badge',
        'last_name' => 'Customer',
        'phone' => '7195554415',
    ]);

    app(InboundSmsConversationIngress::class)->ingest(new \App\Ark\Operations\Conversations\InboundConversationPayload(
        contactSurface: ConversationContactSurface::Phone,
        contactKey: '7195554415',
        providerMessageId: 'SMobservation004',
        channel: OperationalCommunicationChannel::Sms,
        body: 'Still waiting on an update',
        metadata: ['to_number' => '7195559999'],
    ));

    $this->withToken($token)
        ->getJson('/api/mobile/continuity')
        ->assertOk()
        ->assertJsonPath('badge', 1)
        ->assertJsonPath('continuity.count', 1)
        ->assertJsonPath('continuity.highest_priority', 'customer_replied')
        ->assertJsonStructure([
            'badge',
            'continuity' => ['count', 'highest_priority', 'oldest_age_seconds', 'updated_at', 'since'],
            'moments',
            'next_best_action',
            'poll_after_seconds',
        ])
        ->assertJsonPath('moments.0.title', 'Badge replied');

    $this->withToken($token)
        ->getJson('/api/mobile/continuity/badge')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('metric', 'operational_continuity');

    $conversation = Conversation::query()->firstOrFail();

    app(\App\Ark\Operations\Messaging\SendOutboundMessageAction::class)->execute(
        customer: Customer::query()->firstOrFail(),
        actor: $advisor,
        body: 'We will call you shortly.',
        conversation: $conversation,
    );

    $this->withToken($token)
        ->getJson('/api/mobile/continuity')
        ->assertOk()
        ->assertJsonPath('badge', 0)
        ->assertJsonPath('continuity.count', 0);

    $this->withToken($token)
        ->getJson('/api/mobile/continuity/badge')
        ->assertOk()
        ->assertJsonPath('count', 0);
});

test('mobile customer workspace opens with customer_replied observation from stream tap', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195554414',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195554414',
        'status' => ConversationStatus::Open,
    ]);

    ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    $message = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => ConversationParticipant::query()->where('conversation_id', $conversation->id)->value('id'),
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Any news on my car?',
        'occurred_at' => now()->subMinutes(2),
    ]);

    app(\App\Ark\Operations\Observations\CustomerRepliedObservationEmitter::class)
        ->emitFromInboundMessage($message, $customer->id);

    $this->withToken($token)
        ->getJson('/api/mobile/customers/'.$customer->id.'?observation=customer_replied')
        ->assertOk()
        ->assertJsonPath('observation.key', 'customer_replied')
        ->assertJsonPath('orientation.next_best_action.key', 'reply')
        ->assertJsonPath('blocks.0.key', 'conversation');
})->skip('Customer workspace API ships in a separate PR');
