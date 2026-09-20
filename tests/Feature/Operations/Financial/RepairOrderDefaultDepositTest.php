<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\RepairOrderDefaultDepositCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('default deposit and breakdown include recommended parts when approved work also exists', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => true,
        'default_deposit_diagnostic_labor_category_keys' => ['diagnostic'],
    ]);

    $repairOrder = defaultDepositRepairOrder();

    $approvedConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved diagnostic',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => RecommendationIntent::Diagnostic,
        'position' => 0,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $approvedConcern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Approved cooling diagnostic',
        'labor_category_key' => 'diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    $repairOrder = $repairOrder->fresh(['lines.concern', 'concerns']);

    // Estimate Total drops Recommended once Approved exists…
    $billable = app(EstimateTotalsCalculator::class)->billableEstimateLines($repairOrder->lines);
    expect($billable->contains(fn ($line) => $line->description === 'Thermostat'))->toBeFalse();

    // …but deposit still quotes recommended parts + diagnostics.
    $result = app(RepairOrderDefaultDepositCalculator::class)->forRepairOrder($repairOrder);
    $workspace = app(RepairOrderDefaultDepositCalculator::class)->workspaceLines($repairOrder);

    expect($result->partsCents)->toBe(12000)
        ->and($result->diagnosticsCents)->toBe(16500 + 15000)
        ->and($result->totalCents)->toBe(43500)
        ->and(collect($workspace)->pluck('description')->all())
        ->toContain('Thermostat')
        ->toContain('Cooling system diagnostic')
        ->toContain('Approved cooling diagnostic');
});

test('default deposit sums parts and diagnostic labor on the estimate', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => true,
        'default_deposit_diagnostic_labor_category_keys' => ['diagnostic'],
    ]);

    $repairOrder = defaultDepositRepairOrder();

    $result = app(RepairOrderDefaultDepositCalculator::class)->forRepairOrder($repairOrder);

    expect($result->enabled)->toBeTrue()
        ->and($result->partsCents)->toBe(12000)
        ->and($result->diagnosticsCents)->toBe(16500)
        ->and($result->totalCents)->toBe(28500)
        ->and($result->lines)->toHaveCount(2)
        ->and(collect($result->lines)->pluck('description')->all())->toBe(['Cooling system diagnostic', 'Thermostat']);
});

test('default deposit ignores diagnostic labor on non diagnostics scopes', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => true,
        'default_deposit_diagnostic_labor_category_keys' => ['diagnostic'],
    ]);

    $repairOrder = defaultDepositRepairOrder(
        concernRecommendationIntent: RecommendationIntent::Maintenance,
    );

    $result = app(RepairOrderDefaultDepositCalculator::class)->forRepairOrder($repairOrder);

    expect($result->partsCents)->toBe(12000)
        ->and($result->diagnosticsCents)->toBe(0)
        ->and($result->totalCents)->toBe(12000);
});

test('default deposit ignores non diagnostic labor and deferred concerns', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => true,
    ]);

    $repairOrder = defaultDepositRepairOrder(withMechanicalLabor: true, withDeferredPart: true);

    $result = app(RepairOrderDefaultDepositCalculator::class)->forRepairOrder($repairOrder);

    expect($result->partsCents)->toBe(12000)
        ->and($result->diagnosticsCents)->toBe(16500)
        ->and($result->totalCents)->toBe(28500);
});

test('deposit workspace lists billable parts and labor with default inclusion', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => true,
    ]);

    $repairOrder = defaultDepositRepairOrder(withMechanicalLabor: true);

    $lines = app(RepairOrderDefaultDepositCalculator::class)->workspaceLines($repairOrder);

    expect($lines)->toHaveCount(3)
        ->and(collect($lines)->firstWhere('description', 'Thermostat')?->includedByDefault)->toBeTrue()
        ->and(collect($lines)->firstWhere('description', 'Cooling system diagnostic')?->includedByDefault)->toBeTrue()
        ->and(collect($lines)->firstWhere('description', 'Thermostat replacement')?->includedByDefault)->toBeFalse();
});

test('financial rail prefills suggested deposit amount', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => true,
    ]);

    $repairOrder = defaultDepositRepairOrder(status: RepairOrderStatus::InProgress);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Suggested deposit', false)
        ->assertSee('Breakdown', false)
        ->assertSee('$285.00', false)
        ->assertSee('e.g. 285.00', false)
        ->assertSee('Deposit line breakdown', false)
        ->assertSee('data-deposit-line-checkbox', false)
        ->assertSee('Cooling system diagnostic', false)
        ->assertSee('Thermostat', false);
});

test('admin can save deposit settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertSee('Deposits')
        ->assertSee('Save Deposit Settings');

    $this->patch(route('operations.settings.shop.deposits.update'), [
        'default_deposit_enabled' => '1',
        'default_deposit_include_parts' => '1',
        'default_deposit_include_diagnostics' => '1',
        'default_deposit_diagnostic_labor_category_keys' => ['diagnostic', 'programming'],
    ])->assertRedirect()
        ->assertSessionHas('status', 'Deposit settings saved.');

    $settings = ShopSettings::reloadCurrent();

    expect($settings->defaultDepositEnabled())->toBeTrue()
        ->and($settings->defaultDepositIncludeParts())->toBeTrue()
        ->and($settings->defaultDepositIncludeDiagnostics())->toBeTrue()
        ->and($settings->defaultDepositDiagnosticLaborCategoryKeys())->toBe(['diagnostic', 'programming']);
});

function defaultDepositRepairOrder(
    RepairOrderStatus $status = RepairOrderStatus::WaitingApproval,
    bool $withMechanicalLabor = false,
    bool $withDeferredPart = false,
    RecommendationIntent $concernRecommendationIntent = RecommendationIntent::Diagnostic,
): RepairOrder {
    $customer = Customer::query()->create([
        'first_name' => 'Deposit',
        'last_name' => 'Customer',
        'phone' => '555-0300',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Deposit policy test.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Cooling system',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => $concernRecommendationIntent,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Cooling system diagnostic',
        'labor_category_key' => 'diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Thermostat',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
    ]);

    if ($withMechanicalLabor) {
        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor,
            'description' => 'Thermostat replacement',
            'labor_category_key' => 'mechanical',
            'quantity' => '1.00',
            'unit_price_cents' => 9900,
        ]);
    }

    if ($withDeferredPart) {
        $deferredConcern = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Deferred work',
            'disposition' => RepairOrderConcernDisposition::Deferred,
            'position' => 2,
        ]);

        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $deferredConcern->id,
            'type' => RepairOrderLineType::Part,
            'description' => 'Deferred part',
            'quantity' => '1.00',
            'unit_price_cents' => 50000,
        ]);
    }

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return $repairOrder->fresh(['lines.concern', 'concerns']);
}
