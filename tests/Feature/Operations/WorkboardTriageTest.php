<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\WorkboardSwimlaneCatalog;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('advisor home board renders kanban columns with dense cards', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Lane Seed');
    $repairOrder->update(['concern_summary' => 'TRIAGE-LANE-SEED']);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Search job board', false)
        ->assertDontSee('ops-advisor-home-cockpit', false)
        ->assertSee('Estimates', false)
        ->assertSee('Work in Progress', false)
        ->assertSee('Completed', false)
        ->assertSee('ops-advisor-home-board', false)
        ->assertSee('RO #'.$repairOrder->repair_order_id, false)
        ->assertSee('Lane Seed', false)
        ->assertDontSee('ops-ops-workspace', false)
        ->assertDontSee('Choose a queue');
});

test('advisor home board includes intake statuses in estimates column', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $draft = repairOrderForCommunication(RepairOrderStatus::Draft, 'Triage Draft');
    $draft->update(['concern_summary' => 'TRIAGE-DRAFT-CARD']);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('RO #'.$draft->repair_order_id, false)
        ->assertSee('Triage Draft', false);
});

test('advisor home board shows every active repair order without card cap', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    foreach (range(1, 30) as $index) {
        $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Cap Lane '.$index);
        $repairOrder->update(['concern_summary' => 'CAP-LANE-RO-'.$index]);
    }

    $response = $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('+5 more in inventory');

    expect(substr_count($response->getContent(), 'id="ops-card-ro-'))->toBe(30);
});

test('advisor home board surfaces observation signal instead of raw comms event copy', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Obs Signal');
    $repairOrder->update(['concern_summary' => 'OBS-SIGNAL-RO-UNIQUE']);

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer opened estimate portal',
        'occurred_at' => now()->subHours(2),
    ]);

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer opened estimate portal again',
        'occurred_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($advisor)
        ->get(route('operations.index'));

    expect($response->status())->toBe(200);
    expect($response->getContent())->toContain('Obs Signal');
    expect($response->getContent())->toContain('Waiting Approval');
    expect($response->getContent())->not->toContain('Customer opened estimate portal again');

    Carbon::setTestNow();
});

test('workboard triage projection sorts cards by pressure then age', function () {
    $older = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Older Pressure');
    RepairOrder::query()->whereKey($older->getKey())->update(['updated_at' => now()->subDays(3)]);

    $newer = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Newer Pressure');
    RepairOrder::query()->whereKey($newer->getKey())->update(['updated_at' => now()->subHour()]);

    CommunicationEvent::query()->create([
        'repair_order_id' => $newer->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'View',
        'occurred_at' => now()->subMinutes(30),
    ]);

    $projection = app(WorkboardTriageProjection::class)->forAdvisor(
        RepairOrder::query()->whereIn('id', [$older->id, $newer->id])->get(),
    );

    $incoming = collect($projection->swimlanes)->firstWhere('key', 'incoming');
    $waitingLane = collect($incoming->lanes)->firstWhere('key', 'waiting_approval');

    expect($waitingLane->visibleCards[0]->repairOrder->id)->toBe($newer->id);
});

test('repair order index supports awaiting pickup queue filter', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();

    $recent = repairOrderForCommunication(RepairOrderStatus::ReadyPickup, 'Recent');
    RepairOrder::query()->whereKey($recent->getKey())->update([
        'updated_at' => now()->subDay(),
        'concern_summary' => 'RECENT-PICKUP-UNIQUE',
    ]);

    $stale = repairOrderForCommunication(RepairOrderStatus::ReadyPickup, 'Stale');
    RepairOrder::query()->whereKey($stale->getKey())->update([
        'updated_at' => now()->subDays(WorkboardSwimlaneCatalog::PICKUP_RECENT_DAYS + 2),
        'concern_summary' => 'STALE-PICKUP-UNIQUE',
    ]);

    $response = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index', ['status' => RepairOrderStatus::ReadyPickup->value, 'pickup' => 'stale']))
        ->assertOk()
        ->assertSee('Overdue pickup queue');

    expect($response->getContent())->toContain('STALE-PICKUP-UNIQUE')
        ->and($response->getContent())->not->toContain('RECENT-PICKUP-UNIQUE');

    Carbon::setTestNow();
});

test('workboard triage projection marks stale pickup cards as overdue', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $stale = repairOrderForCommunication(RepairOrderStatus::ReadyPickup, 'Stale Projection');
    RepairOrder::query()->whereKey($stale->getKey())->update([
        'updated_at' => now()->subDays(WorkboardSwimlaneCatalog::PICKUP_RECENT_DAYS + 2),
        'concern_summary' => 'STALE-PICKUP-PROJECTION-UNIQUE',
    ]);

    $projection = app(WorkboardTriageProjection::class)->forAdvisor(
        RepairOrder::query()->whereKey($stale->getKey())->get(),
    );

    $outgoing = collect($projection->swimlanes)->firstWhere('key', 'outgoing');
    $lane = collect($outgoing->lanes)->firstWhere('key', 'ready_pickup');

    expect($lane->visibleCards)->toBeEmpty()
        ->and($projection->pickupOverflow?->totalAwaitingPickup)->toBe(1);

    Carbon::setTestNow();
});

test('advisor home board includes stale ready pickup cards in ready pickup column', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();

    $recent = repairOrderForCommunication(RepairOrderStatus::ReadyPickup, 'Recent Pickup');
    RepairOrder::query()->whereKey($recent->getKey())->update([
        'updated_at' => now()->subDay(),
        'concern_summary' => 'RECENT-PICKUP-LANE-1',
    ]);

    $stale = repairOrderForCommunication(RepairOrderStatus::ReadyPickup, 'Stale Pickup');
    RepairOrder::query()->whereKey($stale->getKey())->update([
        'updated_at' => now()->subDays(WorkboardSwimlaneCatalog::PICKUP_RECENT_DAYS + 2),
        'concern_summary' => 'STALE-PICKUP-LANE-UNIQUE',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Recent Pickup', false)
        ->assertSee('Stale Pickup', false);

    Carbon::setTestNow();
});

test('advisor home board projection sorts cards by pressure then age', function () {
    $older = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Older Pressure');
    RepairOrder::query()->whereKey($older->getKey())->update(['updated_at' => now()->subDays(3)]);

    $newer = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Newer Pressure');
    RepairOrder::query()->whereKey($newer->getKey())->update(['updated_at' => now()->subHour()]);

    CommunicationEvent::query()->create([
        'repair_order_id' => $newer->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'View',
        'occurred_at' => now()->subMinutes(30),
    ]);

    $columns = app(WorkboardTriageProjection::class)->forAdvisorHomeBoard(
        RepairOrder::query()->whereIn('id', [$older->id, $newer->id])->get(),
    );

    $waitingApproval = collect($columns)->firstWhere('key', 'waiting_approval');

    expect($waitingApproval->visibleCards[0]->repairOrder->id)->toBe($newer->id);
});

test('repair order index supports unassigned shop floor queue filter', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = User::factory()->create();
    $technician->assignRole(ArkRole::Technician->value);

    $unassigned = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Unassigned Tech');
    RepairOrder::query()->whereKey($unassigned->getKey())->update([
        'assigned_technician_id' => null,
        'concern_summary' => 'UNASSIGNED-RO-AAA',
    ]);

    $assigned = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Assigned Tech');
    RepairOrder::query()->whereKey($assigned->getKey())->update([
        'assigned_technician_id' => $technician->id,
        'concern_summary' => 'ASSIGNED-RO-BBB',
    ]);

    expect($assigned->fresh()->assigned_technician_id)->toBe($technician->id);

    $filteredCount = RepairOrder::query()
        ->whereIn('status', WorkboardSwimlaneCatalog::shopFloorSlugs())
        ->whereNull('assigned_technician_id')
        ->count();

    expect($filteredCount)->toBe(1);

    $response = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index', ['unassigned' => '1']))
        ->assertOk()
        ->assertSee('Unassigned shop floor queue')
        ->assertSee('1 total');

    expect($response->getContent())->toContain('UNASSIGNED-RO-AAA')
        ->and($response->getContent())->not->toContain('ASSIGNED-RO-BBB');
});

test('repair order index supports shop floor lane inventory filter', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $shopFloor = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Shop Floor');
    $shopFloor->update(['concern_summary' => 'SHOP-FLOOR-RO-AAA']);

    $waitingParts = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Waiting Parts');
    $waitingParts->update(['concern_summary' => 'WAITING-PARTS-RO-BBB']);

    $response = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index', ['lane' => 'shop_floor']))
        ->assertOk()
        ->assertSee('Shop floor queue');

    expect($response->getContent())->toContain('SHOP-FLOOR-RO-AAA')
        ->and($response->getContent())->not->toContain('WAITING-PARTS-RO-BBB');
});

test('repair order index supports customer waiting attention inventory filter', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();

    $waiting = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Waiting Customer');
    $waiting->update(['concern_summary' => 'CUSTOMER-WAITING-RO-AAA']);

    CommunicationEvent::query()->create([
        'repair_order_id' => $waiting->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer opened estimate portal',
        'occurred_at' => now()->subHour(),
    ]);

    $quiet = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Quiet Parts');
    $quiet->update(['concern_summary' => 'QUIET-PARTS-RO-BBB']);

    $response = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index', ['attention' => 'customer_waiting']))
        ->assertOk()
        ->assertSee('Customer waiting queue');

    expect($response->getContent())->toContain('CUSTOMER-WAITING-RO-AAA')
        ->and($response->getContent())->not->toContain('QUIET-PARTS-RO-BBB');

    Carbon::setTestNow();
});

test('advisor home board shows empty state when shop is clear', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('ops-advisor-home-cockpit', false)
        ->assertSee('Nothing here', false);
});

test('repair order index supports needs attention inventory filter', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();

    $pressured = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Needs Attention');
    $pressured->update(['concern_summary' => 'NEEDS-ATTENTION-RO-AAA']);

    foreach (range(1, 2) as $index) {
        CommunicationEvent::query()->create([
            'repair_order_id' => $pressured->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'View '.$index,
            'occurred_at' => now()->subHours($index),
        ]);
    }

    $quiet = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Quiet Parts');
    $quiet->update(['concern_summary' => 'QUIET-PARTS-RO-BBB']);

    $response = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index', ['attention' => 'needs_attention']))
        ->assertOk()
        ->assertSee('Needs attention queue')
        ->assertSee('← Workboard triage')
        ->assertSee(route('operations.index', ['queue' => 'needs_attention']), false);

    expect($response->getContent())->toContain('NEEDS-ATTENTION-RO-AAA')
        ->and($response->getContent())->not->toContain('QUIET-PARTS-RO-BBB');

    Carbon::setTestNow();
});

test('repair order index links back to workboard triage for lane queues', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index', ['lane' => 'shop_floor']))
        ->assertOk()
        ->assertSee('← Workboard triage')
        ->assertSee(route('operations.index', ['queue' => 'shop_floor']), false);
});

test('advisor home board surfaces parts pressure on ready for work cards', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::Approved, 'Parts Pressure');
    $repairOrder->update(['concern_summary' => 'SHOP-FLOOR-PARTS-PRESSURE']);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved brake work',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'immediate_attention',
        'position' => 2,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 9800,
        'part_cost_cents' => 5300,
        'procurement_state' => PartProcurementState::Backordered,
        'subtotal_cents' => 9800,
        'total_cents' => 9800,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Parts Pressure', false);
});
