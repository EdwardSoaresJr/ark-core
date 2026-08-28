<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\FrozenInspectionTemplateDefinitions;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionPhysicalSectionMap;
use App\Ark\Operations\Inspections\InspectionPointCompletion;
use App\Ark\Operations\Inspections\InspectionSectionWalkProjection;
use App\Ark\Operations\Inspections\InspectionTemplateItem;
use App\Ark\Operations\Inspections\InspectionTemplatePointMeta;
use App\Ark\Operations\Inspections\InspectionChecklistItems;
use App\Ark\Operations\Inspections\InspectionPhotoPurpose;
use App\Ark\Operations\Inspections\InspectionWalkVisibility;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use Illuminate\Http\UploadedFile;
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

function cornerRepairOrder(?User $technician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Corner',
        'last_name' => 'Walk',
        'phone' => '5550199',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $standard = DefaultInspectionTemplateCatalog::standardTemplate();

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'assigned_technician_id' => $technician?->id,
        'required_inspection_template_id' => $standard?->id,
        'concern_summary' => 'Noise',
        'opened_at' => now(),
    ]);
}

test('standard definition freezes corner order LF LR RR RF', function () {
    $names = array_column(FrozenInspectionTemplateDefinitions::standard(), 'name');

    expect($names)->toContain('Left Front', 'Left Rear', 'Right Rear', 'Right Front', 'Brake system')
        ->and(array_search('Left Front', $names, true))->toBeLessThan(array_search('Left Rear', $names, true))
        ->and(array_search('Left Rear', $names, true))->toBeLessThan(array_search('Right Rear', $names, true))
        ->and(array_search('Right Rear', $names, true))->toBeLessThan(array_search('Right Front', $names, true));

    $stages = InspectionPhysicalSectionMap::standardStages();
    expect($stages[0]['key'])->toBe('corner_inspection')
        ->and(array_column($stages[0]['sections'], 'key'))->toBe([
            'rear_axle',
            'left_front',
            'left_rear',
            'right_rear',
            'right_front',
            'brake_system',
        ]);
});

test('tire and brake corner points do not offer N/A', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = cornerRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $ordered = InspectionWalkVisibility::visibleItems(
        $inspection->fresh(),
        InspectionChecklistItems::orderedChecklistItems($inspection->fresh(['items'])),
    );
    $templateItems = InspectionTemplateItem::query()
        ->whereIn('id', $ordered->pluck('inspection_template_item_id')->filter())
        ->get()
        ->keyBy('id');

    $walk = app(InspectionSectionWalkProjection::class)->for(
        $repairOrder,
        $inspection->fresh(),
        $ordered,
        $templateItems,
    );

    $cornerPoints = collect($walk['stages'][0]['sections'] ?? [])
        ->flatMap(fn (array $section) => $section['points'] ?? []);

    expect($cornerPoints)->not->toBeEmpty();

    foreach ($cornerPoints as $point) {
        if (! empty($point['is_axle_gate'])) {
            continue;
        }
        $displays = collect($point['condition_options'] ?? [])->pluck('display')->all();
        expect($displays)->not->toContain('N/A')
            ->and($displays)->toContain('Green', 'Yellow', 'Red');
    }
});

test('applied standard has corner points with builder meta and gyr palette', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = cornerRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    expect($inspection->items()->where('label', 'LF Tire')->exists())->toBeTrue()
        ->and($inspection->items()->where('label', 'LF Wheel')->exists())->toBeTrue()
        ->and($inspection->items()->where('label', 'LF Brake pads')->exists())->toBeTrue()
        ->and($inspection->items()->where('label', 'LF Rotor')->exists())->toBeTrue()
        ->and($inspection->items()->where('label', 'LF Caliper')->exists())->toBeTrue()
        ->and($inspection->items()->where('label', 'LF Brake hose')->exists())->toBeTrue()
        ->and($inspection->items()->where('label', 'Parking brake')->exists())->toBeTrue()
        ->and($inspection->items()->where('label', 'Brake fluid — level / condition')->exists())->toBeTrue();

    $tire = $inspection->items()->where('label', 'LF Tire')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->findOrFail($tire->inspection_template_item_id);

    expect(InspectionTemplatePointMeta::conditionPalette($templateItem))->toBe('gyr')
        ->and(InspectionTemplatePointMeta::observationOptions($templateItem))->not->toBeEmpty();

    $options = collect(InspectionTemplatePointMeta::conditionOptions($templateItem))->pluck('display')->all();
    expect($options)->toContain('Green', 'Yellow', 'Red');
});

test('yellow rotor requires observation note and photo before addressed', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = cornerRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $rotor = $inspection->items()->where('label', 'LF Rotor')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->findOrFail($rotor->inspection_template_item_id);

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $rotor,
        status: InspectionChecklistStatus::Monitor,
        actor: $technician,
    );

    expect(InspectionPointCompletion::isAddressed($rotor->fresh(), $templateItem))->toBeFalse();

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $rotor->fresh(),
        status: InspectionChecklistStatus::Monitor,
        actor: $technician,
        notes: 'Heat spotting visible on outer face',
        selectedObservations: ['heat_spotting'],
    );

    expect(InspectionPointCompletion::isAddressed($rotor->fresh(), $templateItem))->toBeFalse();

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.inspection.photos.store', [$repairOrder, $rotor]), [
            'purpose' => InspectionPhotoPurpose::Customer->value,
            'photo' => UploadedFile::fake()->image('rotor.jpg'),
        ])
        ->assertRedirect();

    expect(InspectionPointCompletion::isAddressed($rotor->fresh()->load('photos'), $templateItem))->toBeTrue();
});

test('section walk projects corner stage first with green yellow red labels', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = cornerRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $ordered = InspectionWalkVisibility::visibleItems(
        $inspection->fresh(),
        InspectionChecklistItems::orderedChecklistItems($inspection->fresh(['items'])),
    );
    $templateItems = InspectionTemplateItem::query()
        ->whereIn('id', $ordered->pluck('inspection_template_item_id')->filter())
        ->get()
        ->keyBy('id');

    $walk = app(InspectionSectionWalkProjection::class)->for(
        $repairOrder,
        $inspection->fresh(),
        $ordered,
        $templateItems,
    );

    expect($walk['stages'][0]['key'])->toBe('corner_inspection')
        ->and($walk['stages'][0]['label'])->toBe('Corner Inspection');

    $lfTire = collect($walk['stages'][0]['sections'])
        ->firstWhere('key', 'left_front')['points'] ?? [];
    $tirePoint = collect($lfTire)->firstWhere('label', 'LF Tire');

    expect($tirePoint)->not->toBeNull()
        ->and(collect($tirePoint['condition_options'])->pluck('display')->all())->toContain('Green', 'Yellow', 'Red')
        ->and($tirePoint['observation_options'])->not->toBeEmpty();
});

test('apply snapshots walk_section and walk ignores category labels and live builder', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = cornerRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $lfTire = $inspection->items()->where('label', 'LF Tire')->firstOrFail();
    expect($lfTire->walk_section)->toBe('left_front');

    $rearAxle = $inspection->items()->where('label', 'Rear axle brake type')->firstOrFail();
    expect($rearAxle->walk_section)->toBe('rear_axle');

    $brakeFluid = $inspection->items()->where('label', 'Brake fluid — level / condition')->firstOrFail();
    expect($brakeFluid->walk_section)->toBe('brake_system');

    // Category drift must not move Corner points.
    $cornerItemIds = $inspection->items()
        ->whereIn('label', [
            'Rear axle brake type',
            'LF Tire',
            'LF Brake pads',
            'LR Tire',
            'RR Tire',
            'RF Tire',
            'Brake fluid — level / condition',
            'Parking brake',
        ])
        ->pluck('id');
    $inspection->items()->whereIn('id', $cornerItemIds)->update(['checklist_category_name' => 'Brakes']);

    // Live Builder change after apply must not move already-snapshotted points.
    $templateItem = InspectionTemplateItem::query()->findOrFail($lfTire->inspection_template_item_id);
    $meta = is_array($templateItem->builder_meta) ? $templateItem->builder_meta : [];
    $meta['walk_section'] = 'under_vehicle';
    $meta['corner'] = 'rf';
    $templateItem->forceFill(['builder_meta' => $meta])->save();

    $ordered = InspectionWalkVisibility::visibleItems(
        $inspection->fresh(),
        InspectionChecklistItems::orderedChecklistItems($inspection->fresh(['items'])),
    );
    $templateItems = InspectionTemplateItem::query()
        ->whereIn('id', $ordered->pluck('inspection_template_item_id')->filter())
        ->get()
        ->keyBy('id');

    $walk = app(InspectionSectionWalkProjection::class)->for(
        $repairOrder,
        $inspection->fresh(),
        $ordered,
        $templateItems,
    );

    expect($walk['stages'][0]['key'])->toBe('corner_inspection');

    $lf = collect($walk['stages'][0]['sections'])->firstWhere('key', 'left_front');
    expect(collect($lf['points'] ?? [])->pluck('label')->all())->toContain('LF Tire', 'LF Brake pads');

    $underVehicle = collect($walk['stages'])
        ->flatMap(fn (array $stage) => $stage['sections'])
        ->firstWhere('key', 'under_vehicle');
    expect(collect($underVehicle['points'] ?? [])->pluck('label')->all())->not->toContain('LF Tire');

    $allSectionLabels = collect($walk['stages'])->flatMap(fn (array $s) => collect($s['sections'])->pluck('label'));
    expect($allSectionLabels->contains('Brakes'))->toBeFalse();
});

test('legacy walk still groups by checklist category when walk_section snapshot is absent', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = cornerRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $inspection->items()->create([
        'category' => 'tires',
        'label' => 'LF tire — condition / damage',
        'checklist_category_name' => 'Tires',
        'walk_section' => null,
        'position' => 1,
        'inspection_template_item_id' => null,
    ]);
    $inspection->items()->create([
        'category' => 'brakes',
        'label' => 'LF brake',
        'checklist_category_name' => 'Brakes',
        'walk_section' => null,
        'position' => 2,
        'inspection_template_item_id' => null,
    ]);
    $inspection->items()->create([
        'category' => 'general',
        'label' => 'Fluid leaks (underbody)',
        'checklist_category_name' => 'Under vehicle',
        'walk_section' => null,
        'position' => 3,
        'inspection_template_item_id' => null,
    ]);

    $ordered = InspectionWalkVisibility::visibleItems(
        $inspection->fresh(),
        InspectionChecklistItems::orderedChecklistItems($inspection->fresh(['items'])),
    );

    $walk = app(InspectionSectionWalkProjection::class)->for(
        $repairOrder,
        $inspection->fresh(),
        $ordered,
        collect(),
    );

    $stageKeys = collect($walk['stages'])->pluck('key')->all();
    expect($stageKeys)->toContain('on_the_lift', 'checklist')
        ->and($stageKeys)->not->toContain('corner_inspection');

    $legacy = collect($walk['stages'])->firstWhere('key', 'checklist');
    $legacyLabels = collect($legacy['sections'] ?? [])->pluck('label')->all();
    expect($legacyLabels)->toContain('Tires', 'Brakes');
});
