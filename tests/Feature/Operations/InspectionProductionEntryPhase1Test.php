<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionCoverageProjection;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\Portal\InspectionAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    DefaultInspectionTemplateCatalog::rebuildStandardCornerInspectionV1();
});

test('assigned technician sees start inspection from production ro show', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = productionDisciplineRepairOrder($technician);

    // Named show is the production RO surface technicians can open.
    // Builder edit requires Manage and is advisor-side.
    $this->actingAs($technician)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-technician-production-landing', false)
        ->assertSee('data-inspection-entry', false)
        ->assertSee('Vehicle Inspection', false)
        ->assertSee('Not Started', false)
        ->assertSee('Open Inspection', false)
        ->assertSee('data-inspection-capture-cta', false)
        ->assertSee(route('operations.repair-orders.inspection.show', $repairOrder), false)
        ->assertDontSee('ops-estimate-workspace--review', false)
        ->assertDontSee('+ Finding', false);
});

test('advisor estimate keeps inspection on Inspection tab not estimate body', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = productionDisciplineRepairOrder();

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Inspection', false)
        ->assertSee('Not Started', false)
        ->assertDontSee('data-inspection-entry', false)
        ->assertDontSee('data-inspection-entry-builder', false)
        ->assertDontSee('data-inspection-cta-capture', false)
        ->getContent();

    expect($html)->toContain("selectTab('inspect')")
        ->and($html)->not->toContain('estimate-review#inspect');
});

test('production inspection show hosts section walk without distribution chrome', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = productionDisciplineRepairOrder($technician);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-inspection-sections', false)
        ->assertSee('LF Tire', false)
        ->assertSee('Standard Vehicle Inspection', false)
        ->assertSee('Other Findings', false)
        ->assertDontSee('Open on technician device', false)
        ->assertDontSee('Advisor Review', false)
        ->assertDontSee('Send to Technician', false)
        ->assertDontSee('ops-estimate-workspace--review', false)
        ->assertDontSee('ops-inspection-tablet-shell', false);
});

test('tablet surface uses chromeless bay shell with section walk primary', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = productionDisciplineRepairOrder($technician);

    $openUrl = \App\Ark\Operations\Inspections\InspectionCaptureLinks::walkUrl($repairOrder);
    $tabletUrl = \App\Ark\Operations\Inspections\InspectionCaptureLinks::tabletUrl($repairOrder);

    expect($tabletUrl)->not->toBe($openUrl)
        ->and($tabletUrl)->toContain('surface=tablet');

    $this->actingAs($technician)
        ->get($tabletUrl)
        ->assertOk()
        ->assertSee('ops-inspection-tablet-shell', false)
        ->assertSee('ops-inspection-tablet-card', false)
        ->assertSee('ops-inspection-sections', false)
        ->assertSee('ops-inspection-sections--tablet', false)
        ->assertSee('Good', false)
        ->assertSee('Needs Attention', false)
        ->assertDontSee('ops-estimate-workspace', false)
        ->assertDontSee('Send to Technician', false);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);
    $first = $inspection->fresh()->items()->whereNotNull('inspection_template_item_id')->orderBy('id')->firstOrFail();
    $second = $inspection->items()
        ->whereNotNull('inspection_template_item_id')
        ->where('id', '>', $first->id)
        ->orderBy('id')
        ->firstOrFail();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', [
            'repairOrder' => $repairOrder,
            'point' => $first->id,
            'surface' => 'tablet',
        ]))
        ->assertOk()
        ->assertSee('ops-inspection-walk--tablet', false)
        ->assertSee('Back to sections', false)
        ->assertSee('surface=tablet', false)
        ->assertSee((string) $second->id, false);
});

test('ro inspection tab offers device actions without walk', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = productionDisciplineRepairOrder($technician);
    $tabletUrl = \App\Ark\Operations\Inspections\InspectionCaptureLinks::tabletUrl($repairOrder);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'inspect',
        ]))
        ->assertOk()
        ->assertSee('Open Inspection', false)
        ->assertSee('Send walk link', false)
        ->assertSee('data-inspection-handoff-sms', false)
        ->assertSee('data-inspection-handoff-email', false)
        ->assertSee('Bay layout', false)
        ->assertDontSee('Open Tablet View', false)
        ->assertSee($tabletUrl, false)
        ->assertSee('data-inspection-handoff', false)
        ->assertDontSee('Send to Technician', false)
        ->assertDontSee('ops-inspection-walk', false)
        ->assertDontSee('Next →', false);
});

test('inspection handoff lets advisor choose staff for sms or email', function () {
    $advisor = User::factory()->create([
        'name' => 'Ada Advisor',
        'email' => 'ada@example.com',
        'phone' => '7195551001',
    ])->assignRole(ArkRole::Advisor->value);

    $assigned = User::factory()->create([
        'name' => 'Terry Tech',
        'email' => 'terry@example.com',
        'phone' => '7195551002',
    ])->assignRole(ArkRole::Technician->value);

    $other = User::factory()->create([
        'name' => 'Pat Other',
        'email' => 'pat@example.com',
        'phone' => '7195551003',
    ])->assignRole(ArkRole::Technician->value);

    $repairOrder = productionDisciplineRepairOrder($assigned);

    $control = app(\App\Ark\Operations\Inspections\InspectionControlCenterProjection::class)
        ->for($repairOrder, $advisor);

    $recipientIds = collect($control['actions']['recipients'])->pluck('id')->all();

    expect($control['actions']['default_recipient_id'])->toBe($assigned->id)
        ->and($recipientIds)->toContain($assigned->id, $other->id, $advisor->id)
        ->and(collect($control['actions']['recipients'])->firstWhere('id', $other->id)['phone'])->not->toBeNull();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'inspect',
        ]))
        ->assertOk()
        ->assertSee('Terry Tech', false)
        ->assertSee('Pat Other', false)
        ->assertSee('Ada Advisor', false)
        ->assertSee('data-inspection-handoff-sms', false)
        ->assertSee('data-inspection-handoff-email', false)
        ->assertSee(route('operations.repair-orders.inspection.walk-link.send', $repairOrder), false)
        ->assertDontSee('sms:', false)
        ->assertDontSee('mailto:', false);
});

test('coverage flips start to continue and never writes completed_at', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = productionDisciplineRepairOrder($technician);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $before = InspectionCoverageProjection::for($repairOrder->fresh(), $technician);
    expect($before['started'])->toBeFalse()
        ->and($before['cta_label'])->toBe('Open Inspection')
        ->and($before['capture_url'])->toBe($before['walk_url'])
        ->and($before['capture_surface'])->toBe(\App\Ark\Operations\Inspections\InspectionCaptureSurfaceResolver::DESKTOP_WALK)
        ->and($before['posture_label'])->toBe('Not Started');

    $item = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    $after = InspectionCoverageProjection::for($repairOrder->fresh(), $technician);
    expect($after['started'])->toBeTrue()
        ->and($after['checked'])->toBe(1)
        ->and($after['total'])->toBeGreaterThan(1)
        ->and($after['cta_label'])->toStartWith('Continue Inspection')
        ->and($after['cta_label'])->toContain('points remaining')
        ->and($after['posture_key'])->toBe(\App\Ark\Operations\Inspections\InspectionPosture::IN_PROGRESS)
        ->and($after['posture_headline'])->toBe('In Progress')
        ->and($after['posture_label'])->toStartWith('In Progress · ')
        ->and($after['posture_detail'])->toContain('1 of '.$after['total'])
        ->and($after['posture_label'])->not->toContain('Done');

    $allItems = $inspection->fresh()->items()
        ->whereNotNull('inspection_template_item_id')
        ->get();

    // N/A addresses SM points without inventing pad/tread values in this coverage test.
    foreach ($allItems as $checklistItem) {
        if ($checklistItem->observed_state === InspectionObservedState::NotChecked) {
            $checklistItem->forceFill([
                'observed_state' => InspectionObservedState::Na->value,
            ])->save();
        }
    }

    $full = InspectionCoverageProjection::for($repairOrder->fresh(), $technician);
    expect($full['checked'])->toBe($full['total'])
        ->and($full['posture_key'])->toBe(\App\Ark\Operations\Inspections\InspectionPosture::COMPLETE)
        ->and($full['posture_label'])->toBe('Complete')
        ->and($full['cta_label'])->toBe('Open Inspection');

    $session = Inspection::query()->where('repair_order_id', $repairOrder->id)->sole();
    expect($session->completed_at)->toBeNull();
});

test('advisor inspection review tab still works on estimate review', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = productionDisciplineRepairOrder();

    app(EnsureInspectionAction::class)->execute($repairOrder, $advisor);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Inspection', false);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'inspect',
        ]))
        ->assertOk()
        ->assertSee('Open Inspection', false)
        ->assertDontSee('ops-inspection-walk', false);
});

test('advisor inspection tab hosts capture entry and template choice', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = productionDisciplineRepairOrder();

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'inspect',
        ]))
        ->assertOk()
        ->assertSee('data-inspection-entry', false)
        ->assertSee('data-inspection-cta-capture', false)
        ->assertSee('Open Inspection', false)
        ->assertSee('data-inspection-template-select', false)
        ->assertDontSee('data-inspection-cta-workspace', false)
        ->assertDontSee('Start Inspection', false)
        ->getContent();

    preg_match('/<a\b[^>]*\bdata-inspection-cta-capture\b[^>]*>/', $html, $matches);

    expect($matches[0] ?? '')
        ->not->toBe('')
        ->toContain('target="_blank"')
        ->toContain('data-inspection-cta-capture');

    expect($html)->toContain('>Open Inspection</a>');
});

test('unauthorized technician cannot record on production inspection', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $other = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = productionDisciplineRepairOrder($other);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $other);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $other);
    $item = $inspection->items()->firstOrFail();

    $this->actingAs($technician)
        ->patchJson(route('operations.repair-orders.inspection.points.update', [$repairOrder, $item]), [
            'status' => InspectionChecklistStatus::Good->value,
        ])
        ->assertForbidden();
});

test('advisor can reset inspection walk; technician cannot', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = productionDisciplineRepairOrder($technician);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $advisor);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $advisor);
    $item = $inspection->items()->where('label', 'LF Tire')->firstOrFail();

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Failed,
        actor: $advisor,
        notes: 'Worn',
        measurements: [
            ['key' => 'outer', 'name' => 'Outer', 'value' => '3', 'unit' => '/32"'],
            ['key' => 'center', 'name' => 'Center', 'value' => '3', 'unit' => '/32"'],
            ['key' => 'inner', 'name' => 'Inner', 'value' => '3', 'unit' => '/32"'],
        ],
    );

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.inspection.reset', $repairOrder), ['confirm' => '1'])
        ->assertForbidden();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'inspect',
        ]))
        ->assertOk()
        ->assertSee('Reset inspection walk', false)
        ->assertSee('Reset walk', false);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.inspection.reset', $repairOrder), ['confirm' => '1'])
        ->assertRedirect();

    $item->refresh();
    expect($item->observed_state)->toBe(InspectionObservedState::NotChecked)
        ->and($item->notes)->toBeNull()
        ->and($item->measurements()->count())->toBe(0);

    $coverage = InspectionCoverageProjection::for($repairOrder->fresh(), $advisor);
    expect($coverage['checked'])->toBe(0)
        ->and($coverage['posture_label'])->toBe('Not Started');
});

test('walk photos use lightbox trigger for enlarge', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = productionDisciplineRepairOrder($technician);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);
    $item = $inspection->items()->where('label', 'LF Tire')->firstOrFail();

    $item->photos()->create([
        'storage_path' => 'inspection-tests/tread.jpg',
        'content_type' => 'image/jpeg',
        'purpose' => 'internal',
        'original_name' => 'tread.jpg',
        'byte_size' => 1200,
    ]);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', [
            'repairOrder' => $repairOrder,
            'point' => $item->id,
        ]))
        ->assertOk()
        ->assertSee('data-ops-lightbox', false)
        ->assertSee('View photo larger', false);
});

test('customer portal inspection delivery remains unchanged', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = productionDisciplineRepairOrder();
    $plainToken = str_repeat('p', 64);

    InspectionAccessToken::createForPlainToken($repairOrder, $plainToken, [
        'created_by_user_id' => $advisor->id,
        'expires_at' => now()->addDay(),
    ]);

    $this->get(route('portal.inspections.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Vehicle Inspection', false)
        ->assertDontSee('Start Inspection', false)
        ->assertDontSee('ops-inspection-walk', false);
});

function productionDisciplineRepairOrder(?User $assignedTechnician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Discipline',
        'last_name' => 'Customer',
        'phone' => '7195554400',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'DVI1',
        'year' => 2014,
        'make' => 'Subaru',
        'model' => 'Outback',
        'vin' => '4S4BRBCC5E1234567',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Production discipline inspection.',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Check engine light',
        'disposition' => 'draft',
        'position' => 0,
    ]);

    if ($assignedTechnician !== null) {
        $repairOrder->forceFill(['assigned_technician_id' => $assignedTechnician->id])->save();
    }

    return $repairOrder->fresh(['vehicle', 'customer']);
}
