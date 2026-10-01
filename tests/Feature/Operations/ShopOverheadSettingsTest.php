<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('admin can save shop overhead to server', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $payload = [
        'fixed_cost_lines' => [
            ['label' => 'Rent', 'amount' => '4500', 'period' => 'monthly'],
            ['label' => 'Payroll', 'amount' => '1400', 'period' => 'weekly'],
        ],
        'monthly_card_volume' => '75000',
        'card_processing_percent' => '2.9',
        'merchant_financing_holdback_percent' => '',
        'fixed_monthly_financing_payment' => '',
        'technician_count' => '3',
        'workdays_per_month' => '22',
        'workday_hours' => '8',
        'billable_utilization' => '85',
        'overhead_tab' => 'capacity',
    ];

    $this->patchJson(route('operations.settings.shop.overhead.update'), $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Shop overhead saved.')
        ->assertJsonPath('monthly_fixed_costs', 10566.67)
        ->assertJsonPath('state.fixed_cost_lines.1.period', 'weekly');

    $settings = ShopSettings::current()->fresh();

    expect($settings->shopOverheadStateArray()['fixed_cost_lines'][0]['amount'])->toBe('4500')
        ->and($settings->shopOverheadStateArray()['fixed_cost_lines'][1]['period'])->toBe('weekly')
        ->and($settings->shop_overhead_per_hour_cents)->toBeGreaterThan(0)
        ->and(ShopExcellenceTargets::current()['monthly_fixed_costs_cents'])->toBe(1056667);
});

test('saved shop overhead reloads on settings page', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'shop_overhead_state' => [
            'fixed_cost_lines' => [
                ['label' => 'Rent / mortgage', 'amount' => '3200', 'period' => 'monthly'],
            ],
            'technician_count' => '2',
            'workdays_per_month' => '22',
            'workday_hours' => '8',
            'billable_utilization' => '85',
            'overhead_tab' => 'fixed-costs',
        ],
        'shop_overhead_per_hour_cents' => 1250,
    ]);

    $this->get(route('operations.settings.shop.edit', ['section' => 'overhead']))
        ->assertOk()
        ->assertSee('Save shop overhead')
        ->assertSee('Setup walkthrough')
        ->assertSee('Add cost')
        ->assertSee('3200');
});

test('advisor cannot save shop overhead', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->patchJson(route('operations.settings.shop.overhead.update'), [
        'costs' => ['rent' => '9999'],
        'technician_count' => '1',
    ])->assertForbidden();
});
