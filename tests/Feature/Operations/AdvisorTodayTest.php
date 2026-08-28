<?php

use App\Ark\Operations\Commitments\CommitmentStatus;
use App\Ark\Operations\Commitments\CommitmentType;
use App\Ark\Operations\Commitments\OperationalCommitment;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLostReason;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Staff\StaffFrontDoor;
use App\Ark\Operations\Today\TodayPipelineInventoryQuery;
use App\Ark\Operations\Today\TodayPipelineProjection;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('advisor home renders customer first attention cockpit', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Search job board', false)
        ->assertDontSee('+ Create Repair Order', false)
        ->assertSee('Estimates', false)
        ->assertSee('Work in Progress', false)
        ->assertSee('Completed', false)
        ->assertDontSee('Active Cars', false)
        ->assertDontSee('Biggest Pending', false)
        ->assertDontSee('ops-advisor-home-cockpit', false)
        ->assertDontSee('Overview', false)
        ->assertDontSee('My work', false)
        ->assertDontSee('ARK Manager brief')
        ->assertDontSee('Work queues');
});

test('today url renders for advisors', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get('/app/today')
        ->assertOk();
});

test('advisor today surfaces explainable recommendation for estimate viewed', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Today Rec');
    $repairOrder->update(['concern_summary' => 'TODAY-REC-UNIQUE']);

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer opened estimate portal',
        'occurred_at' => now()->subHour(),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Waiting Approval', false)
        ->assertSee('RO #'.$repairOrder->repair_order_id, false)
        ->assertSee('Today Rec', false);

    Carbon::setTestNow();
});

test('today merges multiple signals for one repair order into one recommendation', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'John',
        lastName: 'Smith',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 235_000,
    );

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer opened estimate portal',
        'occurred_at' => now()->subDays(4),
    ]);

    $engine = app(\App\Ark\Operations\Today\AdvisorTodayRecommendationEngine::class);
    $repairOrders = app(\App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery::class)->forAdvisor();
    $recommendations = $engine->recommendations($repairOrders);
    $forRepairOrder = collect($recommendations)->where('repairOrderId', $repairOrder->repair_order_id);

    expect($forRepairOrder)->toHaveCount(1);

    $recommendation = $forRepairOrder->first();

    expect($recommendation->title)->toBe('Follow up with John Smith')
        ->and($recommendation->whyReasons)->toContain('Customer decision needed')
        ->and($recommendation->whyReasons)->toContain('Estimate Viewed')
        ->and(count($recommendation->whyReasons))->toBeGreaterThanOrEqual(2);

    Carbon::setTestNow();
});

test('work queue rows match repair orders in each queue', function () {
    decisionPressureRepairOrder(
        firstName: 'Radar',
        lastName: 'Check',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 50_000,
    );

    $query = app(\App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery::class);
    $repairOrders = $query->forAdvisor();
    $triage = app(\App\Ark\Operations\Workboard\WorkboardTriageProjection::class);
    $queues = app(\App\Ark\Operations\Today\AdvisorTodayShopRadarBuilder::class)->workQueueRows($repairOrders);

    foreach ($queues as $row) {
        $queueOrders = $triage->repairOrdersInQueue($repairOrders, $row->key);

        expect($row->count)->toBe($queueOrders->count());
    }
});

test('staff post login lands on communications attention', function () {
    expect(StaffFrontDoor::landingRouteName(actingAsLearnCurrentAdvisor()))->toBe('operations.today');
});

test('today surfaces flow constraint and explainability for waiting approval pressure', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();

    decisionPressureRepairOrder(
        firstName: 'Flow',
        lastName: 'Constraint',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 450_000,
    );

    decisionPressureRepairOrder(
        firstName: 'Flow',
        lastName: 'Second',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 380_000,
    );

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Flow Constraint', false)
        ->assertSee('Flow Second', false);

    Carbon::setTestNow();
});

test('advisor can snooze a today recommendation for their account only', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Snooze Rec');
    $repairOrder->update(['concern_summary' => 'TODAY-SNOOZE-UNIQUE']);

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer opened estimate portal',
        'occurred_at' => now()->subHour(),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('RO #'.$repairOrder->repair_order_id, false)
        ->assertSee('Snooze Rec', false);

    $this->actingAs($advisor)
        ->post(route('operations.today.snooze'), [
            'repair_order_id' => $repairOrder->repair_order_id,
            'duration' => 'tomorrow',
        ])
        ->assertRedirect(route('operations.index'))
        ->assertSessionHas('status');

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();

    expect(\App\Ark\Operations\Today\TodayRecommendationSnooze::query()
        ->where('user_id', $advisor->id)
        ->where('repair_order_id', $repairOrder->repair_order_id)
        ->where('snoozed_until', '>', now())
        ->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('advisor can close lost a today recommendation with coded reason', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Close',
        lastName: 'LostToday',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 45_375,
    );

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Close LostToday', false);

    $this->actingAs($advisor)
        ->post(route('operations.today.close-lost'), [
            'repair_order_id' => $repairOrder->repair_order_id,
            'lost_reason_key' => RepairOrderLostReason::NoResponse->value,
        ])
        ->assertRedirect(route('operations.index'))
        ->assertSessionHas('status');

    $repairOrder = $repairOrder->fresh();

    expect($repairOrder->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($repairOrder->close_variant_key)->toBe('lost')
        ->and($repairOrder->lost_reason_key)->toBe(RepairOrderLostReason::NoResponse)
        ->and($repairOrder->lost_reason_recorded_by)->toBe($advisor->id);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Close LostToday', false);

    Carbon::setTestNow();
});

test('today close lost requires a coded lost reason', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Missing',
        lastName: 'Reason',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 25_000,
    );

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();

    $this->actingAs($advisor)
        ->post(route('operations.today.close-lost'), [
            'repair_order_id' => $repairOrder->repair_order_id,
        ])
        ->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors(['lost_reason_key']);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();

    Carbon::setTestNow();
});

test('first work visit records front door landing event', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();

    $event = OperationalEvent::query()->sole();

    expect($event->event_name)->toBe(OperationalEventName::StaffFrontDoorLanded->value)
        ->and($event->payload_json)->toMatchArray(['surface' => 'attention']);
});

test('today pipeline sums operational money buckets and links to repair orders', function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();

    $waiting = decisionPressureRepairOrder(
        firstName: 'Wait',
        lastName: 'Approval',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 1_134_000,
    );

    $approved = decisionPressureRepairOrder(
        firstName: 'Approved',
        lastName: 'Queue',
        status: RepairOrderStatus::Approved,
        lineCents: 422_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );

    $pickup = decisionPressureRepairOrder(
        firstName: 'Ready',
        lastName: 'Pickup',
        status: RepairOrderStatus::ReadyPickup,
        lineCents: 387_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $pickup->id,
        'customer_id' => $pickup->customer_id,
        'entry_type' => LedgerEntryType::Payment,
        'amount_cents' => 217_620,
        'recorded_at' => now()->subDays(2),
    ]);

    $metrics = collect(app(TodayPipelineProjection::class)->metrics())->keyBy(fn ($metric) => $metric->key);

    expect($metrics[TodayPipelineInventoryQuery::AWAITING_APPROVAL]->amountCents)->toBe(1_134_000)
        ->and($metrics[TodayPipelineInventoryQuery::APPROVED_NOT_STARTED]->amountCents)->toBe(422_000)
        ->and($metrics[TodayPipelineInventoryQuery::READY_FOR_PICKUP]->amountCents)->toBe(387_000)
        ->and($metrics[TodayPipelineInventoryQuery::REVENUE_IN_FLIGHT]->amountCents)->toBe(1_943_000)
        ->and($metrics[TodayPipelineInventoryQuery::COLLECTED_THIS_MONTH]->amountCents)->toBe(217_620);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Pipeline', false);

    $this->actingAs($advisor)
        ->get($metrics[TodayPipelineInventoryQuery::AWAITING_APPROVAL]->inventoryUrl)
        ->assertOk()
        ->assertSee((string) $waiting->repair_order_id, false);

    $this->actingAs($advisor)
        ->get($metrics[TodayPipelineInventoryQuery::REVENUE_IN_FLIGHT]->inventoryUrl)
        ->assertOk()
        ->assertSee((string) $waiting->repair_order_id, false)
        ->assertSee((string) $approved->repair_order_id, false)
        ->assertSee((string) $pickup->repair_order_id, false);

    Carbon::setTestNow();
});

test('today surfaces open commitments due today and overdue', function () {
    Carbon::setTestNow('2026-06-18 16:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'John',
        lastName: 'Smith',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 235_000,
    );

    OperationalCommitment::query()->create([
        'repair_order_id' => $repairOrder->id,
        'owner_user_id' => $advisor->id,
        'created_by' => $advisor->id,
        'type' => CommitmentType::CustomerUpdate,
        'status' => CommitmentStatus::Open,
        'reason' => 'Warranty callback promised',
        'due_at' => '2026-06-18 15:00:00',
    ]);

    OperationalCommitment::query()->create([
        'repair_order_id' => $repairOrder->id,
        'owner_user_id' => $advisor->id,
        'created_by' => $advisor->id,
        'type' => CommitmentType::CustomerUpdate,
        'status' => CommitmentStatus::Open,
        'reason' => 'Parts arrival update promised',
        'due_at' => '2026-06-17 16:00:00',
    ]);

    OperationalCommitment::query()->create([
        'repair_order_id' => $repairOrder->id,
        'owner_user_id' => $advisor->id,
        'created_by' => $advisor->id,
        'type' => CommitmentType::CustomerUpdate,
        'status' => CommitmentStatus::Open,
        'reason' => 'Future follow-up',
        'due_at' => '2026-06-20 16:00:00',
    ]);

    $summary = app(\App\Ark\Operations\Today\TodayCommitmentsProjection::class)->summary();

    expect($summary->dueTodayCount)->toBe(1)
        ->and($summary->overdueCount)->toBe(1)
        ->and(collect($summary->rows)->pluck('reason')->all())->toContain('Warranty callback promised', 'Parts arrival update promised')
        ->and(collect($summary->rows)->pluck('reason')->all())->not->toContain('Future follow-up');

    Carbon::setTestNow();
});

test('advisor can record and fulfill a commitment from repair order comms', function () {
    Carbon::setTestNow('2026-06-18 16:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Commit RO');

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.commitments.store', $repairOrder), [
            'type' => CommitmentType::CustomerUpdate->value,
            'reason' => 'Will call after warranty review',
            'due_at' => '2026-06-19T10:00',
            'owner_user_id' => $advisor->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $commitment = OperationalCommitment::query()->sole();

    expect($commitment->reason)->toBe('Will call after warranty review')
        ->and($commitment->status)->toBe(CommitmentStatus::Open);

    $this->actingAs($advisor)
        ->post(route('operations.commitments.fulfill', $commitment))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($commitment->fresh()->status)->toBe(CommitmentStatus::Fulfilled)
        ->and($commitment->fresh()->fulfilled_by)->toBe($advisor->id);

    Carbon::setTestNow();
});
