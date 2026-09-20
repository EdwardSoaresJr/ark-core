<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Commitments\CommitmentStatus;
use App\Ark\Operations\Commitments\CommitmentType;
use App\Ark\Operations\Commitments\OperationalCommitment;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('advisor home shows compact brief and full active repair order board', function () {
    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '7195550100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake noise when stopping.',
    ]);

    $concern = \App\Ark\Operations\RepairOrders\RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise when stopping.',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 902_700,
        'subtotal_cents' => 902_700,
        'total_cents' => 902_700,
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Active Cars', false)
        ->assertDontSee('Biggest Pending', false)
        ->assertDontSee('ops-advisor-home-cockpit', false)
        ->assertSee('Estimates', false)
        ->assertSee('Waiting Approval', false)
        ->assertSee('Waiting Parts', false)
        ->assertSee('Work in Progress', false)
        ->assertSee('Completed', false)
        ->assertSee('Search job board', false)
        ->assertDontSee('+ Create Repair Order', false)
        ->assertSee('John Smith', false)
        ->assertSee('2018 Ram 2500', false)
        ->assertSee('Estimate — Ready', false)
        ->assertSee('ops-job-card__mark--ready', false)
        ->assertSee('ops-job-card__mark-icon', false)
        ->assertSee('ops-job-card__activity', false)
        ->assertDontSee('ops-job-card__chip--warn', false)
        ->assertSee('$9,027', false)
        ->assertSee('pending', false)
        ->assertSee('ops-job-card__mark--none', false)
        ->assertDontSee('ops-job-card__exception-mark', false)
        ->assertDontSee('ops-job-card__total--empty', false)
        ->assertDontSee('! Missed appointment', false)
        ->assertDontSee('! Follow-up overdue', false)
        ->assertDontSee('! Needs parts', false)
        ->assertDontSee('! Pickup overdue', false)
        ->assertDontSee('Waiting on decision · not sent', false)
        ->assertDontSee('RO created', false)
        ->assertDontSee('No Promise Time', false)
        ->assertSee('+ Check In', false)
        ->assertDontSee('All ROs', false)
        ->assertDontSee('Overview', false)
        ->assertDontSee('What should I work on next?');
});

test('advisor workboard redirects to home board', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.workboard'))
        ->assertRedirect(route('operations.index'));
});

test('advisor workboard preserves query string when redirecting home', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.workboard', ['queue' => 'waiting_approval']))
        ->assertRedirect(route('operations.index', ['queue' => 'waiting_approval']));
});

test('advisor home waiting approval column holds customer-decision repair orders', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    decisionPressureRepairOrder(
        firstName: 'Quiet',
        lastName: 'Customer',
        status: RepairOrderStatus::Approved,
        lineCents: 45_112,
        disposition: RepairOrderConcernDisposition::Approved,
    );

    $hot = decisionPressureRepairOrder(
        firstName: 'Hot',
        lastName: 'Ram',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 902_764,
    );

    CommunicationEvent::query()->create([
        'repair_order_id' => $hot->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer opened estimate portal',
        'occurred_at' => now()->subDays(4),
    ]);

    $response = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Waiting Approval', false);

    preg_match('/id="ops-home-col-waiting_approval"(.*?)id="ops-home-col-parts"/s', $response->getContent(), $matches);
    expect($matches[1] ?? '')
        ->toContain('ops-card-ro-'.$hot->repair_order_id)
        ->toContain('Waiting Approval')
        ->toContain('Estimate — Viewed')
        ->toContain('4d')
        ->toContain('pending')
        ->toContain('data-workboard-decision="1"')
        ->toContain('ops-job-card__activity')
        ->toContain('ops-job-card__mark--engaged')
        ->toContain('ops-job-card__mark-badge')
        ->toContain('ops-job-card__exception')
        ->not->toContain('RO created')
        ->not->toContain('ops-job-card__status-menu')
        ->not->toContain('Move to');

    Carbon::setTestNow();
});

test('home card does not offer status mutation', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Board',
        lastName: 'Move',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 90_000,
    );

    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Move to', false)
        ->assertDontSee('ops-job-card__status-menu', false)
        ->assertDontSee('Change status', false)
        ->assertSee('Waiting Approval', false);

    $this->from(route('operations.index'))
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::Estimate->value,
        ])
        ->assertRedirect(route('operations.index'));

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Estimate))->toBeTrue();
});

test('waiting approval cards surface configured status not estimate overlay', function () {
    decisionPressureRepairOrder(
        firstName: 'Auth',
        lastName: 'Chip',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 250_000,
    );

    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Auth Chip', false)
        ->getContent();

    expect($html)
        ->toContain('ops-job-card__chip-label">Waiting Approval')
        ->toContain('Estimate — Ready')
        ->not->toContain('ops-job-card__chip-label">Not Sent')
        ->not->toContain('ops-job-card__chip--warn');
});

test('home card status chip tracks lifecycle after a board move', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Chip',
        lastName: 'Tracks',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 90_000,
    );

    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->from(route('operations.index'))
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::Estimate->value,
        ])
        ->assertRedirect(route('operations.index'));

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Building', false)
        ->assertDontSee('Requires Authorization', false);
});

test('in progress cards hide empty labor progress and empty promise time', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Labor',
        lastName: 'Progress',
        status: RepairOrderStatus::InProgress,
        lineCents: 180_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );

    $repairOrder->lines()->first()?->update([
        'type' => RepairOrderLineType::Labor,
        'labor_billed_hours' => '1.50',
        'quantity' => '1.50',
    ]);

    $technician = User::factory()->create(['name' => 'Bay Tech']);
    $repairOrder->update(['assigned_technician_id' => $technician->id]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('hrs complete', false)
        ->assertDontSee('No Promise Time', false)
        ->assertSee('Search job board', false)
        ->assertSee('All employees', false)
        ->assertDontSee('RO created', false)
        ->assertSee('ops-job-card__clock', false)
        ->assertSee('ops-job-card__activity', false)
        ->assertSee('ops-job-card__exception', false);
});

test('in progress cards surface labor progress once hours are complete', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Labor',
        lastName: 'Done',
        status: RepairOrderStatus::InProgress,
        lineCents: 180_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );

    $repairOrder->lines()->first()?->update([
        'type' => RepairOrderLineType::Labor,
        'labor_billed_hours' => '1.50',
        'quantity' => '1.50',
    ]);
    $repairOrder->concerns()->first()?->update([
        'production_status' => ScopeProductionStatus::Completed->value,
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('hrs complete', false)
        ->assertDontSee('ops-job-card__progress', false);
});

test('home card surfaces the next appointment on the job board', function () {
    Carbon::setTestNow('2026-08-24 09:00:00');

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Edwin',
        lastName: 'Scheduled',
        status: RepairOrderStatus::Estimate,
        lineCents: 15_525,
    );

    Appointment::query()->create([
        'customer_id' => $repairOrder->customer_id,
        'vehicle_id' => $repairOrder->vehicle_id,
        'repair_order_id' => $repairOrder->id,
        'created_by_user_id' => actingAsLearnCurrentAdvisor()->id,
        'advisor_user_id' => actingAsLearnCurrentAdvisor()->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-08-24 14:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-08-24 15:00')->utc(),
        'concern' => 'Brake noise',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Edwin Scheduled', false)
        ->assertSee('Scheduled — Today 2:00 PM', false)
        ->assertDontSee('Appointment · Today 2:00 PM', false);

    Carbon::setTestNow();
});

test('home card surfaces a vehicle appointment even when the RO is not linked', function () {
    Carbon::setTestNow('2026-08-23 09:00:00');

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Edwin',
        lastName: 'Bedburdick',
        status: RepairOrderStatus::Estimate,
        lineCents: 15_525,
    );

    Appointment::query()->create([
        'customer_id' => $repairOrder->customer_id,
        'vehicle_id' => $repairOrder->vehicle_id,
        'repair_order_id' => null,
        'created_by_user_id' => actingAsLearnCurrentAdvisor()->id,
        'advisor_user_id' => actingAsLearnCurrentAdvisor()->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-08-24 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-08-24 10:00')->utc(),
        'concern' => 'Comeback',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Edwin Bedburdick', false)
        ->assertSee('Scheduled — Tomorrow 9:00 AM', false)
        ->assertDontSee('Appointment · Tomorrow 9:00 AM', false);

    Carbon::setTestNow();
});

test('home card anchors RO number upper left before vehicle and customer', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Quick',
        lastName: 'Actions',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 120_000,
    );

    $repairOrder->customer?->update(['phone' => '7195550199']);

    $response = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('ops-job-card__scan', false)
        ->assertSee('ops-job-card__status', false)
        ->assertDontSee('ops-job-card__status-menu', false)
        ->assertDontSee('aria-label="More actions"', false)
        ->assertDontSee('Customer hub', false);

    $builderUrl = route('operations.repair-orders.show', $repairOrder).'#builder';
    $customerHubUrl = route('operations.customers.show', $repairOrder->customer_id);
    $commsUrl = $customerHubUrl.'?compose=text#customer-communication';
    $html = $response->getContent();
    $cardStart = strpos($html, 'id="ops-card-ro-'.$repairOrder->repair_order_id.'"');
    $cardHtml = $cardStart === false ? '' : substr($html, $cardStart, 8000);
    $roPos = strpos($cardHtml, 'ops-job-card__ro');
    $vehiclePos = strpos($cardHtml, 'ops-job-card__vehicle');
    $customerPos = strpos($cardHtml, 'ops-job-card__customer-link');
    $statusPos = strpos($cardHtml, 'ops-job-card__status');
    $activityPos = strpos($cardHtml, 'ops-job-card__activity');
    $exceptionPos = strpos($cardHtml, 'ops-job-card__exception');

    expect($cardHtml)->toContain($builderUrl)
        ->and($cardHtml)->toContain('href="'.e($customerHubUrl).'"')
        ->and($cardHtml)->not->toContain('href="'.e($commsUrl).'"')
        ->and($cardHtml)->toContain('ops-job-card__customer-link')
        ->and($roPos)->not->toBeFalse()
        ->and($vehiclePos)->not->toBeFalse()
        ->and($customerPos)->not->toBeFalse()
        ->and($statusPos)->not->toBeFalse()
        ->and($activityPos)->not->toBeFalse()
        ->and($exceptionPos)->not->toBeFalse()
        ->and($roPos)->toBeLessThan($exceptionPos)
        ->and($exceptionPos)->toBeLessThan($vehiclePos)
        ->and($vehiclePos)->toBeLessThan($customerPos)
        ->and($customerPos)->toBeLessThan($statusPos)
        ->and($statusPos)->toBeLessThan($activityPos);
});

test('home card surfaces promise time on the meta row', function () {
    Carbon::setTestNow('2026-06-19 14:00:00');

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Promise',
        lastName: 'Customer',
        status: RepairOrderStatus::InProgress,
        lineCents: 180_000,
    );

    OperationalCommitment::query()->create([
        'repair_order_id' => $repairOrder->id,
        'owner_user_id' => actingAsLearnCurrentAdvisor()->id,
        'created_by' => actingAsLearnCurrentAdvisor()->id,
        'type' => CommitmentType::CustomerUpdate,
        'status' => CommitmentStatus::Open,
        'reason' => 'Call with update before close',
        'due_at' => now()->addHours(2),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Promise Customer', false)
        ->assertSee('ops-job-card__exception', false)
        ->assertDontSee('Promise overdue', false);

    Carbon::setTestNow();
});

test('home card plus-count names the other attention items', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-08-24 09:00')->utc());

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Gary',
        lastName: 'Baca',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 180_100,
        disposition: RepairOrderConcernDisposition::Approved,
    );

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $repairOrder->concerns()->first()?->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Axle seal',
        'quantity' => '1.00',
        'unit_price_cents' => 9800,
        'part_cost_cents' => 5300,
        'procurement_state' => PartProcurementState::Backordered,
        'subtotal_cents' => 9800,
        'total_cents' => 9800,
    ]);

    Appointment::query()->create([
        'customer_id' => $repairOrder->customer_id,
        'vehicle_id' => $repairOrder->vehicle_id,
        'repair_order_id' => $repairOrder->id,
        'created_by_user_id' => actingAsLearnCurrentAdvisor()->id,
        'advisor_user_id' => actingAsLearnCurrentAdvisor()->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-08-23 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-08-23 10:00')->utc(),
        'concern' => 'Comeback',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Missed appointment', false)
        ->assertSee('+1', false)
        ->assertSee('Also: Needs parts', false)
        ->assertSee('ops-job-card__mark--attention', false)
        ->assertSee('ops-job-card__mark-badge--alert', false)
        ->assertSee('ops-job-card__mark--ready', false)
        ->assertSee('ops-job-card__exception-list', false)
        ->assertSee('ops-job-card__exception-list-item', false);

    Carbon::setTestNow();
});

