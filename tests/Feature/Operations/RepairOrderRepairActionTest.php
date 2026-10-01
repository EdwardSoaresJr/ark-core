<?php

use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderEstimate;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\DB;

test('advisor can create a repair action and attach supporting lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $response = $this->post(route('operations.repair-orders.concerns.work-groups.store', [$repairOrder, $concern]), [
        'title' => 'Replace Water Pump',
    ]);

    $workGroup = RepairOrderWorkGroup::query()->first();
    $response
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#repair-action-'.$workGroup->id)
        ->assertSessionHas('status', 'Repair added.');
    expect($workGroup)->not->toBeNull()
        ->and($workGroup->title)->toBe('Replace Water Pump')
        ->and($workGroup->repair_order_concern_id)->toBe($concern->id);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Replace water pump',
        'quantity' => '2.00',
        'unit_price' => '150.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#repair-action-'.$workGroup->id);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Water pump',
        'quantity' => '1.00',
        'part_cost' => '120.00',
        'unit_price' => '180.00',
        'pricing_mode' => 'manual',
        'unit_price_override' => '1',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#repair-action-'.$workGroup->id);

    $concern->refresh()->load(['workGroups.lines', 'lines']);

    expect($concern->workGroups)->toHaveCount(1)
        ->and($concern->lines)->toHaveCount(2)
        ->and($concern->lines->every(fn ($line) => $line->repair_order_work_group_id === $workGroup->id))->toBeTrue();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('What are we doing?', false)
        ->assertDontSee('+ Another Repair', false)
        ->assertSee('+ Add Work', false)
        ->assertSee('Replace Water Pump')
        ->assertSee('2 hr ·', false)
        ->assertSee('Add Part', false)
        ->assertSee('Water pump');
});

test('multiple labor lines under one repair keep distinct descriptions', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);
    $workGroup = $concern->workGroups()->create([
        'title' => 'Engine Replacement',
        'position' => 1,
    ]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Remove Engine',
        'quantity' => '4.00',
        'unit_price' => '150.00',
    ])->assertRedirect();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Install Engine',
        'quantity' => '6.00',
        'unit_price' => '150.00',
    ])->assertRedirect();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Remove Engine')
        ->assertSee('Install Engine')
        ->assertDontSee('4 hr · Shop Default', false);
});

test('standalone notes can remain outside repair actions', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $workGroup = $concern->workGroups()->create([
        'title' => 'Replace Belt',
        'position' => 1,
    ]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Replace belt',
        'quantity' => '0.50',
        'unit_price' => '150.00',
    ]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note->value,
        'description' => 'Further testing required before additional repairs.',
        'quantity' => '1.00',
        'unit_price' => '0.00',
    ]);

    $concern->refresh()->load('lines');

    expect($concern->lines->whereNull('repair_order_work_group_id'))->toHaveCount(1)
        ->and($concern->lines->where('repair_order_work_group_id', $workGroup->id))->toHaveCount(1);
});

test('repair actions do not change authoritative estimate math', function () {
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $ungroupedOrder = repairOrderForEstimateWorkspace();
    $ungroupedConcern = concernForEstimateWorkspace($ungroupedOrder);

    $linePayloads = [
        [
            'type' => RepairOrderLineType::Labor,
            'description' => 'Replace water pump',
            'quantity' => '2.00',
            'unit_price_cents' => 15000,
        ],
        [
            'type' => RepairOrderLineType::Part,
            'description' => 'Water pump',
            'quantity' => '1.00',
            'unit_price_cents' => 18000,
            'part_cost_cents' => 12000,
        ],
        [
            'type' => RepairOrderLineType::Part,
            'description' => 'Coolant',
            'quantity' => '1.00',
            'unit_price_cents' => 2500,
            'part_cost_cents' => 1500,
        ],
    ];

    foreach ($linePayloads as $payload) {
        $ungroupedOrder->lines()->create([
            'repair_order_concern_id' => $ungroupedConcern->id,
            ...$payload,
            'subtotal_cents' => app(EstimateTotalsCalculator::class)->lineTotalCents(
                $payload['quantity'],
                $payload['unit_price_cents'],
            ),
        ]);
    }

    $workGroup = $concern->workGroups()->create([
        'title' => 'Replace Water Pump',
        'position' => 1,
    ]);

    foreach ($linePayloads as $payload) {
        $repairOrder->lines()->create([
            'repair_order_concern_id' => $concern->id,
            'repair_order_work_group_id' => $workGroup->id,
            ...$payload,
            'subtotal_cents' => app(EstimateTotalsCalculator::class)->lineTotalCents(
                $payload['quantity'],
                $payload['unit_price_cents'],
            ),
        ]);
    }

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ungroupedOrder);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder);

    $ungroupedTotals = app(RepairOrderEstimate::class)->totalsFor($ungroupedOrder->fresh());
    $groupedTotals = app(RepairOrderEstimate::class)->totalsFor($repairOrder->fresh());

    expect($groupedTotals->laborCents())->toBe($ungroupedTotals->laborCents())
        ->and($groupedTotals->partsCents())->toBe($ungroupedTotals->partsCents())
        ->and($groupedTotals->totalCents())->toBe($ungroupedTotals->totalCents());
});

test('diagnostic repair actions can start with a note before labor exists', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $workGroup = $concern->workGroups()->create([
        'title' => 'Further Electrical Testing',
        'position' => 1,
    ]);

    expect($workGroup->allowedComposerLineTypes())->toContain(RepairOrderLineType::Note)
        ->and($workGroup->allowedComposerLineTypes())->not->toContain(RepairOrderLineType::Part);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Note->value,
        'description' => 'Scope additional circuits before quoting repairs.',
        'quantity' => '1.00',
        'unit_price' => '0.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#repair-action-'.$workGroup->id);

    $workGroup->refresh()->load('lines');

    expect($workGroup->hasLaborAnchor())->toBeFalse()
        ->and($workGroup->lines)->toHaveCount(1)
        ->and($workGroup->allowedComposerLineTypes())->toContain(RepairOrderLineType::Labor);
});

test('advisor can attach a sublet with vendor cost and sell price under a repair action', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);
    $workGroup = $concern->workGroups()->create([
        'title' => 'Machine flywheel',
        'position' => 1,
    ]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'R&R flywheel',
        'quantity' => '1.50',
        'unit_price' => '150.00',
    ])->assertRedirect();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Sublet->value,
        'description' => 'Machine flywheel at local shop',
        'quantity' => '1.00',
        'part_cost' => '85.00',
        'unit_price' => '125.00',
    ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#repair-action-'.$workGroup->id)
        ->assertSessionHas('status', 'Saved');

    $sublet = $repairOrder->fresh()->lines()->where('type', RepairOrderLineType::Sublet)->first();

    expect($sublet)->not->toBeNull()
        ->and($sublet->description)->toBe('Machine flywheel at local shop')
        ->and($sublet->part_cost_cents)->toBe(8500)
        ->and($sublet->unit_price_cents)->toBe(12500)
        ->and($sublet->repair_order_work_group_id)->toBe($workGroup->id);
});

test('labor-anchored repair actions still offer add labor for additional hours lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $workGroup = $concern->workGroups()->create([
        'title' => 'Replace Timing Cover Gasket',
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace Timing Cover Gasket',
        'quantity' => '3.50',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 52500,
        'total_cents' => 52500,
    ]);

    $workGroup->refresh()->load('lines');

    expect($workGroup->hasLaborAnchor())->toBeTrue()
        ->and($workGroup->allowedComposerLineTypes())->toContain(RepairOrderLineType::Labor)
        ->and($workGroup->allowedComposerLineTypes())->toContain(RepairOrderLineType::Part);
});

test('estimate snapshot includes repair action grouping without changing flat line totals', function () {
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $workGroup = $concern->workGroups()->create([
        'title' => 'Replace Water Pump',
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace water pump',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh());

    expect($snapshot['concerns'][0]['work_groups'][0]['title'])->toBe('Replace Water Pump')
        ->and($snapshot['concerns'][0]['work_groups'][0]['lines'])->toHaveCount(1)
        ->and($snapshot['concerns'][0]['lines'])->toHaveCount(1)
        ->and($snapshot['concerns'][0]['lines'][0]['repair_order_work_group_id'])->toBe($workGroup->id);
});

test('repair action titles preserve ampersands in builder and snapshot', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $this->post(route('operations.repair-orders.concerns.work-groups.store', [$repairOrder, $concern]), [
        'title' => 'Front Brakes & Rotors',
    ])->assertRedirect();

    $workGroup = RepairOrderWorkGroup::query()->first();

    expect($workGroup?->title)->toBe('Front Brakes & Rotors');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Front Brakes &amp; Rotors', false);

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh());

    expect($snapshot['concerns'][0]['work_groups'][0]['title'])->toBe('Front Brakes & Rotors');
});

test('legacy percent conjunction in work group title renders correctly on read', function (): void {
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $workGroupId = DB::table('repair_order_work_groups')->insertGetId([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Front Brakes % Rotors',
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $workGroup = RepairOrderWorkGroup::query()->findOrFail($workGroupId);

    DB::table('repair_order_lines')->insert([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Front Brakes % Rotors',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($workGroup->fresh()->title)->toBe('Front Brakes & Rotors');

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh());

    expect($snapshot['concerns'][0]['work_groups'][0]['title'])->toBe('Front Brakes & Rotors')
        ->and($snapshot['concerns'][0]['work_groups'][0]['lines'][0]['description'])->toBe('Front Brakes & Rotors');
});
