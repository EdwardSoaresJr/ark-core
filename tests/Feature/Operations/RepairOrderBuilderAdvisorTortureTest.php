<?php

use App\Ark\Operations\Evidence\AttachEvidenceAction;
use App\Ark\Operations\Evidence\EvidenceSource;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\RepairOrders\WorksheetMutationIdempotency;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
    Cache::flush();
    Storage::fake('local');
});

test('concern store is idempotent for the same worksheet key', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();
    $key = 'concern-idem-1';

    $payload = [
        'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
        'summary' => 'Noise when turning',
        'observed_summary' => 'Noise when turning',
        WorksheetMutationIdempotency::FIELD => $key,
    ];

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), $payload)
        ->assertRedirect()
        ->assertSessionHas('status', 'Saved');

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), $payload)
        ->assertRedirect()
        ->assertSessionHas('status', 'Saved');

    expect(RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);
});

test('line store is idempotent for the same worksheet key', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'Brake concern',
            'observed_summary' => 'Brake concern',
        ])
        ->assertRedirect();

    $concernId = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->value('id');
    $key = 'line-idem-1';
    $payload = [
        'repair_order_concern_id' => $concernId,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Diagnose brake noise',
        'quantity' => '1.00',
        'unit_price' => '145.00',
        WorksheetMutationIdempotency::FIELD => $key,
    ];

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), $payload)
        ->assertRedirect()
        ->assertSessionHas('status', 'Saved');

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), $payload)
        ->assertRedirect()
        ->assertSessionHas('status', 'Saved');

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);
});

test('engine oil package double add stays one package', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder), [
            'reset_reminder' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Saved');

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder), [
            'reset_reminder' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Saved');

    $packageLines = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Package->value)
        ->count();

    expect($packageLines)->toBe(1);
});

test('builder torture sequence reconciles after rapid mutations and refresh', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'Torture concern A',
            'observed_summary' => 'Torture concern A',
            WorksheetMutationIdempotency::FIELD => 'torture-a',
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'Torture concern B',
            'observed_summary' => 'Torture concern B',
            WorksheetMutationIdempotency::FIELD => 'torture-b',
        ])
        ->assertRedirect();

    $concernA = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('summary', 'Torture concern A')
        ->firstOrFail();
    $concernB = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('summary', 'Torture concern B')
        ->firstOrFail();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concernA->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Labor A',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            WorksheetMutationIdempotency::FIELD => 'torture-labor-a',
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concernB->id,
            'type' => RepairOrderLineType::Part->value,
            'description' => 'Part B',
            'quantity' => '2.00',
            'unit_price' => '25.00',
            'pricing_mode' => 'manual',
            WorksheetMutationIdempotency::FIELD => 'torture-part-b',
        ])
        ->assertRedirect();

    $laborA = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', 'Labor A')
        ->firstOrFail();

    $this->actingAs($advisor)
        ->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $laborA]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Saved');

    $partB = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', 'Part B')
        ->firstOrFail();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.update', [$repairOrder, $partB]), [
            'repair_order_concern_id' => $concernB->id,
            'type' => RepairOrderLineType::Part->value,
            'description' => 'Part B',
            'quantity' => '3.00',
            'unit_price' => '25.00',
            'pricing_mode' => 'manual',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Saved');

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder))
        ->assertRedirect();

    // Replay first concern create — must not duplicate.
    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'Torture concern A',
            'observed_summary' => 'Torture concern A',
            WorksheetMutationIdempotency::FIELD => 'torture-a',
        ])
        ->assertRedirect();

    // Oil package owns its own concern; advisor concerns A/B must stay exactly two.
    expect(RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->whereIn('summary', ['Torture concern A', 'Torture concern B'])
        ->count())->toBe(2)
        ->and(RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->count())->toBeGreaterThanOrEqual(3)
        ->and(RepairOrderLine::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('description', 'Labor A')
            ->exists())->toBeFalse()
        ->and((float) $partB->fresh()->quantity)->toBe(3.0);

    $concernB->update(['disposition' => RepairOrderConcernDisposition::Recommended]);

    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh(['lines.concern']));

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Torture concern A', false)
        ->assertSee('Torture concern B', false)
        ->assertSee('Part B', false)
        ->assertSee('Engine Oil Service', false)
        ->assertSee('id="estimate-total-panel"', false)
        ->assertDontSee('Labor A', false);

    expect($totals->totalCents())->toBeGreaterThan(0)
        ->and(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->whereNull('repair_order_concern_id')->count())->toBe(0);
});

test('advisor builder workflow keeps projections and totals coherent through approve path', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'Workflow brakes',
            'observed_summary' => 'Workflow brakes',
            WorksheetMutationIdempotency::FIELD => 'workflow-concern',
        ])
        ->assertRedirect();

    $concern = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->latest('id')
        ->firstOrFail();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Replace pads',
            'quantity' => '1.50',
            'unit_price' => '145.00',
            WorksheetMutationIdempotency::FIELD => 'workflow-labor',
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Part->value,
            'description' => 'Pad set',
            'quantity' => '1.00',
            'unit_price' => '68.00',
            'pricing_mode' => 'manual',
            WorksheetMutationIdempotency::FIELD => 'workflow-part',
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder))
        ->assertRedirect();

    app(AttachEvidenceAction::class)->handle(
        $repairOrder,
        $concern,
        UploadedFile::fake()->image('pad-wear.jpg'),
        $advisor,
        EvidenceSource::Upload,
        'Outer pad wear',
        false,
    );

    $part = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', 'Pad set')
        ->firstOrFail();

    $this->actingAs($advisor)
        ->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $part]))
        ->assertRedirect();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
            'disposition' => RepairOrderConcernDisposition::Approved->value,
        ])
        ->assertRedirect();

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved);

    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh(['lines.concern']));

    $page = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Workflow brakes', false)
        ->assertSee('Replace pads', false)
        ->assertDontSee('Pad set', false)
        ->assertSee('Engine Oil Service', false)
        ->assertSee('Photo', false)
        ->assertSee('id="estimate-total-panel"', false);

    expect($totals->totalCents())->toBeGreaterThan(0)
        ->and(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBeGreaterThanOrEqual(2)
        ->and(RepairOrderConcern::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('summary', 'Workflow brakes')
            ->count())->toBe(1);

    // Server flash vocabulary for Builder mutations.
    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'Second workflow concern',
            'observed_summary' => 'Second workflow concern',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Saved');

    expect($page->getContent())->toContain('data-compose-save-clarity');
});
