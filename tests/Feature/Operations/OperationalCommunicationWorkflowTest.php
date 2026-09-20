<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Database\Seeders\ArkAuthorizationSeeder;

test('advisor logs operational communication without CRM automation', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->post(route('operations.repair-orders.communications.store', $repairOrder), [
        'communication_type' => OperationalCommunicationType::EstimateSent->value,
        'channel' => OperationalCommunicationChannel::Sms->value,
        'direction' => OperationalCommunicationDirection::Outbound->value,
        'summary' => 'Sent estimate link and asked customer to review brakes today.',
    ])->assertRedirect();

    $event = CommunicationEvent::query()->sole();
    $operationalEvent = OperationalEvent::query()
        ->where('event_name', OperationalEventName::OperationalCommunicationLogged->value)
        ->sole();

    expect($event->repair_order_id)->toBe($repairOrder->id)
        ->and($event->created_by)->toBe($advisor->id)
        ->and($event->event_type)->toBe(OperationalCommunicationType::EstimateSent)
        ->and($event->summary)->toBe('Sent estimate link and asked customer to review brakes today.')
        ->and($repairOrder->fresh()->communicationPostureLabel())->toBe('Estimate sent · SMS')
        ->and($repairOrder->fresh()->communicationNextAction())->toBe('Wait for customer response')
        ->and($operationalEvent->payload_json)->toMatchArray([
            'communication_event_id' => $event->id,
            'communication_type' => OperationalCommunicationType::EstimateSent->value,
            'channel' => OperationalCommunicationChannel::Sms->value,
            'direction' => OperationalCommunicationDirection::Outbound->value,
        ])
        ->and($operationalEvent->payload_json)->not->toHaveKey('summary');
});

test('viewed estimate and customer reply drive advisor next action in queue and review', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval, customerName: 'Viewed Customer');

    $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Email,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer viewed estimate but has not approved.',
        'occurred_at' => now(),
    ]);

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Waiting Approval')
        ->assertSee('Viewed Customer');

    expect($repairOrder->fresh()->communicationPostureLabel())->toBe('Estimate viewed · Email');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Follow up viewed estimate');

    $this->post(route('operations.repair-orders.communications.store', $repairOrder), [
        'communication_type' => OperationalCommunicationType::CustomerReply->value,
        'channel' => OperationalCommunicationChannel::Phone->value,
        'direction' => OperationalCommunicationDirection::Inbound->value,
        'summary' => 'Customer asked whether rear brakes can wait.',
    ])->assertRedirect();

    expect($repairOrder->fresh()->communicationNextAction())->toBe('Review customer reply');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Review customer reply');
});

test('ready pickup communication tracks customer notification posture', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::ReadyPickup, customerName: 'Pickup Customer');
    $repairOrder->forceFill(['payment_status' => RepairOrderPaymentStatus::Paid])->save();

    expect($repairOrder->fresh()->communicationNextAction())->toBe('Notify customer pickup ready');

    $this->post(route('operations.repair-orders.communications.store', $repairOrder), [
        'communication_type' => OperationalCommunicationType::PickupNotified->value,
        'channel' => OperationalCommunicationChannel::Phone->value,
        'direction' => OperationalCommunicationDirection::Outbound->value,
        'summary' => 'Called customer. They will arrive after work.',
    ])->assertRedirect();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Pickup notified · Phone')
        ->assertSee('Await customer arrival');
});

test('communication events snapshot with estimate documents', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);
    $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::ApprovalFollowUp,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'summary' => 'Followed up after lunch; customer will decide tonight.',
        'occurred_at' => now(),
    ]);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $document = EstimateDocument::query()->sole();

    expect($document->snapshot_json['staff']['communication']['posture'])->toBe('Approval follow-up · SMS')
        ->and($document->snapshot_json['staff']['communication']['next_action'])->toBe('Waiting customer response')
        ->and($document->snapshot_json['staff']['communications'][0]['summary'])->toBe('Followed up after lunch; customer will decide tonight.');
});

test('communication events are append only', function () {
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $event = $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::AdvisorNote,
        'channel' => OperationalCommunicationChannel::Internal,
        'direction' => OperationalCommunicationDirection::Internal,
        'summary' => 'Advisor note only.',
        'occurred_at' => now(),
    ]);

    expect(fn () => $event->update(['summary' => 'Changed']))
        ->toThrow(LogicException::class, 'append-only');

    expect(fn () => $event->delete())
        ->toThrow(LogicException::class, 'append-only');
});
