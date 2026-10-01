<?php

use App\Ark\Operations\Communications\CommunicationsRelationshipContextResolver;
use App\Ark\Operations\Communications\CommunicationsVisitSource;
use App\Ark\Operations\Communications\CommunicationsWorkspaceProjection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

function contextIntegrityCustomer(string $phone, string $first = 'Ann', string $last = 'Cox'): Customer
{
    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'email' => strtolower($first).'.'.strtolower($last).'.'.$phone.'@example.test',
    ]);
}

function contextIntegrityVehicle(Customer $customer, string $make = 'Nissan', string $model = 'Sentra'): Vehicle
{
    return Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => $make,
        'model' => $model,
    ]);
}

function contextIntegrityRepairOrder(
    Customer $customer,
    Vehicle $vehicle,
    int $shopNumber,
    RepairOrderStatus $status,
    ?Carbon $updatedAt = null,
): RepairOrder {
    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => $shopNumber,
        'status' => $status,
        'concern_summary' => 'RO '.$shopNumber,
        'updated_at' => $updatedAt ?? now(),
        'created_at' => $updatedAt ?? now(),
    ]);
}

function contextIntegrityConversation(string $phone, ConversationWaitingOn $waitingOn = ConversationWaitingOn::Shop): Conversation
{
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => $phone,
        'status' => ConversationStatus::Open,
        'waiting_on' => $waitingOn,
    ]);

    return $conversation;
}

function contextIntegrityAttachCustomer(Conversation $conversation, Customer $customer): void
{
    ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);
}

test('explicit conversation RO link is the current visit even when a newer open RO exists', function (): void {
    $customer = contextIntegrityCustomer('7195551742');
    $vehicle = contextIntegrityVehicle($customer);
    $historical = contextIntegrityRepairOrder($customer, $vehicle, 1563, RepairOrderStatus::Closed, now()->subDays(90));
    $current = contextIntegrityRepairOrder($customer, $vehicle, 1742, RepairOrderStatus::Estimate, now());
    $conversation = contextIntegrityConversation('7195551742');
    contextIntegrityAttachCustomer($conversation, $customer);
    app(ConversationLinker::class)->link($conversation, $current);

    $context = app(CommunicationsRelationshipContextResolver::class)->forConversation($conversation);

    expect($context['customer']['matched'])->toBeTrue()
        ->and($context['customer']['status'])->toBe('Customer')
        ->and($context['current_visit']['source'])->toBe(CommunicationsVisitSource::Linked->value)
        ->and($context['current_visit']['ro_number'])->toBe(1742)
        ->and($context['current_visit']['repair_order']->id)->toBe($current->id)
        ->and($historical->repair_order_id)->toBe(1563);
});

test('closed linked visit is not replaced by the latest open RO', function (): void {
    $customer = contextIntegrityCustomer('7195551563');
    $vehicle = contextIntegrityVehicle($customer);
    $linkedClosed = contextIntegrityRepairOrder($customer, $vehicle, 1563, RepairOrderStatus::Closed, now()->subDays(40));
    contextIntegrityRepairOrder($customer, $vehicle, 1742, RepairOrderStatus::Estimate, now());
    $conversation = contextIntegrityConversation('7195551563');
    contextIntegrityAttachCustomer($conversation, $customer);
    app(ConversationLinker::class)->link($conversation, $linkedClosed);

    $context = app(CommunicationsRelationshipContextResolver::class)->forConversation($conversation);

    expect($context['current_visit']['source'])->toBe(CommunicationsVisitSource::None->value)
        ->and($context['current_visit']['repair_order'])->toBeNull()
        ->and($context['current_visit']['label'])->toBe('No current visit')
        ->and($context['current_visit']['source_label'])->toBe('Linked visit is not open')
        ->and($context['current_visit']['linked_visit']['ro_number'] ?? null)->toBe(1563);
});

test('one unlinked open RO is inferred current visit and labeled as inferred', function (): void {
    $customer = contextIntegrityCustomer('7195551001');
    $vehicle = contextIntegrityVehicle($customer);
    $open = contextIntegrityRepairOrder($customer, $vehicle, 1742, RepairOrderStatus::Estimate, now());
    $conversation = contextIntegrityConversation('7195551001');
    contextIntegrityAttachCustomer($conversation, $customer);

    $context = app(CommunicationsRelationshipContextResolver::class)->forConversation($conversation);

    expect($context['current_visit']['source'])->toBe(CommunicationsVisitSource::Inferred->value)
        ->and($context['current_visit']['ro_number'])->toBe(1742)
        ->and($context['current_visit']['label'])->toBe('Inferred current visit')
        ->and($context['current_visit']['repair_order']->id)->toBe($open->id);
});

test('multiple open visits do not pick a current visit', function (): void {
    $customer = contextIntegrityCustomer('7195551002');
    $vehicle = contextIntegrityVehicle($customer);
    contextIntegrityRepairOrder($customer, $vehicle, 1742, RepairOrderStatus::Estimate, now());
    contextIntegrityRepairOrder($customer, $vehicle, 1743, RepairOrderStatus::WaitingApproval, now()->subHour());
    $conversation = contextIntegrityConversation('7195551002');
    contextIntegrityAttachCustomer($conversation, $customer);

    $context = app(CommunicationsRelationshipContextResolver::class)->forConversation($conversation);

    expect($context['current_visit']['source'])->toBe(CommunicationsVisitSource::Multiple->value)
        ->and($context['current_visit']['repair_order'])->toBeNull()
        ->and($context['current_visit']['label'])->toBe('Multiple open visits')
        ->and($context['current_visit']['open_visits'])->toHaveCount(2);
});

test('ann cox historical call on RO 1563 is not attributed to current RO 1742', function (): void {
    $customer = contextIntegrityCustomer('7195551740');
    $vehicle = contextIntegrityVehicle($customer);
    $historical = contextIntegrityRepairOrder($customer, $vehicle, 1563, RepairOrderStatus::Closed, now()->subDays(90));
    $current = contextIntegrityRepairOrder($customer, $vehicle, 1742, RepairOrderStatus::Estimate, now());
    $conversation = contextIntegrityConversation('7195551740');
    contextIntegrityAttachCustomer($conversation, $customer);
    app(ConversationLinker::class)->link($conversation, $current);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAanncox1563',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551740',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551740',
        'status' => CallSessionStatus::Completed,
        'repair_order_id' => $historical->id,
        'customer_id' => $customer->id,
        'started_at' => now()->subDays(80),
    ]);

    $identity = app(\App\Ark\Operations\Communications\CommunicationsWorkspaceIdentityProjection::class)
        ->forConversation($conversation);
    $events = app(UnifiedOperationalTimeline::class)->forConversationRelationship($conversation, 50);

    $callEvent = $events->first(fn ($event): bool => ($event->metadata['visit_ro_number'] ?? null) === 1563
        || str_contains((string) $event->body, 'RO #1563'));

    expect($identity['ro_number'])->toBe(1742)
        ->and($identity['visit_source'])->toBe(CommunicationsVisitSource::Linked->value)
        ->and($callEvent)->not->toBeNull()
        ->and($callEvent->metadata['visit_label'] ?? null)->toBe('RO #1563')
        ->and($callEvent->metadata['visit_ro_number'] ?? null)->toBe(1563)
        ->and($callEvent->metadata['visit_ro_number'] ?? null)->not->toBe(1742);
});

test('unmatched phone stays unmatched and does not fuzzy-match another customer', function (): void {
    contextIntegrityCustomer('7195551234', 'Maya', 'Exact');
    $conversation = contextIntegrityConversation('5551234');

    $context = app(CommunicationsRelationshipContextResolver::class)->forConversation($conversation);
    $callContext = app(CustomerCallContextResolver::class)->resolve('5551234');

    expect($context['customer']['matched'])->toBeFalse()
        ->and($context['customer']['status'])->toBe('Unmatched')
        ->and($context['current_visit']['label'])->toBe('No current visit')
        ->and($callContext->customer)->toBeNull();
});

test('customer-turn conversation does not invent why-this-needs-you from waiting copy', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = contextIntegrityCustomer('7195552001', 'Dana', 'Wait');
    $conversation = contextIntegrityConversation('7195552001', ConversationWaitingOn::Customer);
    contextIntegrityAttachCustomer($conversation, $customer);

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        $conversation->id,
        null,
        null,
        null,
        null,
        'waiting',
    );

    $reasons = $workspace['context']['attention']['reasons'] ?? [];

    expect($workspace['thread']['identity']['turn_label'] ?? null)->toBe('Waiting on customer')
        ->and($workspace['context']['turn']['waiting_on'] ?? null)->toBe('customer')
        ->and($workspace['context']['nudge']['key'] ?? null)->not->toBe('conversation.waiting_response')
        ->and($workspace['context']['analysis_insight'] ?? null)->toBeNull()
        ->and(collect($reasons)->contains(fn (string $reason): bool => str_starts_with($reason, 'Customer waiting')))->toBeFalse();
});

test('shop-turn conversation uses waiting_on for turn', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = contextIntegrityCustomer('7195552002', 'Sam', 'Shop');
    $conversation = contextIntegrityConversation('7195552002', ConversationWaitingOn::Shop);
    contextIntegrityAttachCustomer($conversation, $customer);

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        $conversation->id,
        null,
        null,
        null,
        null,
        'needs',
    );

    $reasons = $workspace['context']['attention']['reasons'] ?? [];

    expect($workspace['thread']['identity']['turn_label'] ?? null)->toBe('Needs shop')
        ->and($workspace['context']['turn']['is_shop_turn'] ?? false)->toBeTrue()
        ->and(collect($reasons)->contains(fn (string $reason): bool => str_starts_with($reason, 'Customer waiting')))->toBeFalse();
});

test('unhandled inbound call is a shop interrupt without a manufactured visit', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAinterrupt2002',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195552099',
        'to_number' => '+17195559999',
        'normalized_from' => '7195552099',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinutes(4),
    ]);

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        null,
        null,
        $session->id,
        null,
        null,
        'needs',
    );

    expect($workspace['selected']['kind'] ?? null)->toBe('call')
        ->and($workspace['thread']['identity']['turn_label'] ?? null)->toBe('Needs shop')
        ->and($workspace['thread']['identity']['link_status'] ?? null)->toBe('Unmatched')
        ->and($workspace['thread']['identity']['visit_label'] ?? null)->toBe('No current visit')
        ->and($workspace['context']['sections']['who']['Match'] ?? null)->toBe('Unmatched')
        ->and($workspace['context']['sections']['current_visit']['Visit'] ?? null)->toBe('No current visit');
});

test('resolved list exposes shown versus total instead of implying every counted row is displayed', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();

    for ($i = 0; $i < 41; $i++) {
        Conversation::query()->create([
            'contact_surface' => ConversationContactSurface::Phone,
            'contact_address' => '7195553'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'status' => ConversationStatus::Resolved,
            'waiting_on' => ConversationWaitingOn::Customer,
        ]);
    }

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        null,
        null,
        null,
        null,
        null,
        'resolved',
    );

    expect($workspace['list_shown'])->toBe(40)
        ->and($workspace['list_total'])->toBe(41)
        ->and($workspace['list_truncated'])->toBeTrue()
        ->and($workspace['filter_counts']['resolved'])->toBe(41)
        ->and(count($workspace['list_items']))->toBe(40);
});
