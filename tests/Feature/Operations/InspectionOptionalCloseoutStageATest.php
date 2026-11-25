<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\InspectionPosture;
use App\Ark\Operations\Inspections\InspectionPostureProjection;
use App\Ark\Operations\Inspections\InspectionRequirement;
use App\Ark\Operations\Inspections\InspectionRequirementResolver;
use App\Ark\Operations\Inspections\ResetInspectionWalkAction;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderOperationalOutstandingWork;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());
});

function stageACloseoutRo(RepairOrderStatus $status = RepairOrderStatus::QualityCheck): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Optional',
        'last_name' => 'Inspection',
        'phone' => '55501842',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Battery and brakes',
        'mileage_in' => 80000,
        'mileage_out' => 80001,
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Battery and brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'production_status' => ScopeProductionStatus::Completed,
        'recommendation_intent' => 'repair',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnose',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return $repairOrder->fresh();
}

function stageASeedWalk(RepairOrder $repairOrder): void
{
    $actor = auth()->user();
    DefaultInspectionTemplateCatalog::seedIfMissing();
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $actor);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $actor);
}

test('shop default inspection requirement is optional', function () {
    $repairOrder = stageACloseoutRo();
    $decision = app(InspectionRequirementResolver::class)->forRepairOrder($repairOrder);

    expect($decision->requirement)->toBe(InspectionRequirement::Optional)
        ->and($decision->blocksLifecycleOnIncomplete())->toBeFalse();
});

test('optional never-started inspection does not block completed', function () {
    $repairOrder = stageACloseoutRo();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Completed))->toBeTrue();
});

test('optional partial checklist does not block completed or ready pickup', function () {
    $repairOrder = stageACloseoutRo();
    stageASeedWalk($repairOrder);
    $inspection = \App\Ark\Operations\Inspections\Inspection::query()
        ->where('repair_order_id', $repairOrder->id)
        ->firstOrFail();
    $item = $inspection->items()->whereNotNull('inspection_template_item_id')->firstOrFail();

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: auth()->user(),
    );

    expect(collect(app(RepairOrderOperationalOutstandingWork::class)->items($repairOrder->fresh()))
        ->where('kind', RepairOrderOperationalOutstandingWork::KIND_INSPECTION_IN_PROGRESS))->toBeEmpty();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');
});

test('optional reset walk with freeform finding does not block closeout (RO1842 shape)', function () {
    $advisor = auth()->user();
    $repairOrder = stageACloseoutRo();
    stageASeedWalk($repairOrder);
    $inspection = \App\Ark\Operations\Inspections\Inspection::query()
        ->where('repair_order_id', $repairOrder->id)
        ->firstOrFail();

    $templateItem = $inspection->items()->where('label', 'Wipers / washer')->first()
        ?? $inspection->items()->whereNotNull('inspection_template_item_id')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $templateItem,
        status: InspectionChecklistStatus::Good,
        actor: $advisor,
    );

    // Freeform Other Finding (no template point) — same shape as Bad Battery on RO #1842.
    InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => 'battery',
        'checklist_category_name' => 'Battery',
        'label' => 'Bad Battery',
        'observed_state' => InspectionObservedState::Measure->value,
        'notes' => 'Load test failed',
        'position' => 999,
        'inspection_template_item_id' => null,
    ]);

    app(ResetInspectionWalkAction::class)->execute($repairOrder, $inspection->fresh(), $advisor);

    $posture = app(InspectionPostureProjection::class)->forRepairOrder($repairOrder->fresh());
    expect($posture->started)->toBeFalse()
        ->and($posture->key)->toBe(InspectionPosture::NOT_STARTED)
        ->and($inspection->fresh()->items()->whereNull('superseded_at')->whereNull('inspection_template_item_id')->where('label', 'Bad Battery')->exists())
        ->toBeTrue()
        ->and($inspection->fresh()->items()->whereNull('superseded_at')->whereNotNull('inspection_template_item_id')
            ->where('observed_state', '!=', InspectionObservedState::NotChecked->value)->count())->toBe(0)
        ->and($inspection->fresh()->items()->whereNotNull('superseded_at')->whereNotNull('inspection_template_item_id')->count())
        ->toBeGreaterThan(0);

    expect(collect(app(RepairOrderOperationalOutstandingWork::class)->items($repairOrder->fresh()))
        ->where('kind', RepairOrderOperationalOutstandingWork::KIND_INSPECTION_IN_PROGRESS))->toBeEmpty();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Completed))->toBeTrue();
});

test('recommended incomplete inspection does not block closeout', function () {
    ShopSettings::current()->persistTrusted([
        'inspection_lifecycle_policy' => InspectionRequirement::Recommended->value,
    ]);

    $repairOrder = stageACloseoutRo();
    stageASeedWalk($repairOrder);
    $inspection = \App\Ark\Operations\Inspections\Inspection::query()
        ->where('repair_order_id', $repairOrder->id)->firstOrFail();
    $item = $inspection->items()->whereNotNull('inspection_template_item_id')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: auth()->user(),
    );

    expect(app(InspectionRequirementResolver::class)->forRepairOrder($repairOrder)->requirement)
        ->toBe(InspectionRequirement::Recommended);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::ReadyPickup->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');
});

test('required never-started inspection blocks completed until acknowledged', function () {
    $repairOrder = stageACloseoutRo();
    $repairOrder->forceFill([
        'inspection_lifecycle_policy' => InspectionRequirement::Required->value,
    ])->save();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle')
        ->assertSessionHas('operational_closeout_challenge');
});

test('required waived inspection does not block closeout', function () {
    $repairOrder = stageACloseoutRo();
    $repairOrder->forceFill([
        'inspection_lifecycle_policy' => InspectionRequirement::Required->value,
        'inspection_waived_at' => now(),
        'inspection_waived_by' => auth()->id(),
        'inspection_waived_reason' => 'Customer declined inspection; proceed.',
    ])->save();

    expect(app(InspectionRequirementResolver::class)->forRepairOrder($repairOrder)->blocksLifecycleOnIncomplete())
        ->toBeFalse();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');
});

test('shop required policy applies when RO has no override', function () {
    ShopSettings::current()->persistTrusted([
        'inspection_lifecycle_policy' => InspectionRequirement::Required->value,
    ]);

    $repairOrder = stageACloseoutRo();
    $decision = app(InspectionRequirementResolver::class)->forRepairOrder($repairOrder);

    expect($decision->requirement)->toBe(InspectionRequirement::Required)
        ->and($decision->source)->toBe('shop_setting')
        ->and($decision->blocksLifecycleOnIncomplete())->toBeTrue();
});
