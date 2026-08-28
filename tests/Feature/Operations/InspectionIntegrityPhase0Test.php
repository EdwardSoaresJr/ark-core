<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemMeasurement;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\InspectionPhotoPurpose;
use App\Ark\Operations\Inspections\InspectionReportProjection;
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

function phase0InspectionRepairOrder(?User $technician = null): RepairOrder
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
        'concern_summary' => 'Inspection',
        'opened_at' => now(),
    ]);
}

test('other finding colliding with template point label is rejected', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase0InspectionRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $point = $inspection->items()->where('label', 'LF Brake pads')->firstOrFail();
    expect($point->inspection_template_item_id)->not->toBeNull();

    $before = $inspection->items()->count();

    expect(fn () => app(StoreInspectionFindingAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        actor: $technician,
        intent: InspectionFindingIntent::Safety,
        label: 'LF Brake pads',
        notes: 'Almost metal on metal.',
    ))->toThrow(ValidationException::class);

    expect(fn () => app(StoreInspectionFindingAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        actor: $technician,
        intent: InspectionFindingIntent::Safety,
        label: '  lf   BRAKE   pads  ',
        notes: 'Case and whitespace variant.',
    ))->toThrow(ValidationException::class);

    expect($inspection->fresh()->items()->count())->toBe($before);
});

test('web other finding store redirects with validation when label collides', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase0InspectionRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $before = $inspection->items()->count();

    $this->actingAs($technician)
        ->from(route('operations.repair-orders.inspection.show', $repairOrder))
        ->post(route('operations.repair-orders.inspection.findings.store', $repairOrder), [
            'intent' => InspectionFindingIntent::Safety->value,
            'label' => 'LF Brake pads',
            'notes' => 'Almost metal on metal.',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('label');

    expect($inspection->fresh()->items()->count())->toBe($before);
});

test('genuine other finding still stores when template has no matching point', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase0InspectionRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $item = app(StoreInspectionFindingAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        actor: $technician,
        intent: InspectionFindingIntent::Safety,
        label: 'Left running board loose',
        notes: 'Bolts backing out.',
    );

    expect($item->inspection_template_item_id)->toBeNull()
        ->and($item->label)->toBe('Left running board loose')
        ->and($item->notes)->toContain('Bolts backing out.');
});

test('template measurement slots persist defined unit when client omits unit', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase0InspectionRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $tire = $inspection->items()->where('label', 'LF Tire')->firstOrFail();

    $result = app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $tire,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
        measurements: [
            ['key' => 'outer', 'name' => 'Outer', 'value' => '6', 'unit' => null],
            ['key' => 'center', 'name' => 'Center', 'value' => '6'],
            ['key' => 'inner', 'name' => 'Inner', 'value' => '6', 'unit' => ''],
            ['key' => 'psi', 'name' => 'Pressure', 'value' => '32', 'unit' => null],
        ],
    );

    $rows = $result['item']->measurements->keyBy('name');

    expect($rows['Outer']->unit)->toBe('/32"')
        ->and($rows['Outer']->formattedValue())->toBe('6 /32"')
        ->and($rows['Pressure']->unit)->toBe('PSI')
        ->and($rows['Pressure']->formattedValue())->toBe('32 PSI');

    $report = app(InspectionReportProjection::class)->for($repairOrder->fresh(), InspectionReportProjection::MODE_DETAILED);
    $point = collect($report['points'])->firstWhere('label', 'LF Tire');

    expect($point)->not->toBeNull();
    $psi = collect($point['measurements'])->firstWhere('key', 'psi');
    expect($psi['formatted'])->toBe('32 PSI')
        ->and($psi['unit'])->toBe('PSI');
});

test('freeform measurement without unit remains valid', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase0InspectionRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $item = app(StoreInspectionFindingAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        actor: $technician,
        intent: InspectionFindingIntent::Observation,
        label: 'Custom gauge reading',
        measurementValue: '42',
        measurementUnit: null,
        measurementName: 'Reading',
    );

    $measurement = $item->measurements->first();

    expect($measurement)->not->toBeNull()
        ->and($measurement->value)->toBe('42')
        ->and($measurement->unit)->toBeNull()
        ->and($measurement->formattedValue())->toBe('42');
});

test('customer report suppresses contradictory freeform condition for the same template point', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = phase0InspectionRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $point = $inspection->items()->where('label', 'LF Brake pads')->firstOrFail();
    $point->update([
        'observed_state' => InspectionObservedState::Pass->value,
        'notes' => null,
    ]);

    // Historical bad data: freeform duplicate with a different condition + orphan note/photo.
    $freeform = InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => 'brakes',
        'label' => 'LF Brake pads',
        'observed_state' => InspectionObservedState::Monitor->value,
        'notes' => '[Safety] Almost metal on metal.',
        'position' => 900,
    ]);

    InspectionItemMeasurement::query()->create([
        'inspection_item_id' => $freeform->id,
        'name' => 'Orphan pad reading',
        'value' => '1',
        'unit' => 'mm',
        'position' => 0,
    ]);

    InspectionItemPhoto::query()->create([
        'inspection_item_id' => $freeform->id,
        'storage_path' => 'inspections/phase0-orphan.jpg',
        'original_name' => 'pad.jpg',
        'content_type' => 'image/jpeg',
        'purpose' => InspectionPhotoPurpose::Customer->value,
        'uploaded_by_user_id' => $technician->id,
    ]);

    $report = app(InspectionReportProjection::class)->for($repairOrder->fresh(), InspectionReportProjection::MODE_DETAILED);
    $matches = collect($report['points'])->where('label', 'LF Brake pads')->values();

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['condition_value'])->toBe('good')
        ->and($matches[0]['condition_value'])->not->toBe('monitor')
        ->and($matches[0]['note'])->toContain('Almost metal on metal')
        ->and(collect($matches[0]['measurements'])->pluck('name')->all())->toContain('Orphan pad reading')
        ->and(collect($matches[0]['photos'])->pluck('id')->all())->toContain($freeform->photos->first()->id);

    $simple = app(InspectionReportProjection::class)->for($repairOrder->fresh(), InspectionReportProjection::MODE_SIMPLE);
    $monitorLabels = collect($simple['monitor_findings'])->pluck('label')->all();
    $okLabels = collect($simple['ok_condensed']['labels'])->all();

    expect($monitorLabels)->not->toContain('LF Brake pads')
        ->and($okLabels)->toContain('LF Brake pads');
});
