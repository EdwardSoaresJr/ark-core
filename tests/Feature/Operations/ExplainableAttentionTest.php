<?php

use App\Ark\Operations\Attention\AttentionPressureResolver;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Observations\OperationalObservation;
use App\Ark\Operations\Observations\OperationalObservationResolver;
use App\Ark\Operations\Observations\OperationalObservationSeverity;
use App\Ark\Operations\Observations\OperationalObservationSourceEvent;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\Attention\ConversationAttentionCandidateBuilder;
use App\Ark\Operations\Timeline\Mappers\CommunicationEventMapper;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use Illuminate\Support\Carbon;

test('aggregate observations include source events for each contributing timeline event', function (): void {
    $repairOrder = repairOrderForCommunication(\App\Ark\Operations\RepairOrders\RepairOrderStatus::InProgress, 'Source Events');
    $mapper = app(CommunicationEventMapper::class);
    $resolver = app(OperationalObservationResolver::class);

    $events = collect(['09:12', '09:14', '09:27'])->map(function (string $time) use ($repairOrder, $mapper): OperationalEventEntry {
        $event = CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Portal view',
            'occurred_at' => Carbon::parse("2026-06-10 {$time}:00"),
        ]);

        return $mapper->map($event);
    })->all();

    $multiple = collect($resolver->resolve($events, ['repair_order_id' => $repairOrder->id]))
        ->first(fn ($observation) => $observation->type === OperationalObservationType::EstimateViewedMultipleTimes);

    expect($multiple)->not->toBeNull()
        ->and($multiple->sourceEvents)->toHaveCount(3)
        ->and($multiple->sourceEvents[0])->toBeInstanceOf(OperationalObservationSourceEvent::class)
        ->and($multiple->sourceEvents[0]->headline)->toBe('Estimate viewed');
});

test('attention pressure resolver returns explainable reasons not a magic score', function (): void {
    $resolver = app(AttentionPressureResolver::class);

    $observations = [
        new OperationalObservation(
            type: OperationalObservationType::CustomerSentMultipleMessages,
            severity: OperationalObservationSeverity::High,
            occurredAt: now(),
            customerId: 1,
            vehicleId: null,
            repairOrderId: null,
            conversationId: 5,
            headline: 'Customer sent multiple messages',
            description: '3 messages',
            sourceEvents: [],
            metadata: ['message_count' => 3],
        ),
        new OperationalObservation(
            type: OperationalObservationType::ConversationUnassigned,
            severity: OperationalObservationSeverity::Medium,
            occurredAt: now(),
            customerId: 1,
            vehicleId: null,
            repairOrderId: null,
            conversationId: 5,
            headline: 'Conversation unassigned',
            description: 'No owner',
            sourceEvents: [],
            metadata: [],
        ),
        new OperationalObservation(
            type: OperationalObservationType::CustomerWaitingResponse,
            severity: OperationalObservationSeverity::High,
            occurredAt: now()->subHours(18),
            customerId: 1,
            vehicleId: null,
            repairOrderId: null,
            conversationId: 5,
            headline: 'Customer waiting response',
            description: 'Waiting',
            sourceEvents: [],
            metadata: ['hours_waiting' => 18],
        ),
    ];

    $candidate = $resolver->candidate(
        entityKey: 'conversation:5',
        headline: 'Sarah Johnson',
        observations: $observations,
        conversationId: 5,
        customerId: 1,
    );

    expect($candidate)->not->toBeNull()
        ->and($candidate->reasons)->toContain('3 customer messages')
        ->and($candidate->reasons)->toContain('Conversation unassigned')
        ->and($candidate->reasons)->toContain('Customer waiting 18 hours')
        ->and($candidate->pressureScore)->toBeGreaterThan(0)
        ->and(count($candidate->reasons))->toBe(count($observations));
});

test('conversation attention candidate builder merges event and authority observations', function (): void {
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195552020',
        'status' => ConversationStatus::Open,
        'owned_by_user_id' => null,
    ]);

    $candidate = app(ConversationAttentionCandidateBuilder::class)->forConversation($conversation);

    expect($candidate)->not->toBeNull()
        ->and($candidate->reasons)->toContain('Conversation unassigned');
});
