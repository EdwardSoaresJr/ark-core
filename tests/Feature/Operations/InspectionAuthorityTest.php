<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemMeasurement;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\InspectionPhotoPurpose;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\Inspections\InspectionWorkspaceUrl;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    DefaultInspectionTemplateCatalog::rebuildStandardCornerInspectionV1();
});

test('repair order inspection starts empty without mpi seed rows', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder();

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    expect(Inspection::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1)
        ->and($inspection->items()->count())->toBe(0)
        ->and(app(EnsureInspectionAction::class)->execute($repairOrder, $technician)->id)->toBe($inspection->id);
});

test('technician can attach inspection video evidence to a checklist point', function (): void {
    Storage::fake('local');

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $item = $inspection->items()->create([
        'category' => 'brakes',
        'label' => 'Front brake pads',
        'observed_state' => InspectionObservedState::Fail->value,
        'position' => 0,
    ]);

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.inspection.photos.store', [$repairOrder, $item]), [
            'purpose' => InspectionPhotoPurpose::Customer->value,
            'photo' => UploadedFile::fake()->create('pad-wear.mp4', 512, 'video/mp4'),
        ])
        ->assertRedirect();

    $evidence = InspectionItemPhoto::query()->sole();

    expect($evidence->isVideo())->toBeTrue()
        ->and($evidence->content_type)->toBe('video/mp4')
        ->and(Storage::disk('local')->exists($evidence->storage_path))->toBeTrue();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.photos.show', [$repairOrder, $evidence]))
        ->assertOk()
        ->assertHeader('content-type', 'video/mp4');
});

test('technician can remove inspection photo evidence from a checklist point', function (): void {
    Storage::fake('local');

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $item = $inspection->items()->create([
        'category' => 'brakes',
        'label' => 'Front brake pads',
        'observed_state' => InspectionObservedState::Fail->value,
        'position' => 0,
    ]);

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.inspection.photos.store', [$repairOrder, $item]), [
            'purpose' => InspectionPhotoPurpose::Customer->value,
            'photo' => UploadedFile::fake()->image('pad.jpg'),
        ])
        ->assertRedirect();

    $photo = InspectionItemPhoto::query()->sole();
    $path = $photo->storage_path;

    $this->actingAs($technician)
        ->delete(route('operations.repair-orders.inspection.photos.destroy', [$repairOrder, $photo]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Photo removed.');

    expect(InspectionItemPhoto::query()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

test('technician can save a finding with measurement and photo in one action', function () {
    Storage::fake('local');

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);
    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->sole();

    app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.inspection.findings.store', $repairOrder), [
            'intent' => InspectionFindingIntent::Maintenance->value,
            'label' => 'Front brake pads',
            'measurement_value' => '3',
            'measurement_unit' => 'mm',
            'notes' => 'RF pad measured.',
            'repair_order_concern_id' => $concern->id,
            'photo' => UploadedFile::fake()->image('pad.jpg'),
        ])
        ->assertRedirect(InspectionWorkspaceUrl::finding($repairOrder, InspectionItem::query()->sole()));

    $item = InspectionItem::query()->sole();

    expect($item->label)->toBe('Front brake pads')
        ->and($item->observed_state)->toBe(InspectionObservedState::Measure)
        ->and($item->notes)->toBe('[Maintenance] RF pad measured.')
        ->and($item->repair_order_concern_id)->toBe($concern->id);

    $measurement = InspectionItemMeasurement::query()->sole();

    expect($measurement->formattedValue())->toBe('3 mm');

    $photo = InspectionItemPhoto::query()->sole();

    expect($photo->purpose)->toBe(InspectionPhotoPurpose::Internal)
        ->and(Storage::disk('local')->exists($photo->storage_path))->toBeTrue();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.photos.show', [$repairOrder, $photo]))
        ->assertOk();
});

test('technician can update an existing finding item', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);
    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->sole();

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $item = $inspection->items()->create([
        'category' => 'brakes',
        'label' => 'Front brake pads',
        'observed_state' => InspectionObservedState::Measure->value,
        'notes' => '[Maintenance]',
        'position' => 0,
    ]);

    $this->actingAs($technician)
        ->patch(route('operations.repair-orders.inspection.items.update', [$repairOrder, $item]), [
            'observed_state' => InspectionObservedState::Fail->value,
            'notes' => '[Safety] Metal on metal.',
            'repair_order_concern_id' => $concern->id,
        ])
        ->assertRedirect(InspectionWorkspaceUrl::finding($repairOrder, $item));

    $item->refresh();

    expect($item->observed_state)->toBe(InspectionObservedState::Fail)
        ->and($item->notes)->toBe('[Safety] Metal on metal.')
        ->and($item->repair_order_concern_id)->toBe($concern->id);
});

test('ro inspection tab is control center without walk', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);

    app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'inspect']))
        ->assertOk()
        ->assertSee('ops-inspection--control', false)
        ->assertSee('Open Inspection', false)
        ->assertSee('Send walk link', false)
        ->assertSee('data-inspection-handoff-sms', false)
        ->assertSee('data-inspection-handoff-email', false)
        ->assertSee('Bay layout', false)
        ->assertDontSee('Open Tablet View', false)
        ->assertDontSee('Send to Technician', false)
        ->assertDontSee('ops-inspection-walk', false)
        ->assertDontSee('Next →', false)
        ->assertDontSee('Vocabulary gap', false);
});

test('dedicated inspection host shows section walk with condition vocabulary', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);

    app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-inspection-sections', false)
        ->assertSee('LF Tire', false)
        ->assertSee('Green', false)
        ->assertSee('Yellow', false)
        ->assertSee('Red', false)
        ->assertSee('Other Findings', false)
        ->assertSee('Add Finding', false)
        ->assertDontSee('>Replace<', false)
        ->assertDontSee('Open on technician device', false)
        ->assertDontSee('Advisor Review', false)
        ->assertDontSee('Vocabulary gap', false);
});

test('inspection capture link targets production walk with concern query param', function () {
    $repairOrder = inspectionRepairOrder();
    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->sole();

    $url = \App\Ark\Operations\Inspections\InspectionCaptureLinks::captureUrl($repairOrder, $concern->id);

    expect($url)->toContain('capture=1')
        ->and($url)->toContain('concern='.$concern->id)
        ->and($url)->toContain('/inspection')
        ->and($url)->not->toContain('estimate-review')
        ->and($url)->not->toContain('#inspect');
});

test('inspect control tab auto-applies template and shows coverage', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);

    app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'inspect']))
        ->assertOk()
        ->assertSee('checked', false)
        ->assertSee('Open Inspection', false);
});

test('estimate builder shows verified findings finding nudge', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = inspectionRepairOrder();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Open Inspection', false);
});

test('estimate review findings tab shows recorded count badge', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = inspectionRepairOrder();

    app(EnsureInspectionAction::class)->execute($repairOrder, $advisor);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.inspection.findings.store', $repairOrder), [
            'intent' => InspectionFindingIntent::Maintenance->value,
            'label' => 'Front brake pads',
            'measurement_value' => '3',
            'measurement_unit' => 'mm',
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Inspection', false)
        ->assertSee('ops-ro-workspace-tab__meta--findings', false)
        ->assertSee('>1<', false);
});

test('inspection living record projection after checklist update', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $item = $inspection->items()->firstOrFail();

    $result = app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    expect($result['item']->observed_state)->toBe(InspectionObservedState::Pass);

    $record = app(\App\Ark\Operations\Inspections\InspectionItemLivingRecordProjection::class)->forItem(
        $repairOrder,
        $result['item'],
    );

    expect($record['status'])->toBe('good');
});

test('inspection walk shows progress after condition update', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $item = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();

    $this->actingAs($technician)
        ->patchJson(route('operations.repair-orders.inspection.points.update', [$repairOrder, $item]), [
            'status' => InspectionChecklistStatus::Monitor->value,
        ])
        ->assertOk()
        ->assertJsonPath('living_record.status', 'monitor');

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', ['repairOrder' => $repairOrder, 'point' => $item->id]))
        ->assertOk()
        ->assertSee('1/', false)
        ->assertSee('ops-inspection-walk', false);
});

test('save and add another reopens capture sheet with concern preserved', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($technician);
    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->sole();

    app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.inspection.findings.store', $repairOrder), [
            'intent' => InspectionFindingIntent::Maintenance->value,
            'label' => 'Front brake pads',
            'measurement_value' => '3',
            'measurement_unit' => 'mm',
            'repair_order_concern_id' => $concern->id,
            'add_another' => true,
        ])
        ->assertRedirect(InspectionWorkspaceUrl::capture($repairOrder, $concern->id))
        ->assertSessionHas('status', 'Finding saved. Record another.')
        ->assertSessionHas('finding_intent', InspectionFindingIntent::Maintenance->value);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', [
            'repairOrder' => $repairOrder,
            'capture' => 1,
            'concern' => $concern->id,
        ]))
        ->assertOk()
        ->assertSee('Record finding', false)
        ->assertSee('Save &amp; add another', false)
        ->assertSee('Other Findings', false);
});

test('unassigned technician cannot record findings on repair order', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $otherTechnician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionRepairOrder($otherTechnician);

    app(EnsureInspectionAction::class)->execute($repairOrder, $otherTechnician);

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.inspection.findings.store', $repairOrder), [
            'intent' => InspectionFindingIntent::Maintenance->value,
            'label' => 'Front brake pads',
        ])
        ->assertForbidden();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', $repairOrder))
        ->assertOk()
        ->assertSee('Other Findings', false)
        ->assertDontSee('ops-inspection-finding-btn', false);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'inspect']))
        ->assertOk()
        ->assertSee('Open Inspection', false)
        ->assertDontSee('ops-inspection-finding-btn', false);
});

function inspectionRepairOrder(?User $assignedTechnician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Inspection',
        'last_name' => 'Customer',
        'phone' => '7195551000',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'INSP1',
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => '2HGFC2F59KH123456',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Brake noise inspection.',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise on stop',
        'disposition' => 'draft',
        'position' => 0,
    ]);

    if ($assignedTechnician !== null) {
        $repairOrder->forceFill(['assigned_technician_id' => $assignedTechnician->id])->save();
    }

    return $repairOrder->fresh();
}
