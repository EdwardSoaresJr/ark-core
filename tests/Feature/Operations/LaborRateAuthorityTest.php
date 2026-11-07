<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\LaborRateOverrideReason;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\EstimatePricing\UpsertLaborPolicyAction;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\WorkTemplates\ApplyWorkTemplateAction;
use App\Ark\Operations\WorkTemplates\WorkTemplate;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

function laborRateRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Labor', 'phone' => '555-0141']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2019, 'make' => 'Honda', 'model' => 'Accord']);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Synthetic labor rate',
    ]);
}

function laborRateConcern(RepairOrder $repairOrder, ConcernBillingPosture $posture, string $summary = 'Concern'): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => $repairOrder->concerns()->count() + 1,
        'billing_posture' => $posture,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function laborRateStore(RepairOrder $repairOrder, RepairOrderConcern $concern, string $category, array $overrides = []): RepairOrderLine
{
    $description = 'Labor '.uniqid();

    test()->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => $description,
        'labor_category_key' => $category,
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => 'normal',
        'labor_rate_overridden' => '0',
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return RepairOrderLine::query()->where('description', $description)->sole();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function laborRatePatch(RepairOrder $repairOrder, RepairOrderLine $line, array $overrides = []): TestResponse
{
    $line = $line->fresh();

    return test()->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'type' => 'labor',
        'description' => $line->description,
        'labor_category_key' => $line->labor_category_key,
        'labor_entered_hours' => (string) $line->labor_entered_hours,
        'labor_adjustment' => 'normal',
        'unit_price' => number_format($line->unit_price_cents / 100, 2, '.', ''),
        'labor_rate_overridden' => '0',
        ...$overrides,
    ]);
}

function laborRateRaiseCustomerPayPolicy(float $rate): void
{
    app(UpsertLaborPolicyAction::class)->execute(
        ConcernBillingPosture::CustomerPay,
        OperationClass::query()->where('key', 'general_repair')->firstOrFail(),
        $rate,
        Carbon::today(),
        'synthetic policy change',
    );
}

test('a rate typed on a Warranty program category is rejected instead of silently discarded', function () {
    $repairOrder = laborRateRepairOrder();
    $line = laborRateStore($repairOrder, laborRateConcern($repairOrder, ConcernBillingPosture::WarrantyOther), 'warranty-other');

    expect($line->unit_price_cents)->toBe(16500);

    laborRatePatch($repairOrder, $line, [
        'unit_price' => '150.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => LaborRateOverrideReason::CompetitiveMatch->value,
    ])->assertSessionHasErrors(['unit_price' => 'Warranty - Other labor is billed at its policy rate ($165.00/hr) and its rate cannot be overridden. Change the concern billing or the labor category instead.'])
        ->assertSessionMissing('status');

    expect($line->fresh()->unit_price_cents)->toBe(16500);
});

test('an authorized labor rate override persists with its reason, time and user', function () {
    $repairOrder = laborRateRepairOrder();
    $line = laborRateStore($repairOrder, laborRateConcern($repairOrder, ConcernBillingPosture::CustomerPay), 'mechanical');

    laborRatePatch($repairOrder, $line, [
        'unit_price' => '150.00',
        'labor_rate_overridden' => '1',
    ])->assertSessionHasErrors('labor_rate_override_reason');

    laborRatePatch($repairOrder, $line, [
        'unit_price' => '150.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => LaborRateOverrideReason::CompetitiveMatch->value,
    ])->assertSessionHasNoErrors();

    $line = $line->fresh();
    expect($line->unit_price_cents)->toBe(15000)
        ->and($line->labor_rate_cents)->toBe(15000)
        ->and($line->policy_resolved_labor_rate_cents)->toBe(16500)
        ->and($line->labor_rate_override_reason)->toBe('competitive_match')
        ->and($line->labor_rate_overridden_by_user_id)->toBe($this->advisor->id)
        ->and($line->labor_rate_overridden_at)->not->toBeNull()
        ->and($line->subtotal_cents)->toBe(30000);

    laborRatePatch($repairOrder, $line, ['description' => 'Renamed labor'])->assertSessionHasNoErrors();

    expect($line->fresh())
        ->unit_price_cents->toBe(15000)
        ->labor_rate_override_reason->toBe('competitive_match');
});

test('a labor rate override needs pricing authority', function () {
    $repairOrder = laborRateRepairOrder();
    $line = laborRateStore($repairOrder, laborRateConcern($repairOrder, ConcernBillingPosture::CustomerPay), 'mechanical');

    $withoutPricing = User::factory()->create();
    $withoutPricing->givePermissionTo(
        Role::findByName(ArkRole::Advisor->value, 'web')->permissions
            ->reject(fn ($permission): bool => $permission->name === ArkCapability::PricingOverride->value),
    );
    $this->actingAs($withoutPricing);

    laborRatePatch($repairOrder, $line, [
        'unit_price' => '150.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => LaborRateOverrideReason::CompetitiveMatch->value,
    ])->assertSessionHasErrors(['unit_price' => 'Manual pricing overrides require pricing authority.']);

    expect($line->fresh()->unit_price_cents)->toBe(16500);

    laborRatePatch($repairOrder, $line, ['labor_entered_hours' => '3.00'])->assertSessionHasNoErrors();

    expect($line->fresh())->unit_price_cents->toBe(16500)->quantity->toBe('3.00');
});

test('use policy rate clears an override and resolves the current policy rate', function () {
    $repairOrder = laborRateRepairOrder();
    $line = laborRateStore($repairOrder, laborRateConcern($repairOrder, ConcernBillingPosture::CustomerPay), 'mechanical');
    laborRatePatch($repairOrder, $line, [
        'unit_price' => '150.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => LaborRateOverrideReason::CompetitiveMatch->value,
    ])->assertSessionHasNoErrors();

    laborRateRaiseCustomerPayPolicy(175.00);

    laborRatePatch($repairOrder, $line, [
        'unit_price' => '175.00',
        'reprice_labor' => '1',
        'labor_rate_override_reason' => '',
    ])->assertSessionHasNoErrors();

    expect($line->fresh())
        ->unit_price_cents->toBe(17500)
        ->labor_rate_cents->toBe(17500)
        ->policy_resolved_labor_rate_cents->toBe(17500)
        ->labor_rate_override_reason->toBeNull()
        ->labor_rate_overridden_at->toBeNull()
        ->labor_rate_overridden_by_user_id->toBeNull();
});

test('every labor entry surface shows the labor policy rate the server saves', function () {
    laborRateRaiseCustomerPayPolicy(175.00);
    $settings = ShopSettings::current();

    expect($settings->laborDefaultsForConcern(ConcernBillingPosture::CustomerPay)['rate'])->toBe('175.00')
        ->and($settings->billingPostureOptionPresentation(ConcernBillingPosture::CustomerPay)['rate'])->toBe('175.00')
        ->and(collect($settings->laborCategoriesForBillingPosture(ConcernBillingPosture::CustomerPay))->firstWhere('key', 'mechanical')['rate_cents'])->toBe(17500)
        ->and(collect($settings->laborCategoriesForBillingPosture(ConcernBillingPosture::RepairPal))->firstWhere('key', 'repairpal')['rate_cents'])->toBe(15000)
        ->and(collect($settings->laborCategories())->firstWhere('key', 'mechanical')['rate_cents'])->toBe(16500);

    $repairOrder = laborRateRepairOrder();
    $concern = laborRateConcern($repairOrder, ConcernBillingPosture::CustomerPay);
    $line = laborRateStore($repairOrder, $concern, 'mechanical', ['unit_price' => '175.00']);

    expect($line->unit_price_cents)->toBe(17500);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('defaultLaborRate: \'175.00\'', false)
        ->assertSee('\\u0022key\\u0022:\\u0022mechanical\\u0022,\\u0022name\\u0022:\\u0022Mechanical\\u0022,\\u0022rate_cents\\u0022:17500', false);
});

test('an existing line keeps its rate snapshot and its editor shows that saved rate', function () {
    $repairOrder = laborRateRepairOrder();
    $line = laborRateStore($repairOrder, laborRateConcern($repairOrder, ConcernBillingPosture::CustomerPay), 'mechanical');

    laborRateRaiseCustomerPayPolicy(175.00);

    $this->get(route('operations.repair-orders.show', ['repairOrder' => $repairOrder, 'editing_line' => $line->id]))
        ->assertOk()
        ->assertSee("sell: '165.00'", false)
        ->assertSee('laborRatePinned: true', false)
        ->assertSee('name="reprice_labor"', false)
        ->assertSee('data-use-policy-labor-rate', false);

    laborRatePatch($repairOrder, $line, ['labor_entered_hours' => '3.00'])->assertSessionHasNoErrors();

    expect($line->fresh())
        ->unit_price_cents->toBe(16500)
        ->quantity->toBe('3.00')
        ->subtotal_cents->toBe(49500);
});

test('a work template labor price is kept as a menu price on every concern', function () {
    $repairOrder = laborRateRepairOrder();
    $customerPay = laborRateConcern($repairOrder, ConcernBillingPosture::CustomerPay, 'Customer pay');
    $repairPal = laborRateConcern($repairOrder, ConcernBillingPosture::RepairPal, 'RepairPal');

    $template = WorkTemplate::query()->create(['title' => 'Pre-purchase inspection']);
    $template->lines()->create(['type' => 'labor', 'description' => 'Pre-purchase inspection', 'quantity' => '1.00', 'unit_price_cents' => 19900, 'position' => 1]);

    app(ApplyWorkTemplateAction::class)->handle($repairOrder->fresh(), $template, $this->advisor, $customerPay);
    app(ApplyWorkTemplateAction::class)->handle($repairOrder->fresh(), $template, $this->advisor, $repairPal);

    $customerPayLine = RepairOrderLine::query()->where('repair_order_concern_id', $customerPay->id)->where('type', 'labor')->sole();
    $repairPalLine = RepairOrderLine::query()->where('repair_order_concern_id', $repairPal->id)->where('type', 'labor')->sole();

    expect($customerPayLine)
        ->unit_price_cents->toBe(19900)
        ->policy_resolved_labor_rate_cents->toBe(16500)
        ->labor_rate_override_reason->toBe(LaborRateOverrideReason::MenuOrPackage->value)
        ->and($repairPalLine)
        ->labor_category_key->toBe('repairpal')
        ->unit_price_cents->toBe(19900)
        ->policy_resolved_labor_rate_cents->toBe(15000)
        ->labor_rate_override_reason->toBe(LaborRateOverrideReason::MenuOrPackage->value);
});
