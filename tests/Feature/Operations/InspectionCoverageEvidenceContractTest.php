<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\GuardInspectionCustomerPublication;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\InspectionPhotoPurpose;
use App\Ark\Operations\Inspections\InspectionPointCompletion;
use App\Ark\Operations\Inspections\InspectionPosture;
use App\Ark\Operations\Inspections\InspectionPostureProjection;
use App\Ark\Operations\Inspections\InspectionReviewReadinessProjection;
use App\Ark\Operations\Inspections\InspectionTemplateItem;
use App\Ark\Operations\Inspections\InspectionTemplatePointMeta;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderOperationalOutstandingWork;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    DefaultInspectionTemplateCatalog::rebuildStandardCornerInspectionV1();
});

function coverageEvidenceRepairOrder(?User $technician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Coverage',
        'last_name' => 'Evidence',
        'phone' => '5550176',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $standard = DefaultInspectionTemplateCatalog::standardTemplate();

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::QualityCheck,
        'assigned_technician_id' => $technician?->id,
        'required_inspection_template_id' => $standard?->id,
        'concern_summary' => 'Brakes',
        'mileage_in' => 40000,
        'mileage_out' => 40001,
        'opened_at' => now(),
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake inspection',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'production_status' => ScopeProductionStatus::Completed,
        'recommendation_intent' => 'repair',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Inspect brakes',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
        'subtotal_cents' => 12000,
        'total_cents' => 12000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return $repairOrder->fresh();
}

function coverageEvidenceInspection(RepairOrder $repairOrder, User $technician): Inspection
{
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);
    $inspection->forceFill(['rear_axle_brake_type' => 'disc'])->save();

    return $inspection->fresh();
}

function markVisiblePointsNa(Inspection $inspection): void
{
    $inspection->fresh()->items()
        ->whereNotNull('inspection_template_item_id')
        ->whereNull('superseded_at')
        ->each(function (InspectionItem $item): void {
            $item->forceFill([
                'observed_state' => InspectionObservedState::Na->value,
                'notes' => null,
                'selected_observations' => null,
            ])->save();
        });
}

function yellowBrakePadWithoutPhoto(
    RepairOrder $repairOrder,
    Inspection $inspection,
    User $technician,
    string $label = 'LF Brake pads',
): InspectionItem {
    $item = $inspection->fresh()->items()->where('label', $label)->firstOrFail();

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Monitor,
        actor: $technician,
        notes: 'Pads measure about 5 mm; monitor at next service.',
        measurements: [
            ['key' => 'inner', 'name' => 'Inner pad', 'value' => '5', 'unit' => 'mm'],
            ['key' => 'outer', 'name' => 'Outer pad', 'value' => '5', 'unit' => 'mm'],
        ],
    );

    return $item->fresh()->load(['photos', 'measurements']);
}

test('green brake pads with measurements are covered without photo', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    $item = $inspection->items()->where('label', 'LF Brake pads')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->findOrFail($item->inspection_template_item_id);

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
        measurements: [
            ['key' => 'inner', 'name' => 'Inner pad', 'value' => '8', 'unit' => 'mm'],
            ['key' => 'outer', 'name' => 'Outer pad', 'value' => '8', 'unit' => 'mm'],
        ],
    );

    $fresh = $item->fresh()->load('photos');
    expect(InspectionPointCompletion::isCovered($fresh, $templateItem))->toBeTrue()
        ->and(InspectionPointCompletion::evidenceGaps($fresh, $templateItem))->toBe([])
        ->and(InspectionPointCompletion::coverageState($fresh, $templateItem))->toBe('addressed');
});

test('yellow brake pads with measurements and note are covered; missing photo is evidence only', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    $item = yellowBrakePadWithoutPhoto($repairOrder, $inspection, $technician);
    $templateItem = InspectionTemplateItem::query()->findOrFail($item->inspection_template_item_id);

    expect(InspectionPointCompletion::isCovered($item, $templateItem))->toBeTrue()
        ->and(InspectionPointCompletion::isAddressed($item, $templateItem))->toBeTrue()
        ->and(InspectionPointCompletion::evidenceGaps($item, $templateItem))->toHaveCount(1)
        ->and(InspectionPointCompletion::evidenceGaps($item, $templateItem)[0]['message'])
        ->toBe('Photo missing — Yellow finding recorded')
        ->and(InspectionPointCompletion::coverageState($item, $templateItem))
        ->toBe('addressed_with_evidence_gaps');
});

test('red brake pads with measurements and note are covered; missing photo is evidence only', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    $item = $inspection->items()->where('label', 'LF Brake pads')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->findOrFail($item->inspection_template_item_id);

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Failed,
        actor: $technician,
        notes: 'Pads below safe thickness.',
        measurements: [
            ['key' => 'inner', 'name' => 'Inner pad', 'value' => '2', 'unit' => 'mm'],
            ['key' => 'outer', 'name' => 'Outer pad', 'value' => '2', 'unit' => 'mm'],
        ],
    );

    $fresh = $item->fresh()->load('photos');
    expect(InspectionPointCompletion::isCovered($fresh, $templateItem))->toBeTrue()
        ->and(InspectionPointCompletion::evidenceGaps($fresh, $templateItem)[0]['message'] ?? null)
        ->toBe('Photo missing — Red finding recorded');
});

test('yellow brake pads missing required measurement stay uncovered', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    $item = $inspection->items()->where('label', 'LF Brake pads')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->findOrFail($item->inspection_template_item_id);

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Monitor,
        actor: $technician,
        notes: 'Needs measurement.',
        measurements: [
            ['key' => 'inner', 'name' => 'Inner pad', 'value' => '5', 'unit' => 'mm'],
        ],
    );

    $fresh = $item->fresh()->load(['photos', 'measurements']);
    expect(InspectionPointCompletion::isCovered($fresh, $templateItem))->toBeFalse()
        ->and(InspectionPointCompletion::coverageState($fresh, $templateItem))->toBe('missing_facts')
        ->and(InspectionPointCompletion::missingRequiredSlotNames($fresh, $templateItem))->toContain('Outer pad');
});

test('yellow rotor missing structured observation stays uncovered', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    $item = $inspection->items()->where('label', 'LF Rotor')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->findOrFail($item->inspection_template_item_id);

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Monitor,
        actor: $technician,
        notes: 'Heat spotting noted without coded observation.',
    );

    expect(InspectionPointCompletion::isCovered($item->fresh(), $templateItem))->toBeFalse()
        ->and(collect(InspectionPointCompletion::missingCoverageFacts($item->fresh(), $templateItem))->pluck('kind')->all())
        ->toContain(InspectionPointCompletion::GAP_OBSERVATION);
});

test('n/a remains covered where permitted', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    $item = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->find($item->inspection_template_item_id);

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Na,
        actor: $technician,
    );

    expect(InspectionPointCompletion::isCovered($item->fresh(), $templateItem))->toBeTrue()
        ->and(InspectionPointCompletion::evidenceGaps($item->fresh(), $templateItem))->toBe([]);
});

test('scan evidence gap does not erase coverage when note satisfies scan policy', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    $item = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->findOrFail($item->inspection_template_item_id);
    $templateItem->forceFill(['requires_scan_evidence' => true])->save();

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
        notes: 'Scan code recorded in note until photo upload.',
    );

    $fresh = $item->fresh()->load('photos');
    $templateItem = $templateItem->fresh();
    expect(InspectionPointCompletion::isCovered($fresh, $templateItem))->toBeTrue()
        ->and(InspectionPointCompletion::evidenceGaps($fresh, $templateItem))->toBe([]);
});

test('coverage_requires_photo_when makes photo mandatory for coverage', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    $item = $inspection->items()->where('label', 'LF Brake pads')->firstOrFail();
    $templateItem = InspectionTemplateItem::query()->findOrFail($item->inspection_template_item_id);
    $meta = InspectionTemplatePointMeta::from($templateItem);
    $meta['coverage_requires_photo_when'] = [
        InspectionChecklistStatus::Monitor->value,
        InspectionChecklistStatus::Failed->value,
    ];
    $templateItem->forceFill(['builder_meta' => $meta])->save();

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Monitor,
        actor: $technician,
        notes: '5 mm pads.',
        measurements: [
            ['key' => 'inner', 'name' => 'Inner pad', 'value' => '5', 'unit' => 'mm'],
            ['key' => 'outer', 'name' => 'Outer pad', 'value' => '5', 'unit' => 'mm'],
        ],
    );

    $fresh = $item->fresh()->load('photos');
    $templateItem = $templateItem->fresh();
    expect(InspectionTemplatePointMeta::photoRequiredForCoverage($templateItem, InspectionChecklistStatus::Monitor))->toBeTrue()
        ->and(InspectionPointCompletion::isCovered($fresh, $templateItem))->toBeFalse()
        ->and(InspectionPointCompletion::coverageState($fresh, $templateItem))->toBe('missing_facts');

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.inspection.photos.store', [$repairOrder, $item]), [
            'purpose' => InspectionPhotoPurpose::Internal->value,
            'photo' => UploadedFile::fake()->image('pads.jpg'),
        ])
        ->assertRedirect();

    expect(InspectionPointCompletion::isCovered($item->fresh()->load('photos'), $templateItem->fresh()))->toBeTrue();
});

test('four yellow brake pads with facts yield full coverage posture and evidence warnings', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    markVisiblePointsNa($inspection);

    foreach (['LF Brake pads', 'LR Brake pads', 'RR Brake pads', 'RF Brake pads'] as $label) {
        yellowBrakePadWithoutPhoto($repairOrder, $inspection->fresh(), $technician, $label);
    }

    $posture = app(InspectionPostureProjection::class)->forRepairOrder($repairOrder->fresh());
    $readiness = app(InspectionReviewReadinessProjection::class)->forRepairOrder($repairOrder->fresh());
    $outstanding = app(RepairOrderOperationalOutstandingWork::class)->items($repairOrder->fresh());

    expect($posture->remaining)->toBe(0)
        ->and($posture->checked)->toBe($posture->total)
        ->and($posture->key)->toBe(InspectionPosture::NEEDS_REVIEW)
        ->and($posture->evidenceGapCount)->toBe(4)
        ->and($posture->evidenceDetail)->toContain('documentation')
        ->and($readiness['coverage_complete'])->toBeTrue()
        ->and($readiness['ready_for_internal_review'])->toBeTrue()
        ->and($readiness['ready_for_customer_publication'])->toBeFalse()
        ->and($readiness['evidence_gap_count'])->toBe(4)
        ->and(collect($outstanding)->where('kind', RepairOrderOperationalOutstandingWork::KIND_INSPECTION_IN_PROGRESS))
        ->toBeEmpty();
});

test('optional unfinished inspection does not block completed', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($technician);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    yellowBrakePadWithoutPhoto($repairOrder, $inspection, $technician);

    $posture = app(InspectionPostureProjection::class)->forRepairOrder($repairOrder->fresh());
    expect($posture->key)->toBe(InspectionPosture::IN_PROGRESS)
        ->and($posture->remaining)->toBeGreaterThan(0);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');
});

test('required unfinished inspection still blocks completed without acknowledgment', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($technician);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $repairOrder->forceFill(['inspection_lifecycle_policy' => 'required'])->save();
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    yellowBrakePadWithoutPhoto($repairOrder, $inspection, $technician);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionHasErrors('lifecycle');
});

test('fully covered inspection with evidence gaps closes without unfinished-inspection acknowledgment', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($technician);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    markVisiblePointsNa($inspection);
    yellowBrakePadWithoutPhoto($repairOrder, $inspection->fresh(), $technician);

    $challenge = app(RepairOrderLifecycleTransition::class)->blockingReason(
        $repairOrder->fresh(),
        RepairOrderStatus::Completed->value,
        $technician,
    );

    expect($challenge)->toBeNull();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()->assertSessionDoesntHaveErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Completed))->toBeTrue();
});

test('point walk UI shows covered with photo documentation warning for yellow pads', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($technician);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    markVisiblePointsNa($inspection);
    $item = yellowBrakePadWithoutPhoto($repairOrder, $inspection->fresh(), $technician);

    $this->get(route('operations.repair-orders.inspection.show', [
        'repairOrder' => $repairOrder,
        'point' => $item->id,
    ]))
        ->assertOk()
        ->assertSee('Photo missing — Yellow finding recorded', false)
        ->assertSee('Finding recorded — documentation still needed', false)
        ->assertSee('required for documentation', false)
        ->assertSee('of', false)
        ->assertSee('covered', false);
});

test('mobile follow_up reports coverage and evidence gaps independently', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($technician);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    $item = $inspection->items()->where('label', 'LF Brake pads')->firstOrFail();

    $response = $this->patchJson(route('api.mobile.repair-orders.inspection-checklist.items.update', [
        'repairOrder' => $repairOrder,
        'item' => $item,
    ]), [
        'status' => InspectionChecklistStatus::Monitor->value,
        'note' => 'Pads about 5 mm.',
        'measurements' => [
            ['key' => 'inner', 'name' => 'Inner pad', 'value' => '5', 'unit' => 'mm'],
            ['key' => 'outer', 'name' => 'Outer pad', 'value' => '5', 'unit' => 'mm'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('follow_up.addressed', true)
        ->assertJsonPath('follow_up.coverage_state', 'addressed_with_evidence_gaps')
        ->assertJsonPath('follow_up.requires_photo', true)
        ->assertJsonPath('living_record.addressed', true)
        ->assertJsonPath('living_record.coverage_state', 'addressed_with_evidence_gaps');

    expect($response->json('follow_up.evidence_gaps.0.message'))
        ->toBe('Photo missing — Yellow finding recorded');
});

test('customer publication is blocked when coverage is complete but required evidence is missing', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    markVisiblePointsNa($inspection);
    yellowBrakePadWithoutPhoto($repairOrder, $inspection->fresh(), $technician);

    $blocked = $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.inspection-portal-link', $repairOrder))
        ->assertStatus(422)
        ->json('message');

    expect($blocked)->toContain('Photo missing')
        ->and($blocked)->toContain('Yellow finding recorded');

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.inspection-portal-preview', $repairOrder))
        ->assertOk();

    expect(app(GuardInspectionCustomerPublication::class)
        ->blockReason($repairOrder->fresh()))
        ->toContain('Photo missing');
});

test('customer publication is blocked while coverage remains incomplete', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    yellowBrakePadWithoutPhoto($repairOrder, $inspection, $technician);

    $blocked = $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.inspection-portal-link', $repairOrder))
        ->assertStatus(422)
        ->json('message');

    expect($blocked)->toContain('coverage is incomplete');
});

test('customer publication succeeds when coverage and evidence are complete', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($technician);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    markVisiblePointsNa($inspection);
    $item = yellowBrakePadWithoutPhoto($repairOrder, $inspection->fresh(), $technician);

    $this->post(route('operations.repair-orders.inspection.photos.store', [$repairOrder, $item]), [
        'purpose' => InspectionPhotoPurpose::Internal->value,
        'photo' => UploadedFile::fake()->image('pads.jpg'),
    ])->assertRedirect();

    $readiness = app(InspectionReviewReadinessProjection::class)->forRepairOrder($repairOrder->fresh());
    expect($readiness['ready_for_customer_publication'])->toBeTrue();

    $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.inspection-portal-link', $repairOrder))
        ->assertOk()
        ->assertJsonStructure(['url']);
});

test('unauthorized actors cannot mint customer inspection portal links', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $outsider = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    markVisiblePointsNa($inspection);
    $item = yellowBrakePadWithoutPhoto($repairOrder, $inspection->fresh(), $technician);
    $this->actingAs($technician)
        ->post(route('operations.repair-orders.inspection.photos.store', [$repairOrder, $item]), [
            'purpose' => InspectionPhotoPurpose::Internal->value,
            'photo' => UploadedFile::fake()->image('pads.jpg'),
        ])
        ->assertRedirect();

    $this->actingAs($outsider)
        ->getJson(route('operations.repair-orders.inspection-portal-link', $repairOrder))
        ->assertForbidden();
});

test('existing customer portal tokens remain resolvable after evidence gaps appear', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($technician);
    $repairOrder = coverageEvidenceRepairOrder($technician);
    $inspection = coverageEvidenceInspection($repairOrder, $technician);
    markVisiblePointsNa($inspection);
    $item = yellowBrakePadWithoutPhoto($repairOrder, $inspection->fresh(), $technician);
    $this->post(route('operations.repair-orders.inspection.photos.store', [$repairOrder, $item]), [
        'purpose' => InspectionPhotoPurpose::Internal->value,
        'photo' => UploadedFile::fake()->image('pads.jpg'),
    ])->assertRedirect();

    $link = $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.inspection-portal-link', $repairOrder))
        ->assertOk()
        ->json('url');

    expect($link)->toContain('/portal/inspections/');
    $token = basename(parse_url($link, PHP_URL_PATH) ?: '');

    $item->photos()->delete();
    expect(app(InspectionReviewReadinessProjection::class)
        ->forRepairOrder($repairOrder->fresh())['ready_for_customer_publication'])->toBeFalse();

    $this->get(route('portal.inspections.show', ['token' => $token]))
        ->assertOk();
});
