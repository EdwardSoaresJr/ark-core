<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\Inspections\InspectionPhysicalSectionMap;
use App\Ark\Operations\Inspections\InspectionPointCompletion;
use App\Ark\Operations\Inspections\InspectionReportProjection;
use App\Ark\Operations\Inspections\InspectionSectionWalkProjection;
use App\Ark\Operations\Inspections\InspectionTemplateItem;
use App\Ark\Operations\Inspections\InspectionWalkVisibility;
use App\Ark\Operations\Inspections\InspectionChecklistItems;
use App\Ark\Operations\Inspections\InspectionCoverageProjection;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\StoreInspectionFindingAction;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    DefaultInspectionTemplateCatalog::rebuildStandardCornerInspectionV1();
});

function phase1InspectionRepairOrder(?User $technician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Patron',
        'phone' => '5550199',
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
        'concern_summary' => 'Inspection',
        'opened_at' => now(),
    ]);
}

function phase1PreparedInspection(RepairOrder $repairOrder, User $technician): Inspection
{
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    return $inspection->fresh(['items']);
}

test('physical section map places standard categories into corner then lift and ground stages', function () {
    expect(InspectionPhysicalSectionMap::sectionKeyForCategory('Left Front'))->toBe('left_front')
        ->and(InspectionPhysicalSectionMap::sectionKeyForCategory('Left Rear'))->toBe('left_rear')
        ->and(InspectionPhysicalSectionMap::sectionKeyForCategory('Right Rear'))->toBe('right_rear')
        ->and(InspectionPhysicalSectionMap::sectionKeyForCategory('Right Front'))->toBe('right_front')
        ->and(InspectionPhysicalSectionMap::sectionKeyForCategory('Brake system'))->toBe('brake_system')
        ->and(InspectionPhysicalSectionMap::sectionKeyForCategory('Under vehicle'))->toBe('under_vehicle')
        ->and(InspectionPhysicalSectionMap::sectionKeyForCategory('Under hood'))->toBe('under_hood')
        ->and(InspectionPhysicalSectionMap::sectionKeyForCategory('Arrival / outside'))->toBe('exterior_safety')
        ->and(InspectionPhysicalSectionMap::sectionKeyForCategory('Road / operational'))->toBe('road_operational');
});

test('section walk groups visible points and hides unused rear brake path', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase1InspectionRepairOrder($technician);
    $inspection = phase1PreparedInspection($repairOrder, $technician);

    $inspection->forceFill(['rear_axle_brake_type' => Inspection::REAR_AXLE_DISC])->save();

    $ordered = InspectionWalkVisibility::visibleItems(
        $inspection,
        InspectionChecklistItems::orderedChecklistItems($inspection->fresh(['items'])),
    );
    $templateItems = InspectionTemplateItem::query()
        ->whereIn('id', $ordered->pluck('inspection_template_item_id')->filter()->all())
        ->get()
        ->keyBy('id');

    $sectionWalk = app(InspectionSectionWalkProjection::class)->for(
        $repairOrder,
        $inspection->fresh(),
        $ordered,
        $templateItems,
    );

    $sectionKeys = collect($sectionWalk['sections'])->pluck('key')->all();
    expect($sectionKeys)->toContain('left_front', 'left_rear', 'under_vehicle', 'under_hood', 'exterior_safety', 'road_operational');

    $allLabels = collect($sectionWalk['sections'])->flatMap(fn (array $s) => collect($s['points'])->pluck('label'))->all();
    expect($allLabels)->toContain('LF Brake pads')
        ->and($allLabels)->toContain('LR Brake pads')
        ->and($allLabels)->not->toContain('LR Drum brake')
        ->and($allLabels)->not->toContain('Left running board loose');

    $freeformCountBefore = $inspection->items()->whereNull('inspection_template_item_id')->count();
    expect($freeformCountBefore)->toBe(0);
});

test('section coverage states follow addressed authority including required measurements', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase1InspectionRepairOrder($technician);
    $inspection = phase1PreparedInspection($repairOrder, $technician);
    $inspection->forceFill(['rear_axle_brake_type' => Inspection::REAR_AXLE_DISC])->save();

    $ordered = InspectionWalkVisibility::visibleItems(
        $inspection,
        InspectionChecklistItems::orderedChecklistItems($inspection->fresh(['items.measurements'])),
    );
    $templateItems = InspectionTemplateItem::query()
        ->whereIn('id', $ordered->pluck('inspection_template_item_id')->filter()->all())
        ->get()
        ->keyBy('id');

    $sectionWalk = app(InspectionSectionWalkProjection::class)->for($repairOrder, $inspection, $ordered, $templateItems);
    $leftFront = collect($sectionWalk['sections'])->firstWhere('key', 'left_front');
    expect($leftFront['state'])->toBe('not_started');

    $wipers = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $wipers,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    $tire = $inspection->items()->where('label', 'LF Tire')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $tire,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
        measurements: [
            ['key' => 'outer', 'name' => 'Outer', 'value' => '6', 'unit' => null],
            ['key' => 'center', 'name' => 'Center', 'value' => '6', 'unit' => null],
            ['key' => 'inner', 'name' => 'Inner', 'value' => '6', 'unit' => null],
        ],
    );

    $tire->refresh()->load('measurements');
    $tpl = $templateItems->get($tire->inspection_template_item_id);
    expect(InspectionPointCompletion::isAddressed($tire, $tpl))->toBeFalse();

    $ordered = InspectionWalkVisibility::visibleItems(
        $inspection,
        InspectionChecklistItems::orderedChecklistItems($inspection->fresh(['items.measurements'])),
    );
    $sectionWalk = app(InspectionSectionWalkProjection::class)->for(
        $repairOrder,
        $inspection->fresh(),
        $ordered,
        $templateItems,
    );

    $exterior = collect($sectionWalk['sections'])->firstWhere('key', 'exterior_safety');
    expect($exterior['state'])->toBe('in_progress')
        ->and($exterior['addressed'])->toBeGreaterThan(0)
        ->and($exterior['addressed'])->toBeLessThan($exterior['total']);

    $leftFront = collect($sectionWalk['sections'])->firstWhere('key', 'left_front');
    $tireRow = collect($leftFront['points'])->firstWhere('label', 'LF Tire');
    expect($tireRow['addressed'])->toBeFalse()
        ->and($tireRow['missing_measurement_slots'])->toContain('Pressure')
        ->and(collect($tireRow['measurement_slots'])->firstWhere('key', 'psi')['unit'])->toBe('PSI');
});

test('section workspace is primary and deep point remains available', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase1InspectionRepairOrder($technician);
    phase1PreparedInspection($repairOrder, $technician);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-inspection-sections', false)
        ->assertSee('left_front', false)
        ->assertSee('corner_inspection', false)
        ->assertSee('on_the_ground', false)
        ->assertDontSee('ops-inspection-walk__title', false);

    $item = $repairOrder->inspection->items()->where('label', 'Wipers / washer')->firstOrFail();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', [
            'repairOrder' => $repairOrder,
            'point' => $item->id,
        ]))
        ->assertOk()
        ->assertSee('ops-inspection-walk', false)
        ->assertSee('Back to sections', false)
        ->assertSee('Wipers / washer', false);
});

test('condition update from section endpoint does not mutate siblings and keeps units', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase1InspectionRepairOrder($technician);
    $inspection = phase1PreparedInspection($repairOrder, $technician);

    $lf = $inspection->items()->where('label', 'LF Tire')->firstOrFail();
    $rf = $inspection->items()->where('label', 'RF Tire')->firstOrFail();

    $this->actingAs($technician)
        ->patchJson(route('operations.repair-orders.inspection.points.update', [$repairOrder, $lf]), [
            'status' => InspectionChecklistStatus::Good->value,
            'measurements' => [
                ['key' => 'outer', 'name' => 'Outer', 'value' => '7', 'unit' => null],
                ['key' => 'center', 'name' => 'Center', 'value' => '7'],
                ['key' => 'inner', 'name' => 'Inner', 'value' => '6', 'unit' => ''],
                ['key' => 'psi', 'name' => 'Pressure', 'value' => '32', 'unit' => null],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('living_record.status', 'good')
        ->assertJsonPath('follow_up.addressed', true);

    $lf->refresh()->load('measurements');
    $rf->refresh();

    expect($lf->observed_state->value)->toBe('pass')
        ->and($rf->observed_state->value)->toBe('not_checked')
        ->and($lf->measurements->firstWhere('name', 'Outer')?->unit)->toBe('/32"')
        ->and($lf->measurements->firstWhere('name', 'Outer')?->formattedValue())->toBe('7 /32"');
});

test('coverage resume CTA includes remaining points', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase1InspectionRepairOrder($technician);
    $inspection = phase1PreparedInspection($repairOrder, $technician);

    $item = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    $coverage = InspectionCoverageProjection::for($repairOrder->fresh(), $technician);
    expect($coverage['remaining'])->toBeGreaterThan(0)
        ->and($coverage['cta_label'])->toContain('Continue Inspection')
        ->and($coverage['cta_label'])->toContain('points remaining')
        ->and($coverage['posture_key'])->toBe(\App\Ark\Operations\Inspections\InspectionPosture::IN_PROGRESS)
        ->and($coverage['posture_label'])->toStartWith('In Progress');
});

test('phase 0 duplicate finding guard still blocks colliding other findings', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase1InspectionRepairOrder($technician);
    $inspection = phase1PreparedInspection($repairOrder, $technician);

    expect(fn () => app(StoreInspectionFindingAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        actor: $technician,
        intent: InspectionFindingIntent::Safety,
        label: 'LF Brake pads',
    ))->toThrow(ValidationException::class);
});

test('customer report still renders after section walk updates', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase1InspectionRepairOrder($technician);
    $inspection = phase1PreparedInspection($repairOrder, $technician);

    $item = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    $report = app(InspectionReportProjection::class)->for($repairOrder->fresh(), InspectionReportProjection::MODE_SIMPLE);
    expect($report['ready'])->toBeTrue()
        ->and(collect($report['ok_condensed']['labels'])->all())->toContain('Wipers / washer');
});

test('na addresses a point and can complete a section without inventing road-test performance', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase1InspectionRepairOrder($technician);
    $inspection = phase1PreparedInspection($repairOrder, $technician);

    $body = $inspection->items()->where('label', 'Obvious body / underbody damage (ground view)')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $body,
        status: InspectionChecklistStatus::Na,
        actor: $technician,
    );

    $body->refresh();
    expect(InspectionPointCompletion::isAddressed($body, null))->toBeTrue();

    $roadPerformed = $inspection->items()->where('label', 'Road test performed')->firstOrFail();
    expect($roadPerformed->observed_state->value)->toBe('not_checked');
});
