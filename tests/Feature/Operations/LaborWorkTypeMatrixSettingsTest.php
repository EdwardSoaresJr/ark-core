<?php

use App\Ark\Operations\EstimatePricing\LaborBillingMinimumMode;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimums;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
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
    ]);
});

test('labor settings show work-type matrix and billing-class minimums', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor',
        ]))
        ->assertOk()
        ->assertSee('Labor rate matrix')
        ->assertSee('Billing-class minimums')
        ->assertSee('Mechanical')
        ->assertSee('Advanced Mechanical')
        ->assertSee('Troubleshooting')
        ->assertSee('Programming')
        ->assertSee('Retail')
        ->assertSee('Warranty')
        ->assertSee('Fleet')
        ->assertSee('RepairPal')
        ->assertSee('data-labor-rate-matrix', false)
        ->assertSee('Minimum per repair order')
        ->assertSee('Retail Mechanical (default)')
        ->assertDontSee('Labor categories')
        ->assertDontSee('Advanced billing rates');
});

test('saving matrix and retail RO minimum activates forward-only profile version', function () {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.labor.update'), [
            'standard_hourly_rate' => '165.00',
            'matrix' => [
                'mechanical' => [
                    'customer_pay' => ['mode' => 'standard'],
                    'warranty_other' => ['mode' => 'custom', 'rate' => '150.00'],
                    'fleet' => ['mode' => 'custom', 'rate' => '155.00'],
                    'repairpal' => ['mode' => 'custom', 'rate' => '150.00'],
                ],
                'advanced_mechanical' => [
                    'customer_pay' => ['mode' => 'custom', 'rate' => '185.00'],
                ],
            ],
            'billing_minimums' => [
                'customer_pay' => [
                    'mode' => LaborBillingMinimumMode::PerRepairOrder->value,
                    'hours' => '1.00',
                    'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
                ],
                'warranty_other' => [
                    'mode' => LaborBillingMinimumMode::None->value,
                    'hours' => '0.00',
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHas('status');

    $settings = ShopSettings::current();

    expect((int) $settings->labor_pricing_profile_version)->toBe(1)
        ->and($settings->labor_billing_minimums['customer_pay']['mode'])->toBe('per_ro')
        ->and($settings->labor_billing_minimums['customer_pay']['hours'])->toBe('1.00')
        ->and($settings->labor_billing_minimums['customer_pay']['shortfall_rate_source'])->toBe('standard_mechanical');

    $advanced = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'advanced_mechanical',
    );

    expect($advanced->hourlyRateCents)->toBe(18500);

    $warranty = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::WarrantyOther,
        'general_repair',
    );

    expect($warranty->hourlyRateCents)->toBe(15000);
});
