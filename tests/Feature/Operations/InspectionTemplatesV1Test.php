<?php

use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\AssignRepairOrderInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionCoverageProjection;
use App\Ark\Operations\Inspections\InspectionPointCompletion;
use App\Ark\Operations\Inspections\InspectionTemplate;
use App\Ark\Operations\Inspections\InspectionTemplateItem;
use App\Ark\Operations\Inspections\InspectionTemplateSlugs;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    DefaultInspectionTemplateCatalog::rebuildStandardCornerInspectionV1();
});

function templatesV1RepairOrder(?User $technician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Patron',
        'phone' => '5550100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $standard = DefaultInspectionTemplateCatalog::standardTemplate();

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'assigned_technician_id' => $technician?->id,
        'required_inspection_template_id' => $standard?->id,
        'concern_summary' => 'Brakes',
        'opened_at' => now(),
    ]);
}

test('seed creates Standard default and PPI; archives GVI', function () {
    InspectionTemplate::query()->create([
        'name' => 'General Vehicle Inspection',
        'enabled' => true,
        'is_default' => true,
        'position' => 0,
    ]);

    $standard = DefaultInspectionTemplateCatalog::seedIfMissing();
    $ppi = DefaultInspectionTemplateCatalog::ppiTemplate();
    $legacy = InspectionTemplate::query()->where('slug', InspectionTemplateSlugs::GENERAL_LEGACY)->first();

    expect($standard->slug)->toBe(InspectionTemplateSlugs::STANDARD)
        ->and($standard->is_default)->toBeTrue()
        ->and($standard->enabled)->toBeTrue()
        ->and($ppi)->not->toBeNull()
        ->and($ppi->slug)->toBe(InspectionTemplateSlugs::PPI)
        ->and($ppi->is_default)->toBeFalse()
        ->and($legacy)->not->toBeNull()
        ->and($legacy->enabled)->toBeFalse()
        ->and($legacy->is_default)->toBeFalse()
        ->and($legacy->archived_at)->not->toBeNull();

    $second = DefaultInspectionTemplateCatalog::seedIfMissing();
    expect($second->id)->toBe($standard->id)
        ->and(InspectionTemplate::query()->where('slug', InspectionTemplateSlugs::STANDARD)->count())->toBe(1);
});

test('apply uses RO required template and one inspection per RO', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = templatesV1RepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    expect($inspection->fresh()->inspection_template_id)->toBe(
        DefaultInspectionTemplateCatalog::standardTemplate()->id,
    )->and(Inspection::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1)
        ->and($inspection->fresh()->items()->where('label', 'LF Tire')->exists())->toBeTrue();
});

test('Good alone does not address tire SM point', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = templatesV1RepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $item = $inspection->items()->where('label', 'LF Tire')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->find($item->inspection_template_item_id);

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    expect(InspectionPointCompletion::isAddressed($item->fresh(), $templateItem))->toBeFalse();

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item->fresh(),
        status: InspectionChecklistStatus::Good,
        actor: $technician,
        measurements: [
            ['key' => 'outer', 'name' => 'Outer', 'value' => '6', 'unit' => '/32"'],
            ['key' => 'center', 'name' => 'Center', 'value' => '6', 'unit' => '/32"'],
            ['key' => 'inner', 'name' => 'Inner', 'value' => '6', 'unit' => '/32"'],
            ['key' => 'psi', 'name' => 'Pressure', 'value' => '32', 'unit' => 'PSI'],
        ],
    );

    expect(InspectionPointCompletion::isAddressed($item->fresh(), $templateItem))->toBeTrue();
});

test('advisor can select PPI which replaces Standard before evidence', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = templatesV1RepairOrder();
    $ppi = DefaultInspectionTemplateCatalog::ppiTemplate();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.inspection.template.assign', $repairOrder), [
            'inspection_template_id' => $ppi->id,
        ])
        ->assertRedirect();

    expect($repairOrder->fresh()->required_inspection_template_id)->toBe($ppi->id);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder->fresh(), $advisor);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder->fresh(), $inspection, actor: $advisor);

    expect($inspection->fresh()->inspection_template_id)->toBe($ppi->id)
        ->and($inspection->items()->where('label', 'Scan — stored codes')->exists())->toBeTrue()
        ->and(Inspection::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);

    $coverage = InspectionCoverageProjection::for($repairOrder->fresh(), $advisor);
    expect($coverage['template_name'])->toBe('Pre-Purchase Inspection');
});

test('template switch after captured evidence is refused without confirmation', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = templatesV1RepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $item = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    $ppi = DefaultInspectionTemplateCatalog::ppiTemplate();

    expect(fn () => app(AssignRepairOrderInspectionTemplateAction::class)->execute($repairOrder->fresh(), $ppi))
        ->toThrow(DomainException::class);

    expect($repairOrder->fresh()->required_inspection_template_id)
        ->toBe(DefaultInspectionTemplateCatalog::standardTemplate()->id)
        ->and($item->fresh()->superseded_at)->toBeNull();
});

test('confirmed wrong-template correction keeps history and seeds the new checklist', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = templatesV1RepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $item = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    $standardId = DefaultInspectionTemplateCatalog::standardTemplate()->id;
    $ppi = DefaultInspectionTemplateCatalog::ppiTemplate();
    $beforeItemCount = $inspection->items()->count();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.inspection.template.assign', $repairOrder), [
            'inspection_template_id' => $ppi->id,
            'confirm_template_change' => '1',
            'template_correction_reason' => AssignRepairOrderInspectionTemplateAction::REASON_WRONG_TEMPLATE,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $inspection->refresh();
    $item->refresh();

    expect($repairOrder->fresh()->required_inspection_template_id)->toBe($ppi->id)
        ->and($inspection->inspection_template_id)->toBe($ppi->id)
        ->and($inspection->previous_inspection_template_id)->toBe($standardId)
        ->and($inspection->template_correction_reason)->toBe(AssignRepairOrderInspectionTemplateAction::REASON_WRONG_TEMPLATE)
        ->and($inspection->template_corrected_at)->not->toBeNull()
        ->and($item->superseded_at)->not->toBeNull()
        ->and($inspection->items()->whereNotNull('superseded_at')->count())->toBe($beforeItemCount)
        ->and($inspection->items()->whereNull('superseded_at')->where('label', 'Scan — stored codes')->exists())->toBeTrue()
        ->and(Inspection::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);

    $coverage = InspectionCoverageProjection::for($repairOrder->fresh(), $advisor);
    expect($coverage['template_name'])->toBe('Pre-Purchase Inspection')
        ->and($coverage['retained_history_count'])->toBe($beforeItemCount)
        ->and($coverage['previous_template_name'])->toBe('Standard Vehicle Inspection')
        ->and($coverage['has_captured_evidence'])->toBeFalse();
});

test('rear axle disc choice reveals disc points and brake delta prompts', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = templatesV1RepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $axle = $inspection->items()->where('label', 'Rear axle brake type')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $axle,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
        rearAxleBrakeType: Inspection::REAR_AXLE_DISC,
    );

    expect($inspection->fresh()->rear_axle_brake_type)->toBe('disc')
        ->and($inspection->fresh()->items()->where('label', 'LF Brake pads')->exists())->toBeTrue();

    $lf = $inspection->items()->where('label', 'LF Brake pads')->firstOrFail();
    $result = app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection->fresh(),
        item: $lf,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
        measurements: [
            ['key' => 'inner', 'name' => 'Inner pad', 'value' => '8', 'unit' => 'mm'],
            ['key' => 'outer', 'name' => 'Outer pad', 'value' => '4', 'unit' => 'mm'],
        ],
    );

    expect($result['brake_prompts'])->not->toBeEmpty()
        ->and($result['brake_prompts'][0]['message'])->toContain('Pad wear differs');
});

test('road test findings lock until performed and force N/A when performed is N/A', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = templatesV1RepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $finding = $inspection->items()
        ->where('label', 'Road-test noise / vibration / drivability observation')
        ->firstOrFail();

    $this->actingAs($technician)
        ->patchJson(route('operations.repair-orders.inspection.points.update', [$repairOrder, $finding]), [
            'status' => InspectionChecklistStatus::Good->value,
        ])
        ->assertStatus(422);

    $performed = $inspection->items()->where('label', 'Road test performed')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $performed,
        status: InspectionChecklistStatus::Na,
        actor: $technician,
    );

    $this->actingAs($technician)
        ->patchJson(route('operations.repair-orders.inspection.points.update', [$repairOrder, $finding]), [
            'status' => InspectionChecklistStatus::Good->value,
        ])
        ->assertStatus(422);

    $this->actingAs($technician)
        ->patchJson(route('operations.repair-orders.inspection.points.update', [$repairOrder, $finding]), [
            'status' => InspectionChecklistStatus::Na->value,
        ])
        ->assertOk();
});

test('builder shows Standard and PPI selection', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = templatesV1RepairOrder();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-inspection-template-select', false)
        ->assertSee('Standard', false)
        ->assertSee('Pre-Purchase', false);
});
