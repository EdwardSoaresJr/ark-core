<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderOperationalCloseoutAcknowledgment;
use App\Ark\Operations\RepairOrders\RepairOrderOperationalOutstandingWork;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());
});

function operationalCloseoutRepairOrder(RepairOrderStatus $status = RepairOrderStatus::ReadyPickup): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Ops',
        'last_name' => 'Closeout',
        'phone' => '555-0199',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'OPS'.$customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Brake job',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake job',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'production_status' => ScopeProductionStatus::Completed,
        'recommendation_intent' => 'repair',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace pads',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return $repairOrder->fresh();
}

function operationalCloseoutPart(RepairOrder $repairOrder, PartProcurementState $state): RepairOrderLine
{
    $concern = $repairOrder->concerns()->firstOrFail();

    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8000,
        'subtotal_cents' => 8000,
        'total_cents' => 8000,
        'procurement_state' => $state,
    ]);
}

function startIncompleteInspection(RepairOrder $repairOrder): Inspection
{
    $actor = auth()->user();
    DefaultInspectionTemplateCatalog::seedIfMissing();
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $actor);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $actor);

    $item = $inspection->fresh()->items()->whereNotNull('inspection_template_item_id')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $actor,
    );

    expect($inspection->fresh()->items()->where('observed_state', InspectionObservedState::NotChecked->value)->exists())->toBeTrue();

    return $inspection->fresh();
}

test('never-started inspection does not require operational acknowledgment', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();

    $outstanding = app(RepairOrderOperationalOutstandingWork::class);
    $items = $outstanding->items($repairOrder);

    expect(collect($items)->where('kind', RepairOrderOperationalOutstandingWork::KIND_INSPECTION_IN_PROGRESS))->toBeEmpty();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Completed))->toBeTrue();
});

test('lifecycle select keeps unfinished-work moves clickable so the reason panel can open', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();
    operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);

    $hardBlock = app(\App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition::class)->blockingReason(
        $repairOrder->fresh(),
        RepairOrderStatus::Completed->value,
    );
    $selectBlock = app(\App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition::class)->blockingReason(
        $repairOrder->fresh(),
        RepairOrderStatus::Completed->value,
        includeOperationalCloseout: false,
    );

    expect($hardBlock)->toBe(RepairOrderOperationalCloseoutAcknowledgment::MISSING_REASON)
        ->and($selectBlock)->toBeNull();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('data-blocked-reason="'.RepairOrderOperationalCloseoutAcknowledgment::MISSING_REASON.'"', false)
        ->assertSee('data-status-value="completed"', false);
});

test('quality check does not require operational unfinished-work acknowledgment', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::InProgress);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();
    operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);
    $repairOrder->concerns()->firstOrFail()->forceFill([
        'production_status' => ScopeProductionStatus::InProgress,
    ])->save();

    $challenge = app(RepairOrderOperationalCloseoutAcknowledgment::class)->challenge(
        $repairOrder->fresh(),
        RepairOrderStatus::QualityCheck->value,
    );

    expect($challenge)->toBeNull();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::QualityCheck->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::QualityCheck))->toBeTrue();
});

test('mobile lifecycle API returns operational closeout challenge for unfinished work', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $token = $advisor->createToken('phone')->plainTextToken;
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();
    operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/status', [
            'status' => RepairOrderStatus::Completed->value,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', RepairOrderOperationalCloseoutAcknowledgment::MISSING_REASON)
        ->assertJsonStructure([
            'operational_closeout' => [
                'required',
                'fingerprint',
                'items',
                'message',
            ],
        ]);

    $challenge = app(RepairOrderOperationalCloseoutAcknowledgment::class)->challenge(
        $repairOrder->fresh(),
        RepairOrderStatus::Completed->value,
    );

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/status', [
            'status' => RepairOrderStatus::Completed->value,
            'operational_closeout_reason' => 'Customer waiting; pads arrive tomorrow morning.',
            'operational_closeout_fingerprint' => $challenge['fingerprint'],
        ])
        ->assertOk();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Completed))->toBeTrue()
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderOperationalCloseoutAcknowledged->value)
            ->count())->toBe(1);
});

test('partially completed inspection requires reason before completed', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();
    startIncompleteInspection($repairOrder);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle')
        ->assertSessionHas('operational_closeout_challenge');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::QualityCheck))->toBeTrue();

    $challenge = session('operational_closeout_challenge');
    $fingerprint = $challenge['fingerprint'] ?? '';

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
        'operational_closeout_reason' => 'Customer waiting; inspection finish postponed.',
        'operational_closeout_fingerprint' => $fingerprint,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Completed))->toBeTrue()
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderOperationalCloseoutAcknowledged->value)
            ->count())->toBe(1);

    $inspection = Inspection::query()->where('repair_order_id', $repairOrder->id)->first();
    expect($inspection?->completed_at)->toBeNull();
});

test('ordered approved part not received requires reason', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();
    operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionHasErrors('lifecycle');

    $challenge = app(RepairOrderOperationalCloseoutAcknowledgment::class)->challenge(
        $repairOrder->fresh(),
        RepairOrderStatus::Completed->value,
    );

    expect(collect($challenge['items'] ?? [])->pluck('kind')->all())
        ->toContain(RepairOrderOperationalOutstandingWork::KIND_PART_NOT_RECEIVED);
});

test('received approved part not installed requires reason', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    operationalCloseoutPart($repairOrder, PartProcurementState::Received);

    $challenge = app(RepairOrderOperationalCloseoutAcknowledgment::class)->challenge(
        $repairOrder->fresh(),
        RepairOrderStatus::ReadyPickup->value,
    );

    expect($challenge['required'] ?? false)->toBeTrue()
        ->and(collect($challenge['items'])->pluck('kind')->all())
        ->toContain(RepairOrderOperationalOutstandingWork::KIND_PART_NOT_INSTALLED);
});

test('approved labor incomplete requires reason', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();
    $repairOrder->concerns()->firstOrFail()->forceFill([
        'production_status' => ScopeProductionStatus::InProgress,
    ])->save();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionHasErrors('lifecycle');
});

test('deferred and declined concerns do not create unfinished labor items', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::InProgress);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Deferred alignment',
        'disposition' => RepairOrderConcernDisposition::Deferred,
        'production_status' => ScopeProductionStatus::Pending,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);
    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Declined detail',
        'disposition' => RepairOrderConcernDisposition::Declined,
        'production_status' => ScopeProductionStatus::Pending,
        'recommendation_intent' => 'maintenance',
        'position' => 3,
    ]);

    $items = app(RepairOrderOperationalOutstandingWork::class)->items($repairOrder->fresh());

    expect(collect($items)->where('kind', RepairOrderOperationalOutstandingWork::KIND_LABOR_INCOMPLETE))->toBeEmpty();
});

test('canceled part does not create unfinished procurement items', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::InProgress);
    operationalCloseoutPart($repairOrder, PartProcurementState::Canceled);

    $items = app(RepairOrderOperationalOutstandingWork::class)->items($repairOrder->fresh());

    expect(collect($items)->filter(fn ($item) => str_starts_with($item['kind'], 'part_')))->toBeEmpty();
});

test('missing reason is rejected and audit is not written', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();
    operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
        'operational_closeout_reason' => 'short',
    ])->assertRedirect()->assertSessionHasErrors('lifecycle');

    expect(OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderOperationalCloseoutAcknowledged->value)
        ->count())->toBe(0);
});

test('direct paid close API without reason is rejected when unfinished work remains', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);
    issueFinalInvoiceFor($repairOrder->fresh());
    payRepairOrderInFull($repairOrder->fresh());

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
    ])->assertRedirect()->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::ReadyPickup))->toBeTrue();
});

test('authorized override with reason closes paid and preserves unfinished procurement state', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $part = operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);
    issueFinalInvoiceFor($repairOrder->fresh());
    payRepairOrderInFull($repairOrder->fresh());

    $challenge = app(RepairOrderOperationalCloseoutAcknowledgment::class)->challenge(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed->value,
        'paid',
    );

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
        'operational_closeout_reason' => 'Customer requested release; pads arrive tomorrow.',
        'operational_closeout_fingerprint' => $challenge['fingerprint'],
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($part->fresh()->procurementState())->toBe(PartProcurementState::Ordered)
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderOperationalCloseoutAcknowledged->value)
            ->count())->toBe(1);
});

test('same outstanding fingerprint can reuse prior acknowledgment without a new reason', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();
    operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);

    $challenge = app(RepairOrderOperationalCloseoutAcknowledgment::class)->challenge(
        $repairOrder->fresh(),
        RepairOrderStatus::Completed->value,
    );

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
        'operational_closeout_reason' => 'Proceeding with parts still on order.',
        'operational_closeout_fingerprint' => $challenge['fingerprint'],
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => RepairOrderStatus::ReadyPickup->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::ReadyPickup))->toBeTrue()
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderOperationalCloseoutAcknowledged->value)
            ->count())->toBe(1);
});

test('changed outstanding items after acknowledgment require a renewed reason', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::QualityCheck);
    $repairOrder->forceFill(['mileage_in' => 10000, 'mileage_out' => 10001])->save();
    $part = operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);

    $challenge = app(RepairOrderOperationalCloseoutAcknowledgment::class)->challenge(
        $repairOrder->fresh(),
        RepairOrderStatus::Completed->value,
    );

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
        'operational_closeout_reason' => 'Proceeding with parts still on order.',
        'operational_closeout_fingerprint' => $challenge['fingerprint'],
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    $part->forceFill(['procurement_state' => PartProcurementState::Received])->save();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => RepairOrderStatus::ReadyPickup->value,
    ])->assertRedirect()->assertSessionHasErrors('lifecycle');
});

test('lost closeout remains available without operational unfinished-work acknowledgment', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::WaitingApproval);
    operationalCloseoutPart($repairOrder, PartProcurementState::Ordered);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:lost',
        'lost_reason_key' => 'price_declined',
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($repairOrder->fresh()->close_variant_key)->toBe('lost');
});

test('properly completed repair order closes paid without operational acknowledgment', function () {
    $repairOrder = operationalCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    operationalCloseoutPart($repairOrder, PartProcurementState::Installed);
    issueFinalInvoiceFor($repairOrder->fresh());
    payRepairOrderInFull($repairOrder->fresh());

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderOperationalCloseoutAcknowledged->value)
            ->count())->toBe(0);
});
