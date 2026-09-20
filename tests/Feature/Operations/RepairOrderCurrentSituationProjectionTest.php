<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\IncomingCallContextPresenter;
use App\Ark\Orientation\Orientation;
use App\Ark\Orientation\OrientationDensity;
use App\Ark\Orientation\RepairOrderOrientationEngine;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('orientation engine orients advisor when customer viewed estimate and asked for time', function () {
    $repairOrder = repairOrderForOrientation(
        status: RepairOrderStatus::WaitingApproval,
        concernSummary: 'Grinding brakes at low speed',
    );

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brake failure',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Email,
        'direction' => 'inbound',
        'summary' => 'Customer viewed estimate',
        'occurred_at' => now()->subHours(8),
    ]);

    $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::CustomerReply,
        'channel' => OperationalCommunicationChannel::Phone,
        'direction' => 'inbound',
        'summary' => 'Customer requested time to decide until payday',
        'occurred_at' => now()->subHours(7),
    ]);

    $orientation = app(RepairOrderOrientationEngine::class)->derive($repairOrder->fresh());
    $full = $orientation->present(OrientationDensity::Full);

    expect($full['situation'])->toBe('Waiting on Customer Approval')
        ->and($full['owner_signal'])->toBe('customer')
        ->and($full['progress_stopped_because'])->toBe('Customer requested time to decide until payday.')
        ->and($full['suggested_follow_up_lines'][0])->toBe('No action right now.')
        ->and(collect($full['suggested_follow_up_lines'])->join(' '))->toContain('follow up tomorrow morning')
        ->and($full['confidence_items'])->toContain('Estimate viewed', 'Inspection findings recorded');
});

test('orientation compact density exposes interrupt payload without duplicating derivation', function () {
    $repairOrder = repairOrderForOrientation(status: RepairOrderStatus::WaitingApproval);

    $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::EstimateSent,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => 'outbound',
        'summary' => 'Sent estimate link',
        'occurred_at' => now()->subDay(),
    ]);

    $compact = Orientation::forRepairOrder($repairOrder->fresh(), OrientationDensity::Compact);

    expect($compact)->toHaveKeys(['situation', 'progress_stopped_because', 'owner', 'owner_signal', 'next_action'])
        ->and($compact)->not->toHaveKey('suggested_follow_up_lines')
        ->and($compact['next_action'])->toContain('Call to confirm');
});

test('orientation surfaces warranty stall through the platform service', function () {
    $repairOrder = repairOrderForOrientation(
        status: RepairOrderStatus::WaitingApproval,
        warranty: true,
    );

    $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::ApprovalFollowUp,
        'channel' => OperationalCommunicationChannel::Phone,
        'direction' => 'outbound',
        'summary' => 'Left message with warranty desk',
        'occurred_at' => now()->subDays(3),
    ]);

    $orientation = Orientation::forRepairOrder($repairOrder->fresh(), OrientationDensity::Interrupt);

    expect($orientation['situation'])->toBe('Waiting on Warranty')
        ->and($orientation['owner_signal'])->toBe('warranty')
        ->and($orientation['next_action'])->toContain('warranty administrator');
});

test('repair order surfaces render sticky current situation from orientation service', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForOrientation(
        status: RepairOrderStatus::Estimate,
        concernSummary: 'Check engine light is on',
    );

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('id="ro-orientation-header"', false)
        ->assertSee('ops-ro-orientation-header__owner-signal--advisor', false)
        ->assertSee('Building Estimate', false)
        ->assertDontSee('Waiting on Diagnosis', false);
});

test('incoming call context includes interrupt orientation for primary open repair order', function () {
    $repairOrder = repairOrderForOrientation(status: RepairOrderStatus::WaitingApproval);

    $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::CustomerReply,
        'channel' => OperationalCommunicationChannel::Phone,
        'direction' => 'inbound',
        'summary' => 'Customer requested time to decide until payday',
        'occurred_at' => now()->subHours(2),
    ]);

    $customer = $repairOrder->customer;
    $session = \App\Ark\Operations\Telephony\CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CA-orientation-test',
        'direction' => \App\Ark\Operations\Telephony\CallSessionDirection::Inbound,
        'from_number' => '+17195550101',
        'to_number' => '+17195559999',
        'normalized_from' => '7195550101',
        'status' => \App\Ark\Operations\Telephony\CallSessionStatus::Ringing,
        'customer_id' => $customer->id,
        'started_at' => now(),
    ]);

    $context = app(\App\Ark\Operations\Conversations\CustomerCallContextResolver::class)
        ->resolveForCustomer($customer);

    $payload = app(IncomingCallContextPresenter::class)->present($session, $context);

    expect($payload['orientation']['situation'] ?? null)->toBe('Waiting on Customer Approval')
        ->and($payload['orientation']['progress_stopped_because'] ?? null)->toContain('payday')
        ->and($payload['open_repair_orders'][0]['orientation']['situation'] ?? null)->toBe('Waiting on Customer Approval');
});

function repairOrderForOrientation(
    RepairOrderStatus $status,
    string $concernSummary = 'Orientation fixture concern.',
    bool $warranty = false,
): RepairOrder {
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Current',
        'last_name' => 'Situation',
        'phone' => '7195550101',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    return RepairOrder::query()->create([
        'repair_order_id' => random_int(90000, 99999),
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => $concernSummary,
        'warranty' => $warranty,
    ]);
}
