<?php

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('repair order relationship timeline is strict linked oldest to newest', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Brakes',
        'phone' => '7195558801',
        'email' => 'sarah.brakes@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'CR-V',
    ]);

    $roA = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brakes',
        'created_at' => Carbon::parse('2026-07-01 08:00:00'),
    ]);

    $roB = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Oil change',
        'created_at' => Carbon::parse('2026-07-01 09:00:00'),
    ]);

    $conversationA = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195558801',
        'status' => ConversationStatus::Open,
    ]);
    $conversationB = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195558802',
        'status' => ConversationStatus::Open,
    ]);

    app(ConversationLinker::class)->link($conversationA, $roA);
    app(ConversationLinker::class)->link($conversationB, $roB);

    $participantA = ConversationParticipant::query()->create([
        'conversation_id' => $conversationA->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);
    $participantB = ConversationParticipant::query()->create([
        'conversation_id' => $conversationB->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversationA->id,
        'conversation_participant_id' => $participantA->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'My brakes are squeaking.',
        'occurred_at' => Carbon::parse('2026-07-10 09:00:00'),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversationB->id,
        'conversation_participant_id' => $participantB->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Oil change tomorrow?',
        'occurred_at' => Carbon::parse('2026-07-10 09:30:00'),
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAroreltimeline01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558801',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558801',
        'status' => CallSessionStatus::Completed,
        'repair_order_id' => $roA->id,
        'customer_id' => $customer->id,
        'started_at' => Carbon::parse('2026-07-10 10:00:00'),
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAroreltimeline02',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558801',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558801',
        'status' => CallSessionStatus::Completed,
        'repair_order_id' => $roB->id,
        'customer_id' => $customer->id,
        'started_at' => Carbon::parse('2026-07-10 10:15:00'),
    ]);

    // Unlinked customer call — must not appear on RO A.
    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAroreltimeline03',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558801',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558801',
        'status' => CallSessionStatus::Completed,
        'repair_order_id' => null,
        'customer_id' => $customer->id,
        'started_at' => Carbon::parse('2026-07-10 11:00:00'),
    ]);

    app(CommunicationEventRecorder::class)->record(
        $roA,
        OperationalCommunicationType::EstimateViewed,
        OperationalCommunicationChannel::Website,
        OperationalCommunicationDirection::Inbound,
        'Customer opened the estimate portal link.',
        occurredAt: Carbon::parse('2026-07-10 12:00:00'),
    );

    app(CommunicationEventRecorder::class)->record(
        $roA,
        OperationalCommunicationType::SmsDelivered,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        'SMS delivered to customer.',
        occurredAt: Carbon::parse('2026-07-10 12:05:00'),
    );

    app(CommunicationEventRecorder::class)->record(
        $roB,
        OperationalCommunicationType::EstimateViewed,
        OperationalCommunicationChannel::Website,
        OperationalCommunicationDirection::Inbound,
        'Sibling estimate viewed.',
        occurredAt: Carbon::parse('2026-07-10 12:10:00'),
    );

    $entries = app(UnifiedOperationalTimeline::class)->forRepairOrderRelationship($roA, 50);

    expect($entries->pluck('body')->all())->toContain('My brakes are squeaking.')
        ->and($entries->pluck('body')->all())->not->toContain('Oil change tomorrow?')
        ->and($entries->pluck('body')->all())->not->toContain('Sibling estimate viewed.')
        ->and($entries->contains(fn ($entry): bool => $entry->kind === OperationalEventKind::Call))->toBeTrue()
        ->and($entries->filter(fn ($entry): bool => $entry->kind === OperationalEventKind::Call))->toHaveCount(1)
        ->and($entries->filter(fn ($entry): bool => $entry->kind === OperationalEventKind::EstimateViewed))->toHaveCount(1)
        ->and($entries->contains(fn ($entry): bool => str_contains((string) $entry->headline, 'SMS delivered')))->toBeFalse()
        ->and($entries->first()->occurredAt->lessThanOrEqualTo($entries->last()->occurredAt))->toBeTrue();

    // Oldest → newest: SMS before call before estimate viewed.
    $kinds = $entries->pluck('kind')->map(fn ($kind) => $kind->value)->all();
    expect(array_search(OperationalEventKind::Sms->value, $kinds, true))
        ->toBeLessThan(array_search(OperationalEventKind::Call->value, $kinds, true))
        ->and(array_search(OperationalEventKind::Call->value, $kinds, true))
        ->toBeLessThan(array_search(OperationalEventKind::EstimateViewed->value, $kinds, true));
});

test('ro comms tab uses event-bubble and conversation message history for reply label', function (): void {
    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(\App\Ark\Operations\Settings\ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => false,
        ]),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $roA = repairOrderForCommunication(RepairOrderStatus::Estimate, 'Reply Label Customer');
    $roA->customer->update(['phone' => '7195558808']);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAroreltimeline04',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558808',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558808',
        'status' => CallSessionStatus::Completed,
        'repair_order_id' => $roA->id,
        'customer_id' => $roA->customer_id,
        'started_at' => now(),
        'worked_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $roA, 'tab' => 'comms']))
        ->assertOk()
        ->assertSee('ops-comms-workspace__bubble', false)
        ->assertSee('data-timeline-refresh="comms-tab"', false)
        ->assertDontSee('Estimate activity', false)
        ->assertDontSee('Communications timeline', false)
        ->assertSee('Text Customer', false);
});
