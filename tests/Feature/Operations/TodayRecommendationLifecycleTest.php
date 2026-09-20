<?php

use App\Ark\Operations\Briefing\OperationsBriefingProjection;
use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Recommendations\RecommendationResolution;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Today\Lifecycle\EstimateFollowUpLifecycle;
use App\Ark\Operations\Today\Lifecycle\PartsArrivalLifecycle;
use App\Ark\Operations\Today\Lifecycle\TodayCompletionEvent;
use App\Ark\Operations\Today\Lifecycle\TodayLifecycleComposer;
use App\Ark\Operations\Today\Lifecycle\TodayRecommendationKind;
use App\Ark\Operations\Today\Surface\TodayLens;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-06-27 10:00:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('estimate follow-up lifecycle generates call recommendation when estimate viewed repeatedly', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Jason Miller');

    foreach (range(1, 3) as $index) {
        \App\Ark\Operations\Communications\CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Customer opened estimate portal',
            'occurred_at' => now()->subHours(4 - $index),
        ]);
    }

    $context = app(OperationsBriefingProjection::class)->contextFor($advisor);

    $titles = collect(app(TodayLifecycleComposer::class)->actionsBySection($context, TodayLens::Advisor))
        ->flatten(1)
        ->pluck('title');

    expect($titles)->toContain('Call Jason');

    $candidate = collect(app(EstimateFollowUpLifecycle::class)->candidates($context, TodayLens::Advisor))
        ->first(fn ($item) => $item->title === 'Call Jason');

    expect($candidate)->not->toBeNull()
        ->and($candidate->reason)->toContain('Estimate viewed 3×')
        ->and($candidate->expectedOutcome)->toBe('Increase approval likelihood.');

    // Advisor Today is Shop Dashboard — lifecycle recommendations are not rendered there.
    $this->actingAs($advisor)
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Shop Dashboard')
        ->assertDontSee('Call Jason');
});

test('logging follow-up communication retires estimate follow-up and records resolution', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Jason Miller');

    foreach (range(1, 3) as $index) {
        \App\Ark\Operations\Communications\CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Customer opened estimate portal',
            'occurred_at' => now()->subHours(4 - $index),
        ]);
    }

    expect(app(EstimateFollowUpLifecycle::class)->isActive($repairOrder->fresh(['communicationEvents'])))->toBeTrue();

    app(CommunicationEventRecorder::class)->record(
        $repairOrder->fresh(),
        OperationalCommunicationType::ApprovalFollowUp,
        OperationalCommunicationChannel::Phone,
        OperationalCommunicationDirection::Outbound,
        'Called customer about estimate approval.',
        actor: $advisor,
    );

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::OperationalCommunicationLogged,
        $repairOrder->fresh(),
        actor: $advisor,
        payload: [
            'communication_type' => OperationalCommunicationType::ApprovalFollowUp->value,
            'channel' => OperationalCommunicationChannel::Phone->value,
            'direction' => OperationalCommunicationDirection::Outbound->value,
        ],
    );

    expect(app(EstimateFollowUpLifecycle::class)->isActive($repairOrder->fresh(['communicationEvents'])))->toBeFalse();

    $resolution = RecommendationResolution::query()->sole();

    expect($resolution->recommendation_kind)->toBe(TodayRecommendationKind::EstimateFollowUp->value)
        ->and($resolution->completion_event)->toBe(TodayCompletionEvent::FollowUpLogged->value)
        ->and($resolution->outcome_label)->toBe('Follow-up recorded')
        ->and($resolution->title_snapshot)->toBe('Call Jason');

    $this->actingAs($advisor)
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Shop Dashboard')
        ->assertDontSee('Call Jason');
});

test('parts arrival lifecycle surfaces receive recommendation for ordered parts', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    [$repairOrder, $line] = lifecycleRepairOrderWithOrderedPart('Parts Customer');

    $repairOrder->forceFill(['status' => RepairOrderStatus::WaitingParts])->save();
    $line->update(['procurement_state' => PartProcurementState::Ordered]);

    expect(app(PartsArrivalLifecycle::class)->isActive($repairOrder->fresh(['concerns.lines'])))->toBeTrue();

    $context = app(OperationsBriefingProjection::class)->contextFor($advisor);

    $candidate = collect(app(PartsArrivalLifecycle::class)->candidates($context, TodayLens::Advisor))
        ->first(fn ($item) => str_contains($item->title, 'Parts arrived'));

    expect($candidate)->not->toBeNull()
        ->and($candidate->title)->toContain('2019 Toyota Tacoma')
        ->and($candidate->expectedOutcome)->toBe('Technician can resume work.');

    $this->actingAs($advisor)
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Shop Dashboard')
        ->assertDontSee('Parts arrived · 2019 Toyota Tacoma');
});

test('marking parts received retires parts arrival and records resolution', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    [$repairOrder, $line] = lifecycleRepairOrderWithOrderedPart('Parts Customer');

    $repairOrder->forceFill(['status' => RepairOrderStatus::WaitingParts])->save();
    $line->update(['procurement_state' => PartProcurementState::Ordered]);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
            'procurement_state' => PartProcurementState::Received->value,
        ])
        ->assertRedirect();

    expect(app(PartsArrivalLifecycle::class)->isActive($repairOrder->fresh(['concerns.lines'])))->toBeFalse();

    $resolution = RecommendationResolution::query()->sole();

    expect($resolution->recommendation_kind)->toBe(TodayRecommendationKind::PartsArrival->value)
        ->and($resolution->completion_event)->toBe(TodayCompletionEvent::PartReceived->value)
        ->and($resolution->outcome_label)->toBe('Parts received');

    $this->actingAs($advisor)
        ->get(route('operations.today'))
        ->assertOk()
        ->assertDontSee('Parts arrived · 2019 Toyota Tacoma');
});

test('today projection excludes completed estimate follow-up recommendations', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Jason Miller');

    foreach (range(1, 3) as $index) {
        \App\Ark\Operations\Communications\CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Customer opened estimate portal',
            'occurred_at' => now()->subHours(4 - $index),
        ]);
    }

    $context = app(OperationsBriefingProjection::class)->contextFor($advisor);

    $titles = collect(app(TodayLifecycleComposer::class)->actionsBySection($context, TodayLens::Advisor))
        ->flatten(1)
        ->pluck('title');

    expect($titles)->toContain('Call Jason');

    app(CommunicationEventRecorder::class)->record(
        $repairOrder->fresh(),
        OperationalCommunicationType::ApprovalFollowUp,
        OperationalCommunicationChannel::Phone,
        OperationalCommunicationDirection::Outbound,
        'Follow-up call completed.',
        actor: $advisor,
    );

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::OperationalCommunicationLogged,
        $repairOrder->fresh(),
        actor: $advisor,
        payload: [
            'communication_type' => OperationalCommunicationType::ApprovalFollowUp->value,
            'channel' => OperationalCommunicationChannel::Phone->value,
            'direction' => OperationalCommunicationDirection::Outbound->value,
        ],
    );

    $afterTitles = collect(app(TodayLifecycleComposer::class)->actionsBySection($context, TodayLens::Advisor))
        ->flatten(1)
        ->pluck('title');

    expect($afterTitles)->not->toContain('Call Jason');
});

test('portal estimate approval retires estimate follow-up recommendation', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Jason Miller');

    foreach (range(1, 3) as $index) {
        \App\Ark\Operations\Communications\CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Customer opened estimate portal',
            'occurred_at' => now()->subHours(4 - $index),
        ]);
    }

    expect(app(EstimateFollowUpLifecycle::class)->isActive($repairOrder->fresh(['communicationEvents'])))->toBeTrue();

    $repairOrder->forceFill(['status' => RepairOrderStatus::Approved])->save();

    app(\App\Ark\Operations\Events\OperationalEventRecorder::class)->record(
        OperationalEventName::RepairOrderLifecycleChanged,
        $repairOrder->fresh(),
        payload: [
            'from_status' => RepairOrderStatus::WaitingApproval->value,
            'to_status' => RepairOrderStatus::Approved->value,
        ],
    );

    expect(app(EstimateFollowUpLifecycle::class)->isActive($repairOrder->fresh(['communicationEvents'])))->toBeFalse();

    $resolution = RecommendationResolution::query()->sole();

    expect($resolution->recommendation_kind)->toBe(TodayRecommendationKind::EstimateFollowUp->value)
        ->and($resolution->completion_event)->toBe(TodayCompletionEvent::EstimateApproved->value)
        ->and($resolution->outcome_label)->toBe('Customer approved estimate')
        ->and($resolution->title_snapshot)->toBe('Call Jason');
});

test('portal approval retires estimate follow-up when shop ro number differs from primary key', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Jason Miller');
    $repairOrder->forceFill(['repair_order_id' => 88001])->save();

    foreach (range(1, 3) as $index) {
        \App\Ark\Operations\Communications\CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Customer opened estimate portal',
            'occurred_at' => now()->subHours(4 - $index),
        ]);
    }

    expect($repairOrder->id)->not->toBe($repairOrder->repair_order_id);

    $repairOrder->forceFill(['status' => RepairOrderStatus::Approved])->save();

    app(\App\Ark\Operations\Events\OperationalEventRecorder::class)->record(
        OperationalEventName::RepairOrderLifecycleChanged,
        $repairOrder->fresh(),
        payload: [
            'from_status' => RepairOrderStatus::WaitingApproval->value,
            'to_status' => RepairOrderStatus::Approved->value,
        ],
    );

    $resolution = RecommendationResolution::query()->sole();

    expect($resolution->completion_event)->toBe(TodayCompletionEvent::EstimateApproved->value)
        ->and($resolution->aggregate_id)->toBe(88001);
});

/**
 * @return array{0: \App\Ark\Operations\RepairOrders\RepairOrder, 1: \App\Ark\Operations\RepairOrders\RepairOrderLine}
 */
function lifecycleRepairOrderWithOrderedPart(string $customerName = 'Parts Customer'): array
{
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-0100',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'PRT123',
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Tacoma',
        'vin' => '5TFRX4CN4KX123456',
        'normalized_vin' => '5TFRX4CN4KX123456',
    ]);

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::Approved,
        'concern_summary' => 'Approved brake work waiting on parts.',
    ]);

    $concern = \App\Ark\Operations\RepairOrders\RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake repair',
        'disposition' => \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $partLine = \App\Ark\Operations\RepairOrders\RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 9800,
        'part_cost_cents' => 5300,
        'vendor_name' => 'Local Parts Counter',
        'part_number' => 'PAD-123',
        'subtotal_cents' => 9800,
        'total_cents' => 9800,
    ]);

    return [$repairOrder, $partLine];
}
