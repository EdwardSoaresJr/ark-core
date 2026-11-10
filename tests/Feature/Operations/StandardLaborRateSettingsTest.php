<?php

use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\LaborRateType;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\EstimatePricing\SyncStandardCustomerPayLaborRatesAction;
use App\Ark\Operations\EstimatePricing\UpsertLaborPolicyAction;
use App\Ark\Operations\Labor\LaborAdjustment;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'labor_pricing_profile_version' => 0,
    ]);
});

test('labor settings show vertical matrix with Mechanical Retail as the visible default', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor',
        ]))
        ->assertOk()
        ->assertSee('Labor rate matrix')
        ->assertSee('Shop default hourly rate')
        ->assertSee('+ Add Work Type')
        ->assertSee('data-labor-matrix', false)
        ->assertDontSee('Save Labor Settings')
        ->assertDontSee('Labor categories')
        ->assertDontSee('Advanced billing rates')
        ->assertDontSee('Standard hourly rate');
});

test('saving Mechanical Retail rate syncs matching Customer Pay policies atomically', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => 'custom',
            'rate' => '185.00',
            'expected_version' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'saved');

    $settings = ShopSettings::current();
    expect($settings->default_labor_rate_cents)->toBe(18500)
        ->and(collect($settings->laborCategories())->firstWhere('key', 'mechanical')['rate_cents'])->toBe(18500);

    foreach (OperationClass::query()->pluck('key') as $classKey) {
        $resolved = app(LaborPolicyResolver::class)->resolve(
            ConcernBillingPosture::CustomerPay,
            $classKey,
        );
        expect($resolved->hourlyRateCents)->toBe(18500);
    }

    expect(app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::Internal,
        'general_repair',
    )->hourlyRateCents)->toBe(0)
        ->and(app(LaborPolicyResolver::class)->resolve(
            ConcernBillingPosture::Comeback,
            'general_repair',
        )->hourlyRateCents)->toBe(0);
});

test('standard rate does not overwrite specialty Customer Pay operation-class rates', function () {
    $diagnostics = OperationClass::query()->where('key', 'diagnostics')->firstOrFail();
    app(UpsertLaborPolicyAction::class)->execute(
        ConcernBillingPosture::CustomerPay,
        $diagnostics,
        210.00,
        Carbon::parse('2026-01-01'),
        'Specialty diagnostics rate',
    );

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => 'custom',
            'rate' => '190.00',
            'expected_version' => (int) ShopSettings::current()->labor_pricing_profile_version,
        ])
        ->assertOk();

    expect(app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'general_repair',
    )->hourlyRateCents)->toBe(19000)
        ->and(app(LaborPolicyResolver::class)->resolve(
            ConcernBillingPosture::CustomerPay,
            'maintenance',
        )->hourlyRateCents)->toBe(19000)
        ->and(app(LaborPolicyResolver::class)->resolve(
            ConcernBillingPosture::CustomerPay,
            'diagnostics',
        )->hourlyRateCents)->toBe(21000);

    expect(ShopSettings::current()->default_labor_rate_cents)->toBe(19000);
});

test('existing labor line snapshot is unchanged after Mechanical Retail rate save', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $created = app(RepairOrderLinePricing::class)->attributesFor([
        'type' => RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], $repairOrder);

    expect($created['labor_rate_cents'])->toBe(16500);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake pads',
        'quantity' => $created['quantity'],
        'unit_price_cents' => $created['unit_price_cents'],
        'labor_category_key' => $created['labor_category_key'],
        'labor_category_name' => $created['labor_category_name'],
        'labor_entered_hours' => $created['labor_entered_hours'],
        'labor_adjustment' => $created['labor_adjustment'],
        'labor_adjustment_factor' => $created['labor_adjustment_factor'],
        'labor_billed_hours' => $created['labor_billed_hours'],
        'labor_rate_cents' => $created['labor_rate_cents'],
        'policy_resolved_labor_rate_cents' => $created['policy_resolved_labor_rate_cents'],
        'resolved_from_posture' => $created['resolved_from_posture'],
        'resolved_from_operation_class' => $created['resolved_from_operation_class'],
        'labor_policy_id' => $created['labor_policy_id'],
        'labor_policy_version' => $created['labor_policy_version'],
        'subtotal_cents' => $created['unit_price_cents'],
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => $created['unit_price_cents'],
    ]);

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => 'custom',
            'rate' => '200.00',
            'expected_version' => (int) ShopSettings::current()->labor_pricing_profile_version,
        ])
        ->assertOk();

    $updated = app(RepairOrderLinePricing::class)->attributesFor([
        'type' => RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], $repairOrder, $line->fresh());

    expect($updated['labor_rate_cents'])->toBe(16500)
        ->and(app(LaborPolicyResolver::class)->resolve(
            ConcernBillingPosture::CustomerPay,
            'general_repair',
        )->hourlyRateCents)->toBe(20000);
});

test('standard rate inheritance is explicit via LaborRateType::Standard in the matrix', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => 'custom',
            'rate' => '175.00',
            'expected_version' => 0,
        ])
        ->assertOk();

    $version = (int) ShopSettings::current()->labor_pricing_profile_version;

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::Fleet->value,
            'mode' => 'custom',
            'rate' => '140.00',
            'expected_version' => $version,
        ])
        ->assertOk();

    $operationClass = OperationClass::query()->where('key', 'general_repair')->firstOrFail();
    app(UpsertLaborPolicyAction::class)->execute(
        ConcernBillingPosture::Internal,
        $operationClass,
        0.0,
        Carbon::now()->startOfDay(),
        'Test intentional zero',
        LaborRateType::Zero,
    );

    $resolver = app(LaborPolicyResolver::class);

    $customerPay = $resolver->resolve(ConcernBillingPosture::CustomerPay, 'general_repair');
    expect($customerPay->rateType->value)->toBe('standard')
        ->and($customerPay->hourlyRateCents)->toBe(17500);

    $fleet = $resolver->resolve(ConcernBillingPosture::Fleet, 'general_repair');
    expect($fleet->rateType->value)->toBe('hourly')
        ->and($fleet->hourlyRateCents)->toBe(14000);

    $internal = $resolver->resolve(ConcernBillingPosture::Internal, 'general_repair');
    expect($internal->rateType->value)->toBe('zero')
        ->and($internal->hourlyRateCents)->toBe(0);

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => 'custom',
            'rate' => '188.00',
            'expected_version' => (int) ShopSettings::current()->labor_pricing_profile_version,
        ])
        ->assertOk();

    expect($resolver->resolve(ConcernBillingPosture::CustomerPay, 'general_repair')->hourlyRateCents)->toBe(18800)
        ->and($resolver->resolve(ConcernBillingPosture::Fleet, 'general_repair')->hourlyRateCents)->toBe(14000)
        ->and($resolver->resolve(ConcernBillingPosture::Internal, 'general_repair')->hourlyRateCents)->toBe(0);
});

test('station bind prompt is skipped for a single-station shop', function () {
    Workstation::query()->delete();
    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Main Shop',
        'is_active' => true,
    ]);

    expect(WorkstationPresence::stationBindingPromptEnabled())->toBeFalse();

    $this->actingAs($this->admin)
        ->get('/app')
        ->assertOk()
        ->assertDontSee('Choose the station for this browser.', false)
        ->assertDontSee('data-ws-bind-panel', false);
});

test('station bind prompt stays off for multi-station and telephony-only shops until explicit enablement exists', function () {
    Workstation::query()->delete();
    $shopId = ShopSettings::current()->id;
    Workstation::query()->create([
        'shop_settings_id' => $shopId,
        'name' => 'Bay 1',
        'is_active' => true,
    ]);
    Workstation::query()->create([
        'shop_settings_id' => $shopId,
        'name' => 'Bay 2',
        'is_active' => true,
    ]);

    expect(WorkstationPresence::stationBindingPromptEnabled())->toBeFalse();

    $this->actingAs($this->admin)
        ->get('/app')
        ->assertOk()
        ->assertDontSee('data-needs-binding="1"', false)
        ->assertDontSee('data-ws-bind-panel', false)
        ->assertDontSee('Choose the station for this browser.', false);
});

test('failed Customer Pay verification rolls back labor category and policy writes', function () {
    $beforeCents = (int) ShopSettings::current()->default_labor_rate_cents;
    $beforePolicyCents = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'general_repair',
    )->hourlyRateCents;

    $this->mock(SyncStandardCustomerPayLaborRatesAction::class, function ($mock): void {
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(\Illuminate\Validation\ValidationException::withMessages([
                'rate' => 'Forced sync failure for rollback proof.',
            ]));
    });

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.labor-matrix.rate'), [
            'work_type' => 'mechanical',
            'billing_class' => ConcernBillingPosture::CustomerPay->value,
            'mode' => 'custom',
            'rate' => '199.00',
            'expected_version' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('rate');

    ShopSettings::forgetCurrent();

    expect((int) ShopSettings::current()->default_labor_rate_cents)->toBe($beforeCents)
        ->and(collect(ShopSettings::current()->laborCategories())->firstWhere('key', 'mechanical')['rate_cents'])
        ->toBe($beforeCents)
        ->and(app(LaborPolicyResolver::class)->resolve(
            ConcernBillingPosture::CustomerPay,
            'general_repair',
        )->hourlyRateCents)->toBe($beforePolicyCents);
});
