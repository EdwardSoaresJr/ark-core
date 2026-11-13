<?php

use App\Ark\Operations\EstimatePricing\LaborBillingMinimumMode;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimums;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'labor_pricing_profile_version' => 0,
        'labor_billing_minimums' => null,
        'custom_labor_billing_classes' => null,
    ]);
});

test('labor settings show hybrid inline work-type matrix without a save button', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor',
        ]))
        ->assertOk()
        ->assertSee('Labor rate matrix')
        ->assertSee('data-labor-work-type-nav', false)
        ->assertSee('+ Add Work Type')
        ->assertSee('+ Add Labor Column')
        ->assertSee('Manage Billing Classes')
        ->assertSee('Default Work Type')
        ->assertSee('Rates for')
        ->assertSee('Billing Class')
        ->assertSee('Hourly Rate')
        ->assertSee('Min Hrs')
        ->assertSee('Round To')
        ->assertSee('>Per concern</option>', false)
        ->assertSee('data-labor-hourly-rate', false)
        ->assertSee('data-labor-min-hours', false)
        ->assertSee('data-labor-rounding', false)
        ->assertDontSee('Default Labor Rate')
        ->assertDontSee('Rate Type')
        ->assertDontSee('data-labor-rate-mode', false)
        ->assertDontSee('data-labor-default-rate', false)
        ->assertDontSee('Allow Modifiers')
        ->assertDontSee('Adjust Labor')
        ->assertDontSee('Save Labor Settings')
        ->assertDontSee('Standard hourly rate');
});

test('autosave rate endpoint updates Mechanical Retail cell and profile version', function () {
    $beforeDefault = (int) ShopSettings::current()->default_labor_rate_cents;

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => 'custom',
            'rate' => '175.00',
            'expected_version' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'saved')
        ->assertJsonPath('applies_to', 'new_repair_orders_only');

    expect((int) ShopSettings::current()->default_labor_rate_cents)->toBe($beforeDefault)
        ->and((int) ShopSettings::current()->labor_pricing_profile_version)->toBeGreaterThanOrEqual(1);

    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'general_repair',
    );

    expect($resolved->hourlyRateCents)->toBe(17500);
});

test('autosave minimum endpoint activates forward-only billing-class minimums', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.minimum'), [
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => LaborBillingMinimumMode::PerRepairOrder->value,
            'hours' => '1.00',
            'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            'expected_version' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'saved');

    $settings = ShopSettings::current();

    expect($settings->labor_billing_minimums['customer_pay']['mode'])->toBe('per_ro')
        ->and($settings->labor_billing_minimums['customer_pay']['hours'])->toBe('1.00')
        ->and((int) $settings->labor_pricing_profile_version)->toBeGreaterThanOrEqual(1);
});

test('add work type creates operation class and selectable labor category', function () {
    $this->actingAs($this->admin)
        ->postJson(route('operations.settings.shop.labor-matrix.work-types.store'), [
            'name' => 'EV High Voltage',
        ])
        ->assertOk()
        ->assertJsonPath('work_type.label', 'EV High Voltage');

    expect(OperationClass::query()->where('key', 'ev_high_voltage')->exists())->toBeTrue()
        ->and(collect(ShopSettings::current()->laborCategories())->pluck('key'))->toContain('ev_high_voltage');

    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'ev_high_voltage',
    );

    expect($resolved->hourlyRateCents)->toBe(16500);
});

test('add billing class seeds independent labor policies', function () {
    $this->actingAs($this->admin)
        ->postJson(route('operations.settings.shop.labor-matrix.billing-classes.store'), [
            'name' => 'Extended Warranty',
            'base_posture' => ConcernBillingPosture::WarrantyOther->value,
        ])
        ->assertOk()
        ->assertJsonPath('billing_class.label', 'Extended Warranty');

    $key = ShopSettings::current()->customLaborBillingClasses()[0]['key'];

    $resolved = app(LaborPolicyResolver::class)->resolve($key, 'general_repair');

    expect($resolved->hourlyRateCents)->toBe(16500)
        ->and($resolved->billingPosture)->toBe($key);
});

test('programming and troubleshooting resolve independently after split', function () {
    $this->artisan('migrate', ['--force' => true]);

    expect(OperationClass::query()->where('key', 'programming')->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'programming',
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => 'custom',
            'rate' => '195.00',
            'expected_version' => (int) ShopSettings::current()->labor_pricing_profile_version,
        ])
        ->assertOk();

    expect(app(LaborPolicyResolver::class)->resolve(ConcernBillingPosture::CustomerPay, 'programming')->hourlyRateCents)
        ->toBe(19500)
        ->and(app(LaborPolicyResolver::class)->resolve(ConcernBillingPosture::CustomerPay, 'diagnostics')->hourlyRateCents)
        ->not->toBe(19500);
});

test('custom billing class can be selected on a concern and prices new labor', function () {
    $this->actingAs($this->admin)
        ->postJson(route('operations.settings.shop.labor-matrix.billing-classes.store'), [
            'name' => 'Extended Warranty',
            'base_posture' => ConcernBillingPosture::WarrantyOther->value,
        ])
        ->assertOk();

    $key = ShopSettings::current()->customLaborBillingClasses()[0]['key'];

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => $key,
            'mode' => 'custom',
            'rate' => '122.00',
            'expected_version' => (int) ShopSettings::current()->labor_pricing_profile_version,
        ])
        ->assertOk();

    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $this->actingAs($this->admin)
        ->patch(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]), [
            'billing_posture' => $key,
            App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        ])
        ->assertRedirect();

    $concern->refresh();
    expect($concern->billingClassKey())->toBe($key)
        ->and($concern->billingClassLabel())->toBe('Extended Warranty');

    $priced = app(App\Ark\Operations\RepairOrders\RepairOrderLinePricing::class)->attributesFor([
        'type' => App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => App\Ark\Operations\Labor\LaborAdjustment::Normal->value,
    ], $repairOrder->fresh());

    expect($priced['labor_rate_cents'])->toBe(12200)
        ->and($priced['resolved_from_posture'])->toBe($key);
});

test('existing open RO keeps stamped rates for new labor after settings change', function () {
    ShopSettings::current()->update([
        'labor_pricing_profile_version' => 1,
        'default_labor_rate_cents' => 16500,
    ]);

    $repairOrder = repairOrderForFinancialAuthority();
    expect($repairOrder->labor_pricing_profile)->toBeArray()
        ->and($repairOrder->labor_pricing_profile['schema'] ?? 1)->toBeGreaterThanOrEqual(1);

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => 'custom',
            'rate' => '210.00',
            'expected_version' => (int) ShopSettings::current()->labor_pricing_profile_version,
        ])
        ->assertOk();

    $concern = concernForFinancialAuthority($repairOrder);
    $priced = app(App\Ark\Operations\RepairOrders\RepairOrderLinePricing::class)->attributesFor([
        'type' => App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => App\Ark\Operations\Labor\LaborAdjustment::Normal->value,
    ], $repairOrder->fresh());

    // Stamped profile (schema 2) freezes Mechanical×Retail; schema 1 falls back to as-of opened_at.
    if (($repairOrder->labor_pricing_profile['schema'] ?? 1) >= 2) {
        expect($priced['labor_rate_cents'])->toBe(16500);
    } else {
        expect($priced['labor_rate_cents'])->not->toBe(21000);
    }

    $newRo = repairOrderForFinancialAuthority();
    $newConcern = concernForFinancialAuthority($newRo);
    $newPriced = app(App\Ark\Operations\RepairOrders\RepairOrderLinePricing::class)->attributesFor([
        'type' => App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $newConcern->id,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => App\Ark\Operations\Labor\LaborAdjustment::Normal->value,
    ], $newRo->fresh());

    expect($newPriced['labor_rate_cents'])->toBe(21000);
});

test('inline billing minimum autosave persists without a Rules popup', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.minimum'), [
            'billing_class' => ConcernBillingPosture::Fleet->value,
            'mode' => LaborBillingMinimumMode::PerLine->value,
            'hours' => '0.50',
            'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            'expected_version' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'saved');

    $settings = ShopSettings::current();

    expect($settings->labor_billing_minimums['fleet']['mode'])->toBe('per_line')
        ->and($settings->labor_billing_minimums['fleet']['hours'])->toBe('0.50')
        ->and(collect($settings->laborCategories())->firstWhere('key', 'mechanical')['rounding_rule'])->toBe('quarter');
});

test('inline billing minimum accepts per-concern mode', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.minimum'), [
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => LaborBillingMinimumMode::PerConcern->value,
            'hours' => '1.00',
            'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            'expected_version' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'saved');

    expect(ShopSettings::current()->labor_billing_minimums['customer_pay']['mode'])->toBe('per_concern')
        ->and(ShopSettings::current()->labor_billing_minimums['customer_pay']['hours'])->toBe('1.00');
});

test('cell rounding autosave is independent per billing class', function () {
    ShopSettings::current()->update([
        'labor_rounding' => [
            'mechanical' => [
                'customer_pay' => 'quarter',
                'repairpal' => 'quarter',
            ],
        ],
    ]);

    $beforeRate = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'general_repair',
    )->hourlyRateCents;

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rounding'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::RepairPal->value,
            'rounding_rule' => 'exact',
            'expected_version' => (int) ShopSettings::current()->labor_pricing_profile_version,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'saved')
        ->assertJsonPath('scope', 'work_type_billing_class')
        ->assertJsonPath('matrix.cells.mechanical.repairpal.rounding_rule', 'exact')
        ->assertJsonPath('matrix.cells.mechanical.customer_pay.rounding_rule', 'quarter');

    $stored = ShopSettings::current()->labor_rounding;

    expect($stored['mechanical']['repairpal'])->toBe('exact')
        ->and($stored['mechanical']['customer_pay'])->toBe('quarter')
        ->and(app(LaborPolicyResolver::class)->resolve(
            ConcernBillingPosture::CustomerPay,
            'general_repair',
        )->hourlyRateCents)->toBe($beforeRate);
});

test('matrix JSON projection exposes hybrid fields without adjust-labor controls', function () {
    $this->actingAs($this->admin)
        ->getJson(route('operations.settings.shop.labor-matrix.show'))
        ->assertOk()
        ->assertJsonPath('cells.mechanical.customer_pay.rounding_rule', 'quarter')
        ->assertJsonStructure([
            'work_types',
            'columns',
            'cells',
            'work_type_settings',
            'rounding_options',
            'billing_minimums',
            'routes' => ['rate', 'minimum', 'rounding'],
        ]);
});
