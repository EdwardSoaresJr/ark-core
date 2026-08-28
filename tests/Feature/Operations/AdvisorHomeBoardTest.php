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

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Active Cars', false)
        ->assertDontSee('Biggest Pending', false)
        ->assertDontSee('ops-advisor-home-cockpit', false)
        ->assertSee('Estimates', false)
        ->assertSee('Work in Progress', false)
        ->assertSee('Completed', false)
        ->assertSee('Search job board', false)
        ->assertDontSee('+ Create Repair Order', false)
        ->assertSee('John Smith', false)
        ->assertSee('2018 Ram 2500', false)
        ->assertSee('Waiting Approval', false)
        ->assertSee('ops-job-card__chip--warn', false)
        ->assertSee('Brake noise when stopping', false)
        ->assertSee('Follow up', false)
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
        ->assertSee('Estimates', false);

    preg_match('/id="ops-home-col-waiting_approval"(.*?)id="ops-home-col-parts"/s', $response->getContent(), $matches);
    expect($matches[1] ?? '')->toContain('ops-card-ro-'.$hot->repair_order_id);

    Carbon::setTestNow();
});

test('home card menu offers status moves and patches lifecycle from the board', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Board',
        lastName: 'Move',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 90_000,
    );

    $advisor = actingAsLearnCurrentAdvisor();

    expect(app(\App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog::class)
        ->allowedTargetSlugs(RepairOrderStatus::WaitingApproval->value, $advisor))
        ->toBe(['estimate', 'approved']);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Move to', false)
        ->assertSee(route('operations.repair-orders.lifecycle.update', $repairOrder), false)
        ->assertSee('Building Estimate', false)
        ->assertSee('Closed — Paid', false)
        ->assertSee('Closed — Lost', false)
        ->assertSee('lifecycle=closed%3Alost', false)
        ->assertDontSee('Sort by: Pressure', false);

    $this->from(route('operations.index'))
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::Estimate->value,
        ])
        ->assertRedirect(route('operations.index'));

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Estimate))->toBeTrue();
});

test('waiting approval cards surface waiting approval status chip', function () {
    decisionPressureRepairOrder(
        firstName: 'Auth',
        lastName: 'Chip',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 250_000,
    );

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Auth Chip', false)
        ->assertSee('Waiting Approval', false)
        ->assertSee('ops-job-card__chip--warn', false);
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
        ->assertSee('Building Estimate', false)
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
        ->assertSee('RO created', false);
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
        ->assertSee('hrs complete', false)
        ->assertSee('100%', false);
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
        ->assertSee('Appointment · Today 2:00 PM', false)
        ->assertSee('ops-job-card__schedule', false);

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
        ->assertSee('Appointment · Tomorrow 9:00 AM', false);

    Carbon::setTestNow();
});

test('home card puts RO identity left and status chip right without a more menu', function () {
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
        ->assertSee('ops-job-card__identity', false)
        ->assertSee('ops-job-card__status-menu', false)
        ->assertDontSee('aria-label="More actions"', false)
        ->assertDontSee('Customer hub', false);

    $builderUrl = route('operations.repair-orders.show', $repairOrder).'#builder';
    $customerHubUrl = route('operations.customers.show', $repairOrder->customer_id);
    $commsUrl = $customerHubUrl.'?compose=text#customer-communication';
    $html = $response->getContent();
    $cardStart = strpos($html, 'id="ops-card-ro-'.$repairOrder->repair_order_id.'"');
    $cardHtml = $cardStart === false ? '' : substr($html, $cardStart, 8000);
    $roPos = strpos($cardHtml, 'ops-job-card__ro');
    $statusPos = strpos($cardHtml, 'ops-job-card__status-menu');

    expect($cardHtml)->toContain($builderUrl)
        ->and($cardHtml)->toContain('href="'.e($customerHubUrl).'"')
        ->and($cardHtml)->toContain('href="'.e($commsUrl).'"')
        ->and($cardHtml)->toContain('ops-job-card__customer-link')
        ->and($roPos)->not->toBeFalse()
        ->and($statusPos)->not->toBeFalse()
        ->and($roPos)->toBeLessThan($statusPos);
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
        ->assertSee('Due ', false);

    Carbon::setTestNow();
});

