<?php

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Maintenance\AddEngineOilServiceAction;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
});

test('builder continuity config always includes totals panel authority', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('id="estimate-total-panel"', false)
        ->assertSee('id="estimate-builder-rail"', false)
        ->assertSee('id="worksheet-status-flash"', false)
        ->assertSee("continuityPanelIds: ['worksheet-status-flash', 'ro-identity-band', 'visit-reason', 'estimate-builder-rail', 'estimate-total-panel', 'review-toolbar', 'workspace-modal-host', 'ro-orientation-header']", false)
        ->assertSee('data-worksheet-root', false)
        ->assertSee('submitWorksheetForm', false)
        ->assertSee('+ Add Work', false)
        ->assertSee('id="workspace-modal-host"', false)
        ->assertSee('What would you like to add?', false)
        ->assertDontSee('Not saved until you create the concern', false);
});

test('concern validation failure creates no partial concern and surfaces the error', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();
    $tooLong = str_repeat('x', 2001);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->followingRedirects()
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => $tooLong,
            'observed_summary' => $tooLong,
        ])
        ->assertOk()
        ->assertSee('data-concern-error', false);

    expect(RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(0);
});

test('builder mutation path persists concern labor part and matching totals after reload', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Front brakes grinding',
            'observed_summary' => 'Front brakes grinding',
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
            'description' => 'Replace front brake pads',
            'quantity' => '1.50',
            'unit_price' => '145.00',
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Part->value,
            'description' => 'Front brake pad set',
            'quantity' => '1.00',
            'unit_price' => '68.00',
            'pricing_mode' => 'manual',
        ])
        ->assertRedirect();

    $labor = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Labor->value)
        ->firstOrFail();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.update', [$repairOrder, $labor]), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Replace front brake pads',
            'quantity' => '2.00',
            'labor_entered_hours' => '2.00',
            'unit_price' => '145.00',
        ])
        ->assertRedirect();

    expect(RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1)
        ->and(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(2)
        ->and((float) $labor->fresh()->quantity)->toBe(2.0);

    $concern->update(['disposition' => RepairOrderConcernDisposition::Recommended]);

    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh(['lines.concern']));

    expect($totals->totalCents())->toBeGreaterThan(0);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Front brakes grinding', false)
        ->assertSee('Replace front brake pads', false)
        ->assertSee('Front brake pad set', false)
        ->assertSee('id="estimate-total-panel"', false);

    $part = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Part->value)
        ->firstOrFail();

    $this->actingAs($advisor)
        ->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $part]))
        ->assertRedirect();

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);

    $afterDelete = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh(['lines.concern']));
    expect($afterDelete->totalCents())->toBeLessThan($totals->totalCents());
});

test('evidence arrival and package projections do not break builder rendering', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    app(AddEngineOilServiceAction::class)->handle($repairOrder, $advisor);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'Customer concern with oil service present',
            'observed_summary' => 'Customer concern with oil service present',
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Engine Oil Service', false)
        ->assertSee('aria-label="Add Photo"', false)
        ->assertSee('id="estimate-total-panel"', false)
        ->assertSee('Customer concern with oil service present', false);

    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh(['lines.concern']));

    expect($totals->grossPackageCents())->toBeGreaterThan(0)
        ->and($totals->grossLaborCents())->toBe(0)
        ->and($totals->totalCents())->toBeGreaterThanOrEqual($totals->grossPackageCents());
});

test('line store validation failure leaves no orphan line', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'Validation isolation concern',
            'observed_summary' => 'Validation isolation concern',
        ])
        ->assertRedirect();

    $concernId = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->value('id');

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concernId,
            'type' => RepairOrderLineType::Labor->value,
            // description missing — must fail validation without creating a line
            'quantity' => '1.00',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('description');

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(0)
        ->and(RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);
});
