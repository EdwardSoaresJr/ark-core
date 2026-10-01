<?php

use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('shop excellence targets persist through settings form', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.excellence.update'), [
            'effective_labor_rate_floor' => '155.00',
            'aro_target' => '800.00',
            'parts_margin_target_percent' => 56,
            'labor_sales_target_percent' => 55,
            'parts_sales_target_percent' => 45,
            'monthly_fixed_costs' => '22000.00',
            'net_profit_target_percent' => 22,
            'income_tax_reserve_percent' => 28,
            'payroll_tax_reserve_percent' => 12,
            'monthly_payroll_tax' => '1800.00',
            'owner_digest_enabled' => '1',
            'owner_digest_time' => '17:30',
            'mark_target_reviewed' => '1',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'excellence']))
        ->assertSessionHas('status');

    $targets = ShopExcellenceTargets::current();

    expect($targets['effective_labor_rate_floor_cents'])->toBe(15500)
        ->and($targets['aro_target_cents'])->toBe(80000)
        ->and($targets['parts_margin_target_percent'])->toBe(56)
        ->and($targets['monthly_fixed_costs_cents'])->toBe(2200000)
        ->and($targets['net_profit_target_percent'])->toBe(22)
        ->and($targets['income_tax_reserve_percent'])->toBe(28)
        ->and($targets['payroll_tax_reserve_percent'])->toBe(12)
        ->and($targets['monthly_payroll_tax_cents'])->toBe(180000)
        ->and($targets['owner_digest_time'])->toBe('17:30')
        ->and(ShopExcellenceTargets::lastTargetReview())->not->toBeNull();

    expect(ShopSettings::current()->fresh()->shop_excellence_targets['aro_target_cents'])->toBe(80000);
});

test('shop excellence targets persist in shop settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    ShopExcellenceTargets::persist([
        'aro_target_cents' => 75000,
        'parts_margin_target_percent' => 55,
        'labor_sales_target_percent' => 55,
        'parts_sales_target_percent' => 45,
        'owner_digest_enabled' => true,
        'owner_digest_time' => '18:00',
    ]);

    expect(ShopExcellenceTargets::raw()['aro_target_cents'])->toBe(75000)
        ->and(ShopExcellenceTargets::raw()['owner_digest_enabled'])->toBeTrue()
        ->and(ShopSettings::current()->fresh()->shop_excellence_targets['aro_target_cents'])->toBe(75000);
});

test('admin can open owner targets settings section', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.settings.shop.edit', ['section' => 'excellence']))
        ->assertOk()
        ->assertSee('Owner Targets & Reporting')
        ->assertSee('Monthly fixed costs');
});
