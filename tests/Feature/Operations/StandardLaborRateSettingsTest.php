<?php

use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
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

function standardLaborSettingsPayload(array $overrides = []): array
{
    $settings = ShopSettings::current();

    $categories = collect($settings->laborCategories())
        ->values()
        ->map(fn (array $category): array => [
            'key' => $category['key'],
            'name' => $category['name'],
            'rate' => number_format($category['rate_cents'] / 100, 2, '.', ''),
            'minimum_hours' => $category['minimum_hours'],
            'rounding_rule' => $category['rounding_rule'],
            'allows_modifiers' => $category['allows_modifiers'] ? '1' : '0',
        ])
        ->all();

    $defaultKey = $overrides['default_labor_category_key'] ?? ShopSettings::DEFAULT_LABOR_CATEGORY_KEY;
    $defaultCategory = collect($categories)->firstWhere('key', $defaultKey) ?? ($categories[0] ?? null);
    $standardHourlyRate = $overrides['standard_hourly_rate']
        ?? ($defaultCategory['rate'] ?? number_format($settings->default_labor_rate_cents / 100, 2, '.', ''));

    return array_merge([
        'standard_hourly_rate' => $standardHourlyRate,
        'default_labor_category_key' => ShopSettings::DEFAULT_LABOR_CATEGORY_KEY,
        'labor_categories' => $categories,
    ], $overrides);
}

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);
});

test('labor settings show standard hourly rate and effective estimate rate', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor',
        ]))
        ->assertOk()
        ->assertSee('Standard hourly rate')
        ->assertSee('What a new Customer Pay estimate uses now')
        ->assertSee('Labor categories')
        ->assertSee('Advanced billing rates')
        ->assertSee('Use Standard')
        ->assertSee('Intentional Zero')
        ->assertSee('Core category')
        ->assertSee('Archive')
        ->assertDontSee('>Labor Policies</button>', false)
        ->assertSee('data-labor-effective-rate', false)
        ->assertSee('Basic')
        ->assertSee('Optional')
        ->assertSee('Advanced');
});

test('saving standard hourly rate syncs matching Customer Pay policies atomically', function () {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.labor.update'), standardLaborSettingsPayload([
            'standard_hourly_rate' => '185.00',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHas('status');

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
        ->patch(route('operations.settings.shop.labor.update'), standardLaborSettingsPayload([
            'standard_hourly_rate' => '190.00',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHas('status');

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

test('existing labor line snapshot is unchanged after standard rate save', function () {
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
        ->patch(route('operations.settings.shop.labor.update'), standardLaborSettingsPayload([
            'standard_hourly_rate' => '200.00',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit'));

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

test('labor policies matrix cells expose an Edit affordance under Labor Settings', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor',
        ]))
        ->assertOk()
        ->assertSee('>Edit</span>', false)
        ->assertSee('Operation-class specialty rates')
        ->assertSee('Core-defined');
});

test('standard rate inheritance is explicit via LaborRateType::Standard', function () {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.labor.update'), standardLaborSettingsPayload([
            'standard_hourly_rate' => '175.00',
            'advanced_rates' => [
                ConcernBillingPosture::CustomerPay->value => [
                    'mode' => 'standard',
                    'rate' => '175.00',
                ],
                ConcernBillingPosture::Fleet->value => [
                    'mode' => 'custom',
                    'rate' => '140.00',
                ],
                ConcernBillingPosture::Internal->value => [
                    'mode' => 'zero',
                    'rate' => '0.00',
                ],
            ],
        ]))
        ->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHas('status');

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

    // Changing standard updates Standard policies without touching custom/zero.
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.labor.update'), standardLaborSettingsPayload([
            'standard_hourly_rate' => '188.00',
            'advanced_rates' => [
                ConcernBillingPosture::CustomerPay->value => [
                    'mode' => 'standard',
                    'rate' => '188.00',
                ],
                ConcernBillingPosture::Fleet->value => [
                    'mode' => 'custom',
                    'rate' => '140.00',
                ],
                ConcernBillingPosture::Internal->value => [
                    'mode' => 'zero',
                    'rate' => '0.00',
                ],
            ],
        ]))
        ->assertRedirect(route('operations.settings.shop.edit'));

    expect($resolver->resolve(ConcernBillingPosture::CustomerPay, 'general_repair')->hourlyRateCents)->toBe(18800)
        ->and($resolver->resolve(ConcernBillingPosture::Fleet, 'general_repair')->hourlyRateCents)->toBe(14000)
        ->and($resolver->resolve(ConcernBillingPosture::Internal, 'general_repair')->hourlyRateCents)->toBe(0);
});

test('labor category can be archived and restored without hard delete', function () {
    $payload = standardLaborSettingsPayload();
    $payload['labor_categories'] = collect($payload['labor_categories'])
        ->map(function (array $category): array {
            $category['is_active'] = $category['key'] === 'fabrication' ? '0' : '1';

            return $category;
        })
        ->all();

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.labor.update'), $payload)
        ->assertRedirect(route('operations.settings.shop.edit'));

    $fabrication = collect(ShopSettings::current()->laborCategories())->firstWhere('key', 'fabrication');
    expect($fabrication['is_active'])->toBeFalse()
        ->and(collect(ShopSettings::current()->activeLaborCategories())->pluck('key')->all())
        ->not->toContain('fabrication');

    $payload['labor_categories'] = collect($payload['labor_categories'])
        ->map(function (array $category): array {
            $category['is_active'] = '1';

            return $category;
        })
        ->all();

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.labor.update'), $payload)
        ->assertRedirect(route('operations.settings.shop.edit'));

    expect(collect(ShopSettings::current()->laborCategories())->firstWhere('key', 'fabrication')['is_active'])->toBeTrue();
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

    // No ShopSettings "stations enabled" flag exists yet (only workstation_idle_lock_minutes).
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

    // Sync throws after ShopSettings is written inside the transaction so both roll back.
    $this->mock(SyncStandardCustomerPayLaborRatesAction::class, function ($mock): void {
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(\Illuminate\Validation\ValidationException::withMessages([
                'standard_hourly_rate' => 'Forced sync failure for rollback proof.',
            ]));
    });

    $this->actingAs($this->admin)
        ->from(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor',
        ]))
        ->patch(route('operations.settings.shop.labor.update'), standardLaborSettingsPayload([
            'standard_hourly_rate' => '199.00',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor',
        ]))
        ->assertSessionHasErrors('standard_hourly_rate');

    ShopSettings::forgetCurrent();

    expect((int) ShopSettings::current()->default_labor_rate_cents)->toBe($beforeCents)
        ->and(collect(ShopSettings::current()->laborCategories())->firstWhere('key', 'mechanical')['rate_cents'])
        ->toBe($beforeCents)
        ->and(app(LaborPolicyResolver::class)->resolve(
            ConcernBillingPosture::CustomerPay,
            'general_repair',
        )->hourlyRateCents)->toBe($beforePolicyCents);
});
