<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('repair order lifecycle exposes only active pressure states', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    expect(RepairOrderStatus::Closed->isTerminal())->toBeTrue()
        ->and(RepairOrderStatus::Draft->isTerminal())->toBeFalse();

    $advisor = actingAsLearnCurrentAdvisor();

    expect(RepairOrderStatus::ReadyPickup->allowedOperationalTransitionSlugs($advisor))
        ->toContain(RepairOrderStatus::Invoiced->value);
});

test('lifecycle update supports worksheet ajax refresh on the estimate builder', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::WaitingApproval);
    lineForLifecycleQueue($repairOrder);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->followingRedirects()
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::Approved->value,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])
        ->assertOk()
        ->assertSee('id="review-toolbar"', false)
        ->assertSee('id="ro-orientation-header"', false)
        ->assertSee('data-ro-dock-status', false)
        ->assertSee('data-current-status="'.RepairOrderStatus::Approved->value.'"', false);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Approved))->toBeTrue();
});

test('advisors can move repair orders through humane lifecycle pressure states', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::WaitingApproval);
    lineForLifecycleQueue($repairOrder);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Approved->value,
    ])->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Approved))->toBeTrue();

    $event = OperationalEvent::query()->sole();

    expect($event->event_name)->toBe(OperationalEventName::RepairOrderLifecycleChanged->value)
        ->and($event->aggregate_type)->toBe(RepairOrder::class)
        ->and($event->aggregate_id)->toBe($repairOrder->id)
        ->and($event->actor_user_id)->toBe($advisor->id)
        ->and($event->payload_json['from_status'])->toBe(RepairOrderStatus::WaitingApproval->value)
        ->and($event->payload_json['to_status'])->toBe(RepairOrderStatus::Approved->value);
});

test('lifecycle authority snapshots terminal closeout and rejects drift', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $this->app->bind(PdfRenderer::class, FakeLifecyclePdfRenderer::class);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyPickup);
    $line = lineForLifecycleQueue($repairOrder);
    $line->concern->forceFill(['disposition' => RepairOrderConcernDisposition::Approved])->save();
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    issueFinalInvoiceFor($repairOrder->fresh());
    payRepairOrderInFull($repairOrder->fresh());

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
        'review_request_sent' => '1',
    ])->assertRedirect();

    $document = EstimateDocument::query()->where('document_type', 'estimate')->sole();
    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
        ->sole();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($document->needs_pdf_refresh)->toBeFalse()
        ->and($document->snapshot_json['repair_order']['status'])->toBe(RepairOrderStatus::Closed->value)
        ->and($event->payload_json)->toMatchArray([
            'from_status' => RepairOrderStatus::ReadyPickup->value,
            'to_status' => RepairOrderStatus::Closed->value,
        ]);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
            ->count())->toBe(1);
});

test('ready pickup requires payment before terminal closeout', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyPickup, customerName: 'Balance Due');
    $line = lineForLifecycleQueue($repairOrder);
    $line->concern->forceFill(['disposition' => RepairOrderConcernDisposition::Approved])->save();
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Balance Due')
        ->assertSee('ops-home-col-completed', false);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Balance due at pickup')
        ->assertSee('Collect balance before releasing vehicle')
        ->assertSee('Generate Final Invoice')
        ->assertSee('Generate the final invoice before closing this repair order')
        ->assertDontSee('Record Payment');

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Closed->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::ReadyPickup))->toBeTrue()
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
            ->count())->toBe(0);
});

test('technician assignment is lightweight server-owned execution context', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $technician->forceFill(['name' => 'Alex Tech'])->save();
    $this->actingAs($advisor);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyForWork, customerName: 'Execution Customer');
    lineForLifecycleQueue($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Unassigned')
        ->assertSee('Execution Customer');

    $this->patch(route('operations.repair-orders.technician-assignment.update', $repairOrder), [
        'assigned_technician_id' => $technician->id,
    ])->assertRedirect();

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderTechnicianAssigned->value)
        ->sole();

    expect($repairOrder->fresh()->assigned_technician_id)->toBe($technician->id)
        ->and($event->payload_json)->toMatchArray([
            'from_technician_id' => null,
            'to_technician_id' => $technician->id,
            'to_technician_name' => 'Alex Tech',
        ]);

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Alex Tech');
});

test('work cannot start without assigned technician and rejects non technician assignment', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $notTechnician = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyForWork);
    lineForLifecycleQueue($repairOrder);

    $this->patchJson(route('operations.repair-orders.technician-assignment.update', $repairOrder), [
        'assigned_technician_id' => $notTechnician->id,
    ])->assertStatus(422);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::ReadyForWork))->toBeTrue()
        ->and($repairOrder->fresh()->assigned_technician_id)->toBeNull()
        ->and(OperationalEvent::query()->count())->toBe(0);
});

test('solo owner shop without advisor staff can start work without technician assignment', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $owner = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($owner);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyForWork);
    lineForLifecycleQueue($repairOrder);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::InProgress))->toBeTrue();
});

test('solo owner shop can assign admin owner as technician', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $owner = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($owner);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyForWork);
    lineForLifecycleQueue($repairOrder);

    $this->patch(route('operations.repair-orders.technician-assignment.update', $repairOrder), [
        'assigned_technician_id' => $owner->id,
    ])->assertRedirect();

    expect($repairOrder->fresh()->assigned_technician_id)->toBe($owner->id);
});

test('technician assignment cannot be cleared while work is in progress', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $technician->forceFill(['name' => 'Locked Tech'])->save();
    $this->actingAs($advisor);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyForWork);
    lineForLifecycleQueue($repairOrder);

    $this->patch(route('operations.repair-orders.technician-assignment.update', $repairOrder), [
        'assigned_technician_id' => $technician->id,
    ])->assertRedirect();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect();

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.technician-assignment.update', $repairOrder->fresh()), [
            'assigned_technician_id' => '',
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHasErrors('assigned_technician_id');

    expect($repairOrder->fresh())
        ->status->is(RepairOrderStatus::InProgress)->toBeTrue()
        ->and($repairOrder->fresh()->assigned_technician_id)->toBe($technician->id);
});

test('assigned work can move into progress and snapshot execution posture', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $technician->forceFill(['name' => 'Jordan Tech'])->save();
    $this->actingAs($advisor);

    $this->app->bind(PdfRenderer::class, FakeLifecyclePdfRenderer::class);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyForWork);
    $line = lineForLifecycleQueue($repairOrder);

    RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'title' => 'Lifecycle work',
        'position' => 1,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $technician->id,
    ]);

    $this->patch(route('operations.repair-orders.technician-assignment.update', $repairOrder), [
        'assigned_technician_id' => $technician->id,
    ])->assertRedirect();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::InProgress))->toBeTrue();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder->fresh()))
        ->assertRedirect();

    $document = EstimateDocument::query()->where('document_type', 'estimate')->sole();

    expect($document->snapshot_json['staff']['execution']['technician_name'])->toBe('Jordan Tech')
        ->and($document->snapshot_json['staff']['execution']['posture'])->toBe('In Progress')
        ->and($document->snapshot_json['staff']['execution']['next_action'])->toBe('Continue approved work');
});

test('paid ready pickup can close and leaves active queue', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $this->app->bind(PdfRenderer::class, FakeLifecyclePdfRenderer::class);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyPickup, customerName: 'Paid Pickup');
    $line = lineForLifecycleQueue($repairOrder);
    $line->concern->forceFill(['disposition' => RepairOrderConcernDisposition::Approved])->save();
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    issueFinalInvoiceFor($repairOrder->fresh());

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder), [
        'amount' => '150.00',
        'payment_method' => 'cash',
    ])->assertRedirect();

    $paymentEvent = OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderPaymentReceived->value)
        ->sole();

    expect($repairOrder->fresh()->isPaid())->toBeTrue()
        ->and($repairOrder->fresh()->paid_at)->not->toBeNull()
        ->and($paymentEvent->payload_json)->toMatchArray([
            'event_contract' => 'payment_received',
            'balance_due_cents' => 0,
            'payment_status' => 'paid',
        ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Paid / ready to close')
        ->assertSee('Eligible to close');

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => 'closed:paid',
        'review_request_sent' => '1',
    ])->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue();

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Paid Pickup');
});

test('adding the first estimate line records the automatic draft to estimate lifecycle event', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::Draft);
    $concern = concernForLifecycleQueue($repairOrder);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price' => '150.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Estimate))->toBeTrue();

    $lifecycleEvent = OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
        ->sole();

    expect($lifecycleEvent->aggregate_type)->toBe(RepairOrder::class)
        ->and($lifecycleEvent->aggregate_id)->toBe($repairOrder->id)
        ->and($lifecycleEvent->actor_user_id)->toBe($advisor->id)
        ->and($lifecycleEvent->payload_json)->toMatchArray([
            'from_status' => RepairOrderStatus::Draft->value,
            'to_status' => RepairOrderStatus::Estimate->value,
        ]);
});

test('lifecycle transitions reject unavailable jumps and empty forward movement', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::Draft);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Approved->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Draft))->toBeTrue()
        ->and(OperationalEvent::query()->count())->toBe(0);

    $repairOrder->update(['status' => RepairOrderStatus::WaitingApproval]);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Approved->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue()
        ->and(OperationalEvent::query()->count())->toBe(0);
});

test('terminal repair orders cannot be moved through lifecycle controls', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $repairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::Closed);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and(OperationalEvent::query()->count())->toBe(0);
});

test('operations home groups repair orders by lifecycle pressure', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    repairOrderForLifecycleQueue(status: RepairOrderStatus::WaitingApproval, customerName: 'Approval Customer');
    repairOrderForLifecycleQueue(status: RepairOrderStatus::WaitingParts, customerName: 'Parts Customer');
    repairOrderForLifecycleQueue(status: RepairOrderStatus::ReadyPickup, customerName: 'Pickup Customer');
    repairOrderForLifecycleQueue(status: RepairOrderStatus::Closed, customerName: 'Closed Customer');

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Estimates', false)
        ->assertSee('Waiting Approval', false)
        ->assertSee('Waiting Parts', false)
        ->assertSee('Work in Progress', false)
        ->assertSee('Completed', false)
        ->assertSee('Approval Customer', false)
        ->assertSee('Parts Customer', false)
        ->assertSee('Pickup Customer', false)
        ->assertDontSee('Closed Customer', false);
});

test('repair order index includes terminal repair orders excluded from the live queue', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    repairOrderForLifecycleQueue(status: RepairOrderStatus::Closed, customerName: 'Archive Customer');

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Archive Customer');

    $this->get(route('operations.repair-orders.index'))
        ->assertOk()
        ->assertSee('Repair Orders')
        ->assertSee('Archive Customer')
        ->assertSee('Closed');
});

test('repair order index searches retrieval fields and paginates results', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $matched = repairOrderForLifecycleQueue(status: RepairOrderStatus::Closed, customerName: 'Lookup Customer');
    $matched->customer->update(['phone' => '7195554411']);
    $matched->vehicle->update([
        'vin' => '1HGCM82633A004352',
        'normalized_vin' => '1HGCM82633A004352',
        'plate' => 'FIND441',
    ]);

    foreach (range(1, 24) as $index) {
        repairOrderForLifecycleQueue(status: RepairOrderStatus::Draft, customerName: 'Paged '.$index);
    }

    $this->get(route('operations.repair-orders.index', ['q' => '7195554411']))
        ->assertOk()
        ->assertSee('Lookup Customer')
        ->assertDontSee('Paged 1');

    $this->get(route('operations.repair-orders.index', ['q' => '1HGCM82633A004352']))
        ->assertOk()
        ->assertSee('Lookup Customer');

    $this->get(route('operations.repair-orders.index'))
        ->assertOk()
        ->assertSee('Pagination Navigation');
});

test('operations home survives dense active queue without full estimate graph dependency', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    foreach (range(1, 45) as $index) {
        $repairOrder = repairOrderForLifecycleQueue(
            status: $index % 3 === 0 ? RepairOrderStatus::WaitingApproval : RepairOrderStatus::InProgress,
            customerName: 'Dense '.$index,
        );

        lineForLifecycleQueue($repairOrder);
    }

    $blockedRepairOrder = repairOrderForLifecycleQueue(status: RepairOrderStatus::Approved, customerName: 'Blocked Parts');
    $approvedConcern = concernForLifecycleQueue($blockedRepairOrder, RepairOrderConcernDisposition::Approved);

    RepairOrderLine::query()->create([
        'repair_order_id' => $blockedRepairOrder->id,
        'repair_order_concern_id' => $approvedConcern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Backordered module',
        'quantity' => '1.00',
        'unit_price_cents' => 25000,
        'subtotal_cents' => 25000,
        'total_cents' => 25000,
        'procurement_state' => PartProcurementState::Backordered,
    ]);

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Blocked Parts', false)
        ->assertSee('Waiting Parts', false)
        ->assertSee('Dense 45', false);
});

function repairOrderForLifecycleQueue(RepairOrderStatus $status, string $customerName = 'Lifecycle Customer'): RepairOrder
{
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');

    $customer = Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'LIFE'.$customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Customer states vehicle needs attention.',
    ]);
}

function lineForLifecycleQueue(RepairOrder $repairOrder): RepairOrderLine
{
    $concern = concernForLifecycleQueue($repairOrder);

    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);
}

function concernForLifecycleQueue(RepairOrder $repairOrder, RepairOrderConcernDisposition $disposition = RepairOrderConcernDisposition::Recommended): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Lifecycle work',
        'disposition' => $disposition,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);
}

class FakeLifecyclePdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        $path = 'estimates/lifecycle-'.$document->id.'.pdf';

        $document->forceFill([
            'status' => 'generated',
            'pdf_path' => $path,
            'generated_at' => now(),
        ])->save();

        return $path;
    }
}
