<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartLineSource;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\PartsPressure;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('customer supplied parts use customer posture instead of shop ordering', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'part_cost' => '53.00',
        'unit_price' => '98.00',
        'pricing_mode' => 'manual',
        'vendor_name' => 'Customer',
        'part_number' => 'PAD-123',
        'part_source' => 'customer_supplied',
        'customer_part_posture' => 'in_hand',
    ])->assertRedirect();

    $line->refresh();

    expect($line->part_source)->toBe(PartLineSource::CustomerSupplied)
        ->and($line->procurement_state)->toBe(PartProcurementState::Received)
        ->and($line->procurementStateLabel())->toBe('In hand')
        ->and($line->hasUnresolvedProcurement())->toBeFalse()
        ->and($repairOrder->fresh()->hasUnresolvedApprovedParts())->toBeFalse();

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('In hand')
        ->and($html)->not->toContain('Needs ordered');
});

test('customer supplied parts waiting on customer stay unresolved without shop order actions', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();

    $line->update([
        'part_source' => PartLineSource::CustomerSupplied,
        'procurement_state' => PartProcurementState::AwaitingCustomer,
    ]);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
            'procurement_state' => PartProcurementState::Ordered->value,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHas('error');

    expect($line->fresh()->procurement_state)->toBe(PartProcurementState::AwaitingCustomer)
        ->and($line->procurementStateLabel())->toBe('Waiting on customer')
        ->and($line->procurementNextAction())->toBe('customer follow-up')
        ->and($repairOrder->fresh()->hasUnresolvedApprovedParts())->toBeTrue();

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
        'procurement_state' => PartProcurementState::Received->value,
    ])->assertRedirect();

    $line = $line->fresh();

    expect($line->procurement_state)->toBe(PartProcurementState::Received)
        ->and($line->procurementStateLabel())->toBe('In hand');
});

test('creating a customer supplied part requires customer part posture', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Customer supplied radiator',
        'part_cost' => '0.00',
        'pricing_mode' => 'manual',
        'unit_price' => '0.00',
        'quantity' => '1.00',
        'part_source' => 'customer_supplied',
    ])->assertSessionHasErrors('customer_part_posture');
});

test('part ordering is allowed when vehicle vin is missing', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $line] = repairOrderWithPartForProcurement();
    $repairOrder->vehicle->forceFill(['vin' => null, 'normalized_vin' => null])->save();

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
        'procurement_state' => PartProcurementState::Ordered->value,
    ])->assertRedirect()
        ->assertSessionHas('status');

    expect($line->fresh()->procurement_state)->toBe(PartProcurementState::Ordered)
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::PartOrdered->value)
            ->count())->toBe(1);
});

test('advisors can update part procurement state without changing financial totals', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $line] = repairOrderWithPartForProcurement();
    $totalsBefore = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
        'procurement_state' => PartProcurementState::Ordered->value,
    ])->assertRedirect();

    $line->refresh();
    $totalsAfter = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh());

    expect($line->procurement_state)->toBe(PartProcurementState::Ordered)
        ->and($totalsAfter->totalCents())->toBe($totalsBefore->totalCents());

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::PartOrdered->value)
        ->sole();

    expect($event->event_name)->toBe(OperationalEventName::PartOrdered->value)
        ->and($event->aggregate_type)->toBe(RepairOrder::class)
        ->and($event->aggregate_id)->toBe($repairOrder->id)
        ->and($event->actor_user_id)->toBe($advisor->id)
        ->and($event->payload_json)->toMatchArray([
            'line_id' => $line->id,
            'concern_id' => $line->repair_order_concern_id,
            'from_state' => PartProcurementState::None->value,
            'to_state' => PartProcurementState::Ordered->value,
            'part_number' => 'PAD-123',
            'vendor_name' => 'Local Parts Counter',
        ])
        ->and($event->payload_json)->not->toHaveKeys(['description', 'totals', 'part_cost_cents']);
});

test('part procurement actions use worksheet continuity on the ro editor', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('lines/'.$line->id.'/procurement')
        ->and($html)->toContain('data-procurement-form')
        ->and($html)->toContain('data-procurement-select')
        ->and($html)->toContain('data-current-state="none"')
        ->and($html)->toContain('data-refresh-scope="worksheet"')
        ->and($html)->toContain('data-continuity-focus="#line-'.$line->id.'"');
});

test('procurement state only applies to part lines and rejects unbelievable jumps', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $partLine, $laborLine] = repairOrderWithPartForProcurement(includeLabor: true);

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $laborLine]), [
        'procurement_state' => PartProcurementState::Ordered->value,
    ])->assertRedirect()
        ->assertSessionHas('error');

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $partLine]), [
        'procurement_state' => PartProcurementState::Installed->value,
    ])->assertRedirect()
        ->assertSessionHas('error');

    expect($partLine->fresh()->procurement_state)->toBe(PartProcurementState::None)
        ->and(OperationalEvent::query()->count())->toBe(0);
});

test('no-op procurement submissions do not record events', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
        'procurement_state' => PartProcurementState::None->value,
    ])->assertRedirect();

    expect($line->fresh()->procurement_state)->toBe(PartProcurementState::None)
        ->and(OperationalEvent::query()->count())->toBe(0);
});

test('procurement authority prevents installed parts from drifting backward', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();
    $line->update(['procurement_state' => PartProcurementState::Installed]);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
            'procurement_state' => PartProcurementState::Backordered->value,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHas('error');

    expect($line->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and(OperationalEvent::query()->count())->toBe(0);
});

test('waiting parts pressure is derived from unresolved approved part lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement(customerName: 'Blocked Parts');
    $repairOrder->update(['status' => RepairOrderStatus::Approved]);
    $line->update([
        'procurement_state' => PartProcurementState::Backordered,
        'sourcing_notes' => 'ETA Friday morning from Denver warehouse.',
    ]);

    expect($repairOrder->fresh()->partsPressure())->toBe(PartsPressure::Backordered)
        ->and($repairOrder->fresh()->workboardLaneStatus()->is(RepairOrderStatus::Approved))->toBeTrue();

    $this->get(route('operations.workboard', ['queue' => 'shop_floor']))
        ->assertRedirect(route('operations.index', ['queue' => 'shop_floor']));

    $this->get(route('operations.index', ['queue' => 'shop_floor']))
        ->assertOk()
        ->assertSee('Blocked Parts', false);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ETA Friday morning from Denver warehouse.');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Parts')
        ->assertSee('Backordered')
        ->assertSee('ETA Friday morning from Denver warehouse.');

    $this->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'parts']))
        ->assertOk()
        ->assertSee('Part Lines', false)
        ->assertSee('Front brake pads', false)
        ->assertSee('Backordered', false)
        ->assertSee('ETA Friday morning from Denver warehouse.', false);

    expect($line->fresh()->procurementNextAction())->toBe('vendor follow-up');

    $line->update(['procurement_state' => PartProcurementState::Received]);

    expect($repairOrder->fresh()->hasUnresolvedApprovedParts())->toBeFalse();
});

test('awaiting approval repair orders stay in waiting approval lane when approved parts are unresolved', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder, $line] = repairOrderWithPartForProcurement(customerName: 'Approval Lane Customer');
    $repairOrder->update(['status' => RepairOrderStatus::WaitingApproval]);
    $line->update(['procurement_state' => PartProcurementState::Ordered]);

    expect($repairOrder->fresh()->workboardLaneStatus()->is(RepairOrderStatus::WaitingApproval))->toBeTrue();

    $this->get(route('operations.workboard', ['queue' => 'waiting_approval']))
        ->assertRedirect(route('operations.index', ['queue' => 'waiting_approval']));

    expect($repairOrder->fresh()->partsPressure()->showsChip())->toBeTrue();

    $this->get(route('operations.index', ['queue' => 'waiting_approval']))
        ->assertOk()
        ->assertSee('Approval Lane Customer', false);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Approval Lane Customer')
        ->assertSee('Parts');
});

test('lifecycle select keeps in progress selectable while parts pressure is unresolved', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $technician = User::factory()->create(['name' => 'Parts Tech'])->assignRole(ArkRole::Technician->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $line] = repairOrderWithPartForProcurement();
    $repairOrder->update([
        'status' => RepairOrderStatus::ReadyForWork,
        'assigned_technician_id' => $technician->id,
    ]);
    $line->update(['procurement_state' => PartProcurementState::Ordered]);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('value="in_progress"')
        ->and($html)->not->toContain('value="in_progress" disabled')
        ->and($html)->not->toContain('Receive or install parts before moving to In Progress');
});

test('lifecycle allows in progress while approved parts are unresolved', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $technician = User::factory()->create(['name' => 'Parts Tech'])->assignRole(ArkRole::Technician->value);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();
    $repairOrder->update([
        'status' => RepairOrderStatus::ReadyForWork,
        'assigned_technician_id' => $technician->id,
    ]);
    $line->update(['procurement_state' => PartProcurementState::Ordered]);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    $repairOrder->refresh();

    expect($repairOrder->status->is(RepairOrderStatus::InProgress))->toBeTrue()
        ->and($repairOrder->partsPressure())->toBe(PartsPressure::WaitingParts)
        ->and($repairOrder->workboardLaneStatus()->is(RepairOrderStatus::InProgress))->toBeTrue();
});

test('lifecycle allows in progress when repair action has owner even without ro technician', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $technician = User::factory()->create(['name' => 'Bay Tech'])->assignRole(ArkRole::Technician->value);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderWithPartForProcurement();
    $repairOrder->update([
        'status' => RepairOrderStatus::Approved,
        'assigned_technician_id' => null,
    ]);
    $concern = $repairOrder->concerns()->firstOrFail();
    $concern->workGroups()->create([
        'title' => 'Brake repair',
        'position' => 1,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $technician->id,
    ]);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::InProgress))->toBeTrue();
});

test('lifecycle ignores draft and deferred unresolved parts when moving to in progress', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $technician = User::factory()->create(['name' => 'Bay Tech'])->assignRole(ArkRole::Technician->value);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $approvedPart] = repairOrderWithPartForProcurement();
    $repairOrder->update([
        'status' => RepairOrderStatus::Approved,
        'assigned_technician_id' => null,
    ]);
    $approvedPart->update(['procurement_state' => PartProcurementState::Received]);
    $approved = $repairOrder->concerns()->firstOrFail();
    $approved->workGroups()->create([
        'title' => 'Approved work',
        'position' => 1,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $technician->id,
    ]);

    $draft = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Possible alternator follow-up',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 2,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $draft->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Ultima Alternator',
        'quantity' => '1.00',
        'unit_price_cents' => 35000,
        'part_cost_cents' => 20000,
        'procurement_state' => PartProcurementState::None,
        'subtotal_cents' => 35000,
        'total_cents' => 35000,
    ]);

    $deferred = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Deferred suspension',
        'disposition' => RepairOrderConcernDisposition::Deferred,
        'position' => 3,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $deferred->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Control arm',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
        'part_cost_cents' => 6000,
        'procurement_state' => PartProcurementState::Ordered,
        'subtotal_cents' => 12000,
        'total_cents' => 12000,
    ]);

    expect($repairOrder->fresh()->hasUnresolvedApprovedParts())->toBeFalse();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::InProgress))->toBeTrue();
});

test('lifecycle blocks ready for work while approved parts are unresolved', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();
    $repairOrder->update(['status' => RepairOrderStatus::WaitingParts]);
    $line->update(['procurement_state' => PartProcurementState::Backordered]);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::ReadyForWork->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingParts))->toBeTrue();
});

test('part lifecycle records sourcing received backordered installed and canceled events', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
        'procurement_state' => PartProcurementState::Sourcing->value,
    ])->assertRedirect();

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line->fresh()]), [
        'procurement_state' => PartProcurementState::Backordered->value,
    ])->assertRedirect();

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line->fresh()]), [
        'procurement_state' => PartProcurementState::Canceled->value,
    ])->assertRedirect();

    $line->update(['procurement_state' => PartProcurementState::None]);

    $line->update(['procurement_state' => PartProcurementState::Ordered]);

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
        'procurement_state' => PartProcurementState::Received->value,
    ])->assertRedirect();

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line->fresh()]), [
        'procurement_state' => PartProcurementState::Backordered->value,
    ])->assertRedirect();

    $line->update(['procurement_state' => PartProcurementState::Received]);

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line->fresh()]), [
        'procurement_state' => PartProcurementState::Installed->value,
    ])->assertRedirect();

    $partEvents = OperationalEvent::query()
        ->orderBy('id')
        ->pluck('event_name')
        ->filter(static fn (string $name): bool => str_starts_with($name, 'part_'))
        ->values()
        ->all();

    expect($partEvents)->toBe([
        OperationalEventName::PartSourcingStarted->value,
        OperationalEventName::PartBackordered->value,
        OperationalEventName::PartCanceled->value,
        OperationalEventName::PartReceived->value,
        OperationalEventName::PartBackordered->value,
        OperationalEventName::PartInstalled->value,
    ]);
});

test('part line sourcing notes stay attached to estimate line and snapshot authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $line] = repairOrderWithPartForProcurement();

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'part_cost' => '53.00',
        'unit_price' => '98.00',
        'pricing_mode' => 'manual',
        'vendor_name' => 'Local Parts Counter',
        'part_number' => 'PAD-123',
        'sourcing_notes' => 'Call vendor if not here by 2 PM.',
    ])->assertRedirect();

    $line->refresh();

    expect($line->sourcing_notes)->toBe('Call vendor if not here by 2 PM.')
        ->and($line->total_cents)->toBe(9800);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $document = EstimateDocument::query()->sole();

    expect($document->snapshot_json['concerns'][0]['lines'][0]['sourcing_notes'])->toBe('Call vendor if not here by 2 PM.')
        ->and($document->snapshot_json['concerns'][0]['lines'][0]['procurement_state_label'])->toBe('Needs ordered')
        ->and($document->snapshot_json['concerns'][0]['lines'][0]['procurement_next_action'])->toBe('source / order');
});

test('parts pressure derives from approved part line procurement states', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    [$repairOrder, $line] = repairOrderWithPartForProcurement();

    expect($repairOrder->fresh()->partsPressure())->toBe(PartsPressure::WaitingParts);

    $line->update(['procurement_state' => PartProcurementState::Backordered]);

    expect($repairOrder->fresh()->partsPressure())->toBe(PartsPressure::Backordered);

    $line->update(['procurement_state' => PartProcurementState::Received]);

    expect($repairOrder->fresh()->partsPressure())->toBe(PartsPressure::AllPartsAvailable);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Crank seal',
        'quantity' => '1.00',
        'unit_price_cents' => 4500,
        'part_cost_cents' => 2200,
        'procurement_state' => PartProcurementState::Ordered,
        'subtotal_cents' => 4500,
        'total_cents' => 4500,
    ]);

    expect($repairOrder->fresh()->partsPressure())->toBe(PartsPressure::PartialParts)
        ->and($repairOrder->fresh()->partsPressureSummary())->toContain('Crank seal ordered');
});

test('large mixed part readiness stays compact and queue derived', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $firstLine] = repairOrderWithPartForProcurement(customerName: 'Mixed Parts');
    $states = [
        PartProcurementState::None,
        PartProcurementState::Sourcing,
        PartProcurementState::Ordered,
        PartProcurementState::Partial,
        PartProcurementState::Backordered,
        PartProcurementState::Received,
        PartProcurementState::Installed,
    ];

    foreach (range(1, 20) as $index) {
        $state = $states[$index % count($states)];
        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $firstLine->repair_order_concern_id,
            'type' => RepairOrderLineType::Part,
            'description' => 'Part '.$index,
            'quantity' => '1.00',
            'unit_price_cents' => 1000 + $index,
            'part_cost_cents' => 500 + $index,
            'vendor_name' => 'Vendor '.$index,
            'part_number' => 'P-'.$index,
            'procurement_state' => $state,
            'subtotal_cents' => 1000 + $index,
            'total_cents' => 1000 + $index,
        ]);
    }

    expect($repairOrder->fresh()->partsPressure())->toBe(PartsPressure::PartialParts);

    $this->get(route('operations.workboard', ['queue' => 'shop_floor']))
        ->assertRedirect(route('operations.index', ['queue' => 'shop_floor']));

    expect($repairOrder->fresh()->partsPressure())->toBe(PartsPressure::PartialParts);

    $this->get(route('operations.index', ['queue' => 'shop_floor']))
        ->assertOk()
        ->assertSee('Mixed Parts', false);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Mixed Parts')
        ->assertSee('Partial Parts');

    $summary = $repairOrder->fresh()->procurementReadinessSummary();

    expect($summary)->toContain('Need ordered')
        ->and($summary)->toContain('Sourcing')
        ->and($summary)->toContain('Backordered')
        ->and($summary)->toContain('Received')
        ->and($summary)->toContain('Installed');
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderLine, 2?: RepairOrderLine}
 */
function repairOrderWithPartForProcurement(string $customerName = 'Parts Customer', bool $includeLabor = false): array
{
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');

    $customer = Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'PRT123',
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Tacoma',
        'vin' => '5TFRX4CN4KX123456',
        'normalized_vin' => '5TFRX4CN4KX123456',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Approved,
        'concern_summary' => 'Approved brake work waiting on parts.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake repair',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $partLine = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 9800,
        'part_cost_cents' => 5300,
        'vendor_name' => 'Local Parts Counter',
        'part_number' => 'PAD-123',
        'subtotal_cents' => 9800,
        'total_cents' => 9800,
    ]);

    if (! $includeLabor) {
        return [$repairOrder, $partLine];
    }

    $laborLine = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Install front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    return [$repairOrder, $partLine, $laborLine];
}
