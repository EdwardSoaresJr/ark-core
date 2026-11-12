<?php

use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\OperationalProfile;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function laborSettingsPayload(array $overrides = []): array
{
    $settings = ShopSettings::current();

    $categories = collect($settings->laborCategories())
        ->values()
        ->map(fn (array $category, int $index): array => [
            'key' => $category['key'],
            'name' => $category['name'],
            'rate' => number_format($category['rate_cents'] / 100, 2, '.', ''),
            'minimum_hours' => $category['minimum_hours'],
            'rounding_rule' => $category['rounding_rule'],
            'allows_modifiers' => $category['allows_modifiers'] ? '1' : '0',
        ])
        ->all();

    $defaultKey = $overrides['default_labor_category_key'] ?? ShopSettings::DEFAULT_LABOR_CATEGORY_KEY;
    $defaultCategory = collect($categories)->firstWhere('key', $defaultKey) ?? $categories[0] ?? null;
    $standardHourlyRate = $overrides['standard_hourly_rate']
        ?? ($defaultCategory['rate'] ?? number_format($settings->default_labor_rate_cents / 100, 2, '.', ''));

    return array_merge([
        'standard_hourly_rate' => $standardHourlyRate,
        'default_labor_category_key' => ShopSettings::DEFAULT_LABOR_CATEGORY_KEY,
        'labor_categories' => $categories,
    ], $overrides);
}

test('admin can view operational shop settings defaults', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $response = $this->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertSee('How this shop runs: shop details, pricing, documents, workflow, staff, and connected services.')
        ->assertDontSee('Operational Settings')
        ->assertDontSee('Operational Domains')
        ->assertDontSee('Current outcomes')
        ->assertDontSee('Financial rules, estimate defaults, and document language')
        ->assertSeeInOrder(['Basic', 'Shop Identity', 'Appointments &amp; Setup', 'Financial Rules', 'Documents / Disclaimers', 'Optional', 'Online Requests', 'Square Payments', 'Parts catalogs', 'Advanced', 'Shop Overhead', 'Owner Targets &amp; Reporting', 'Workflow Defaults', 'Label Printing'], false)
        ->assertSee('fw-semibold', false)
        ->assertSee('Communications')
        ->assertSee('Stations & Phones')
        ->assertSee("@click=\"setActive('operations')\"", false)
        ->assertSee('<div class="subheader">Appointments &amp; Setup</div>', false)
        ->assertSee('Shop-wide options: appointments and bays, station presence, shop profile presets, and importing customers from a spreadsheet.')
        ->assertSee('Shop name, logo, and contact details')
        ->assertSee('Shown on estimates, invoices, and other customer documents.')
        ->assertSee('Save Shop Identity')
        ->assertDontSee('Estimate-facing shop identity')
        ->assertDontSee('Save General')
        ->assertDontSee('Advisor work surface')
        ->assertSee('Shop Identity')
        ->assertSee('Financial Rules')
        ->assertSee('Documents / Disclaimers')
        ->assertSee('Workflow Defaults')
        ->assertSee('Default visit type')
        ->assertDontSee('Default visit posture')
        ->assertSee('Check In visit')
        ->assertSee('Worksheet notes')
        ->assertSee('Estimate logo')
        ->assertDontSee('name="default_labor_rate"', false)
        ->assertSee('Default Labor Rate')
        ->assertSee('Labor rate matrix')
        ->assertDontSee('name="standard_hourly_rate"', false)
        ->assertSee('Sales tax profiles')
        ->assertSee('Customer document presentation')
        ->assertSee('Tax labor')
        ->assertSee('Tax parts')
        ->assertSee('does not reprice existing open or closed ROs')
        ->assertSee('Saved projection for new ROs:')
        ->assertDontSee('Effective posture')
        ->assertSee('shop fees not taxable')
        ->assertSee('Tax shop fees')
        ->assertSee('+ Add tax profile')
        ->assertDontSee('Default tax rate')
        ->assertDontSee('Taxable labor')
        ->assertDontSee('Taxable parts')
        ->assertSee('Parts Matrix')
        ->assertSee('AFT Parts')
        ->assertSee('OEM Parts')
        ->assertSee('Warranty (No Markup)')
        ->assertSee('Manage Billing Classes')
        ->assertSee('Warranty')
        ->assertSee('Military')
        ->assertSee('Markup is the editable pricing policy')
        ->assertSee('How estimates are priced')
        ->assertSee('Key tags and oil change stickers')
        ->assertSee('Parts gross profit from your markup')
        ->assertDontSee('Estimate pricing authority')
        ->assertDontSee('Label printing authority')
        ->assertDontSee('Editable authority')
        ->assertDontSee('Read-only truth')
        ->assertDontSee('Target posture')
        ->assertSee('line overrides remain operational exceptions')
        ->assertSee('Save for margin')
        ->assertDontSee('marginFromMarkup(row.markup_percentage)', false)
        ->assertSee('Add Row')
        ->assertSee('Remove')
        ->assertSee('Fleet shop fees')
        ->assertSee('Warranty parts matrix')
        ->assertSee('Pill color')
        ->assertSee('data-billing-classes-shared', false)
        ->assertDontSee('id="financial-tab-billing-classes"', false)
        ->assertDontSee('id="financial-tab-labor-policies"', false)
        ->assertDontSee('Default estimate state')
        ->assertDontSee('name="parts_matrices[1][rows][0][margin_percentage]"', false)
        ->assertSee(ShopSettings::DEFAULT_ESTIMATE_DISCLAIMER)
        ->assertSee(ShopSettings::DEFAULT_RECOMMENDATION_DISCLAIMER)
        ->assertDontSee('name="default_estimate_state"', false)
        ->assertSee('150.00')
        ->assertSee('180.00');

    $financialRules = str($response->getContent())->between("x-show=\"active === 'financial'\"", "x-show=\"active === 'payments'\"")->toString();

    expect($financialRules)->not->toBe('')
        ->and($financialRules)->not->toContain('>Order<');
});

test('settings pages use plain labels instead of posture, authority, truth, surface, and band wording', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $response = $this->get(route('operations.settings.shop.edit'))->assertOk();

    foreach ([
        'Default visit posture',
        'Account posture at Check In',
        'Category defaults drive labor authority',
        'billing posture',
        'Billing posture',
        'Billing Posture',
        'billing postures',
        'shop fee posture',
        'check-in posture',
        'visit posture',
        'InspectionItem authority',
        'Customer and Vehicle authority',
        'Fact authority',
        'Capture surfaces',
        'Save payment surfaces',
        'Shop excellence bands',
        'posted sales truth',
        'planning bands',
        'Owner rhythm',
        'Resolver Preview',
        'financial rail',
    ] as $phrase) {
        $response->assertDontSee($phrase);
    }

    $response
        ->assertSee('Default visit type')
        ->assertSee('Billing at Check In')
        ->assertSee('Labor rate matrix')
        ->assertSee('Select a work type, edit hourly rates inline, and open Rules for billing-policy minimums.')
        ->assertSee('Manage Billing Classes')
        ->assertSee('Time zone sources')
        ->assertSee('Where card payments are offered')
        ->assertSee('Save card payment options')
        ->assertSee('Shop performance targets')
        ->assertSee('Creates customer and vehicle records only. Repair history is not imported.')
        ->assertSee('Technicians record findings on each checklist item.')
        ->assertSee('name="default_visit_mode"', false)
        ->assertDontSee('name="lp_posture"', false)
        ->assertSee(route('operations.settings.shop.payments.update'), false);
});

test('every financial rules tab opens from the financial-tab link that save redirects use', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $html = $this->get(route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'labor']))
        ->assertOk()
        ->getContent();

    expect(preg_match("/\[([^\]]*)\]\.includes\(fromUrl\)/", $html, $honored))->toBe(1);
    preg_match_all("/financialTab === '([a-z-]+)'/", $html, $tabs);

    $honoredTabs = collect(explode(',', $honored[1]))->map(fn (string $tab): string => trim($tab, " '"))->all();
    $renderedTabs = collect($tabs[1])->unique()->reject(fn (string $tab): bool => in_array($tab, ['billing-classes', 'customer-types', 'customer-tags'], true))->values()->all();

    expect($renderedTabs)->toContain('labor', 'deposits')
        ->and($renderedTabs)->not->toContain('labor-policies');

    foreach ($renderedTabs as $tab) {
        expect($honoredTabs)->toContain($tab);
    }
});

test('admin can upload and remove shop estimate logo', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('public');
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->patch(route('operations.settings.shop.general.update'), [
        'shop_name' => 'Operational Drivability',
        'shop_timezone' => 'America/Denver',
        'phone' => '555-0110',
        'email' => 'service@example.com',
        'website' => 'https://example.com',
        'logo' => UploadedFile::fake()->image('shop-logo.png', 320, 140),
        'address_line_1' => '123 Shop Road',
        'address_line_2' => 'Suite B',
        'city' => 'Demo City',
        'state' => 'CO',
        'postal_code' => '80903',
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHas('status', 'Shop identity saved.');

    $settings = ShopSettings::current();

    expect($settings->logo_path)->toStartWith('shop-logos/');
    Storage::disk('public')->assertExists($settings->logo_path);

    $logoPath = $settings->logo_path;

    $this->patch(route('operations.settings.shop.general.update'), [
        'shop_name' => 'Operational Drivability',
        'shop_timezone' => 'America/Denver',
        'phone' => '555-0110',
        'email' => 'service@example.com',
        'website' => 'https://example.com',
        'remove_logo' => '1',
        'address_line_1' => '123 Shop Road',
        'address_line_2' => 'Suite B',
        'city' => 'Demo City',
        'state' => 'CO',
        'postal_code' => '80903',
    ])->assertRedirect(route('operations.settings.shop.edit'));

    expect(ShopSettings::current()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($logoPath);
});

test('shop identity save accepts a host-only website and browser time values with seconds', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $weekly = ShopSettings::defaultTelephonyCallFlow()['weekly_hours'];
    foreach ($weekly as $day => $hours) {
        $weekly[$day]['open'] = '09:00:00';
        $weekly[$day]['close'] = '18:00:00';
    }

    $this->patch(route('operations.settings.shop.general.update'), [
        'shop_name' => 'Demo Auto Repair',
        'shop_timezone' => 'America/Denver',
        'website' => 'demo.autorepairkeeper.com',
        'telephony_call_flow' => [
            'weekly_hours' => $weekly,
            'closed_dates' => '',
        ],
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasNoErrors();

    $settings = ShopSettings::current();

    expect($settings->website)->toBe('https://demo.autorepairkeeper.com')
        ->and($settings->telephony_call_flow['weekly_hours']['monday']['open'])->toBe('09:00')
        ->and($settings->telephony_call_flow['weekly_hours']['monday']['close'])->toBe('18:00');
});

test('admin can persist authoritative operational defaults', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->patch(route('operations.settings.shop.general.update'), [
        'shop_name' => 'Operational Drivability',
        'shop_timezone' => 'America/Denver',
        'phone' => '555-0110',
        'email' => 'service@example.com',
        'website' => 'https://example.com',
        'address_line_1' => '123 Shop Road',
        'address_line_2' => 'Suite B',
        'city' => 'Demo City',
        'state' => 'CO',
        'postal_code' => '80903',
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $this->patch(route('operations.settings.shop.labor.update'), laborSettingsPayload([
        'standard_hourly_rate' => '175.50',
        'labor_categories' => collect(ShopSettings::DEFAULT_LABOR_CATEGORIES)->values()->map(function (array $category): array {
            return [
                'key' => $category['key'],
                'name' => $category['name'],
                'rate' => $category['key'] === 'mechanical' ? '175.50' : number_format($category['rate_cents'] / 100, 2, '.', ''),
                'minimum_hours' => $category['minimum_hours'],
                'rounding_rule' => $category['rounding_rule'],
            ];
        })->all(),
    ]))->assertRedirect(route('operations.settings.shop.edit'));

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_label' => 'C/S Tax',
        'default_tax_rate' => '8.250',
        'taxable_labor' => '0',
        'taxable_parts' => '1',
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $this->patch(route('operations.settings.shop.parts-matrices.update', 'aft-parts'), [
        'parts_matrices' => [
            [
                'key' => 'aft-parts',
                'name' => 'AFT Parts',
                'rows' => [
                    ['min_cost' => '0', 'max_cost' => '5', 'markup_percentage' => '180', 'sort_order' => '1'],
                    ['min_cost' => '5.01', 'max_cost' => '10', 'markup_percentage' => '150', 'sort_order' => '2'],
                ],
            ],
            [
                'key' => 'warranty-no-markup',
                'name' => 'Warranty (No Markup)',
                'rows' => [
                    ['min_cost' => '0', 'max_cost' => null, 'markup_percentage' => '0', 'sort_order' => '1'],
                ],
            ],
        ],
        'default_parts_matrix_key' => 'aft-parts',
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $this->patch(route('operations.settings.shop.customer-types.update'), [
        'customer_types' => [
            ['name' => 'Retail', 'default_parts_matrix_key' => null],
            ['name' => 'Warranty', 'default_parts_matrix_key' => 'aft-parts'],
            ['name' => 'Military', 'default_parts_matrix_key' => 'warranty-no-markup'],
            ['name' => 'Fleet', 'default_parts_matrix_key' => null],
        ],
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $this->patch(route('operations.settings.shop.estimates.update'), [
        'estimate_disclaimer' => 'Estimates are based on visible conditions at time of inspection.',
        'recommendation_disclaimer' => 'Recommendations may change after disassembly or further testing.',
        'estimate_validity_days' => '14',
        'customer_part_description_mode' => 'cleaned',
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHas('status', 'Document settings saved.');

    $this->patch(route('operations.settings.shop.workflow.update'), [
        'default_visit_mode' => 'needs_shuttle',
        'default_recommendation_intent' => 'maintenance',
        'default_recommendation_posture' => 'must_do',
        'default_notes_private' => '1',
        'default_estimate_state' => RepairOrderStatus::Estimate->value,
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHas('status', 'Check In defaults saved.');

    $settings = ShopSettings::current();

    expect($settings->shop_name)->toBe('Operational Drivability')
        ->and($settings->default_labor_rate_cents)->toBe(17550)
        ->and($settings->tax_enabled)->toBeTrue()
        ->and($settings->tax_label)->toBe('C/S Tax')
        ->and((string) $settings->default_tax_rate)->toBe('8.250')
        ->and($settings->taxable_labor)->toBeFalse()
        ->and($settings->taxable_parts)->toBeTrue()
        ->and($settings->parts_matrices[0]['name'])->toBe('AFT Parts')
        ->and($settings->parts_matrices[0]['is_default'])->toBeTrue()
        ->and($settings->parts_matrices[0]['rows'][0]['markup_percentage'])->toBe('180.00')
        ->and($settings->parts_matrices[0]['rows'][0]['margin_percentage'])->toBeNull()
        ->and($settings->partsMatrices()[0]['rows'][0]['margin_percentage'])->toBe('64')
        ->and($settings->partsMatrices()[1]['rows'][0]['margin_percentage'])->toBe('0')
        ->and(collect($settings->partsMatrices())->pluck('name')->all())->toBe(['AFT Parts', 'Warranty (No Markup)'])
        ->and(collect($settings->customer_types)->firstWhere('name', 'Warranty')['name'])->toBe('Warranty')
        ->and(collect($settings->customer_types)->firstWhere('name', 'Warranty')['shop_fees_enabled'])->toBeFalse()
        ->and(collect($settings->customer_types)->firstWhere('name', 'Warranty')['pill_color'])->toBe('green')
        ->and(collect($settings->customer_types)->firstWhere('name', 'Warranty')['default_parts_matrix_key'])->toBe('aft-parts')
        ->and($settings->customer_types[2]['discount_amount'])->toBe('10.00')
        ->and($settings->customer_types[2]['default_parts_matrix_key'])->toBe('warranty-no-markup')
        ->and($settings->customer_types[3]['discount_type'])->toBe('none')
        ->and($settings->estimate_validity_days)->toBe(14)
        ->and($settings->default_visit_mode)->toBe('needs_shuttle')
        ->and($settings->default_recommendation_intent)->toBe('maintenance')
        ->and($settings->default_recommendation_priority)->toBe('must_do')
        ->and($settings->default_notes_private)->toBeTrue()
        ->and($settings->default_estimate_state)->toBe(RepairOrderStatus::Estimate->value);
});

test('shop settings rejects more than one default parts matrix', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->patch(route('operations.settings.shop.parts-matrices.update', 'aft-parts'), [
        'parts_matrices' => [
            [
                'key' => 'aft-parts',
                'name' => 'AFT Parts',
                'is_default' => '1',
                'rows' => [
                    ['min_cost' => '0', 'max_cost' => '5', 'markup_percentage' => '180', 'sort_order' => '1'],
                ],
            ],
            [
                'key' => 'oem-parts',
                'name' => 'OEM Parts',
                'is_default' => '1',
                'rows' => [
                    ['min_cost' => '0', 'max_cost' => '25', 'markup_percentage' => '90', 'sort_order' => '1'],
                ],
            ],
        ],
    ])->assertSessionHasErrors('parts_matrices');
});

test('saving tax settings does not recalculate living repair order line tax', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'tax_enabled' => true,
        'default_tax_rate' => '11.000',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => null,
    ]);

    $repairOrder = repairOrderForFinancialAuthority();
    expect($repairOrder->tax_profile['rate'])->toBe('11.000');

    $concern = concernForFinancialAuthority($repairOrder);

    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Sensor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'subtotal_cents' => 10000,
        'tax_cents' => 1100,
        'shop_fee_cents' => 1000,
        'total_cents' => 12100,
    ]);

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_label' => 'Tax',
        'default_tax_rate' => '10.000',
        'taxable_labor' => '0',
        'taxable_parts' => '1',
        'taxable_shop_fees' => '0',
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $part->refresh();

    // Forward-only: living line amounts stay put; RO keeps the pre-change tax stamp.
    expect($part->shop_fee_cents)->toBe(1000)
        ->and($part->tax_cents)->toBe(1100)
        ->and($part->total_cents)->toBe(12100)
        ->and($repairOrder->fresh()->tax_profile['rate'])->toBe('11.000')
        ->and((string) ShopSettings::current()->default_tax_rate)->toBe('10.000');
});

test('saving tax settings does not rewrite unrelated settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $settings = ShopSettings::current();
    $settings->update([
        'shop_name' => 'Stable Shop',
        'default_labor_rate_cents' => 18100,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '5.000',
        'shop_fee_cap_cents' => 4200,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
        'estimate_disclaimer' => 'Stable estimate disclaimer.',
        'recommendation_disclaimer' => 'Stable recommendation disclaimer.',
        'estimate_validity_days' => 21,
        'default_recommendation_intent' => 'immediate_attention',
        'default_estimate_state' => RepairOrderStatus::WaitingApproval->value,
    ]);

    $originalPartsMatrices = $settings->fresh()->parts_matrices;
    $originalCustomerTypes = $settings->fresh()->customer_types;

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_label' => 'C/S Tax',
        'default_tax_rate' => '8.250',
        'taxable_labor' => '0',
        'taxable_parts' => '1',
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $settings->refresh();

    expect($settings->tax_label)->toBe('C/S Tax')
        ->and($settings->shop_name)->toBe('Stable Shop')
        ->and($settings->default_labor_rate_cents)->toBe(18100)
        ->and($settings->shop_fee_cap_cents)->toBe(4200)
        ->and($settings->parts_matrices)->toBe($originalPartsMatrices)
        ->and($settings->customer_types)->toBe($originalCustomerTypes)
        ->and($settings->estimate_disclaimer)->toBe('Stable estimate disclaimer.')
        ->and($settings->default_estimate_state)->toBe(RepairOrderStatus::WaitingApproval->value);
});

test('tax and shop fee switches are checkboxes that post 0 when off and 1 when on', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update(['tax_enabled' => true, 'shop_fee_enabled' => false]);

    $response = $this->get(route('operations.settings.shop.edit', ['section' => 'financial']))->assertOk();

    foreach (['tax_enabled', 'shop_fee_enabled'] as $field) {
        $response
            ->assertSee('<input type="hidden" name="'.$field.'" value="0">', false)
            ->assertSee('type="checkbox" name="'.$field.'" value="1"', false)
            ->assertDontSee('<select name="'.$field.'"', false);
    }

    foreach (['taxable_labor', 'taxable_parts', 'taxable_shop_fees'] as $field) {
        $response
            ->assertSee('name="tax_profiles[0]['.$field.']"', false)
            ->assertSee('type="checkbox" name="tax_profiles[0]['.$field.']" value="1"', false)
            ->assertDontSee('<select name="tax_profiles[0]['.$field.']"', false);
    }

    $response
        ->assertSee('Charge sales tax')
        ->assertSee('Charge a shop fee')
        ->assertSee('taxEnabled: true', false)
        ->assertSee('shopFeeEnabled: false', false)
        ->assertSee('shopFeeMode:', false)
        ->assertSee('data-tax-add-profile', false)
        ->assertSee('+ Add component');
});

test('saving tax with the switch on or off persists exactly the posted values', function (string $posted, bool $expected) {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update(['tax_enabled' => ! $expected]);

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => $posted,
        'tax_label' => 'County Tax',
        'default_tax_rate' => '7.500',
        'taxable_labor' => '1',
        'taxable_parts' => '0',
        'taxable_shop_fees' => '1',
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasNoErrors();

    $settings = ShopSettings::current()->fresh();

    expect($settings->tax_enabled)->toBe($expected)
        ->and($settings->tax_label)->toBe('County Tax')
        ->and((string) $settings->default_tax_rate)->toBe('7.500')
        ->and($settings->taxable_labor)->toBeTrue()
        ->and($settings->taxable_parts)->toBeFalse()
        ->and($settings->taxable_shop_fees)->toBeTrue();
})->with([
    'box off' => ['0', false],
    'box on' => ['1', true],
]);

test('saving shop fees with the switch on or off persists exactly the posted values', function (string $posted, bool $expected) {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update(['shop_fee_enabled' => ! $expected]);

    $this->patch(route('operations.settings.shop.fees.update'), [
        'shop_fee_enabled' => $posted,
        'shop_fee_mode' => 'percent',
        'shop_fee_rate' => '6.000',
        'shop_fee_cap' => '45.00',
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasNoErrors();

    $settings = ShopSettings::current()->fresh();

    expect($settings->shop_fee_enabled)->toBe($expected)
        ->and($settings->shop_fee_mode)->toBe('percent')
        ->and((string) $settings->shop_fee_rate)->toBe('6.000')
        ->and($settings->shop_fee_cap_cents)->toBe(4500);
})->with([
    'box off' => ['0', false],
    'box on' => ['1', true],
]);

test('deposit switches are checkboxes that post 0 when off and 1 when on, and reveal what the deposit includes', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => false,
    ]);

    $response = $this->get(route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'deposits']))->assertOk();

    foreach (['default_deposit_enabled', 'default_deposit_include_parts', 'default_deposit_include_diagnostics'] as $field) {
        $response
            ->assertSee('<input type="hidden" name="'.$field.'" value="0">', false)
            ->assertSee('type="checkbox" name="'.$field.'" value="1"', false)
            ->assertDontSee('<select name="'.$field.'"', false);
    }

    $response
        ->assertSee('Suggest a default deposit')
        ->assertSee('Include in the deposit')
        ->assertSee('x-data="{ depositEnabled: true, depositIncludeDiagnostics: false }"', false)
        ->assertSee('name="default_deposit_diagnostic_labor_category_keys[]"', false)
        ->assertSee('Square deposit capture');

    expect($response->getContent())
        ->toMatch('/<div x-show="depositEnabled"\s*>/')
        ->toMatch('/<fieldset x-show="depositIncludeDiagnostics"\s+x-cloak\s+class="border rounded p-3">/');
});

test('saving deposits with each box on or off persists exactly the posted values', function (string $enabled, string $parts, string $diagnostics) {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'default_deposit_enabled' => $enabled !== '1',
        'default_deposit_include_parts' => $parts !== '1',
        'default_deposit_include_diagnostics' => $diagnostics !== '1',
    ]);

    $this->patch(route('operations.settings.shop.deposits.update'), [
        'default_deposit_enabled' => $enabled,
        'default_deposit_include_parts' => $parts,
        'default_deposit_include_diagnostics' => $diagnostics,
        'default_deposit_diagnostic_labor_category_keys' => ['diagnostic', 'programming'],
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasNoErrors();

    $settings = ShopSettings::reloadCurrent();

    expect($settings->defaultDepositEnabled())->toBe($enabled === '1')
        ->and($settings->defaultDepositIncludeParts())->toBe($parts === '1')
        ->and($settings->defaultDepositIncludeDiagnostics())->toBe($diagnostics === '1')
        ->and($settings->defaultDepositDiagnosticLaborCategoryKeys())->toBe(['diagnostic', 'programming']);
})->with([
    'all off' => ['0', '0', '0'],
    'all on' => ['1', '1', '1'],
    'deposit off keeps the hidden choices' => ['0', '1', '1'],
    'deposit on, parts only' => ['1', '1', '0'],
]);

test('saving deposits with diagnostics off and no labor categories keeps the selection empty', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'default_deposit_include_diagnostics' => true,
        'default_deposit_diagnostic_labor_category_keys' => ['diagnostic', 'programming'],
    ]);

    $this->patch(route('operations.settings.shop.deposits.update'), [
        'default_deposit_enabled' => '1',
        'default_deposit_include_parts' => '1',
        'default_deposit_include_diagnostics' => '0',
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasNoErrors();

    $settings = ShopSettings::reloadCurrent();

    expect($settings->default_deposit_diagnostic_labor_category_keys)->toBe([])
        ->and($settings->defaultDepositIncludeDiagnostics())->toBeFalse()
        ->and($settings->defaultDepositDiagnosticLaborCategoryKeys())->toBe([]);

    $html = $this->get(route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'deposits']))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'name="default_deposit_diagnostic_labor_category_keys[]"'))->toBeGreaterThan(0)
        ->and(preg_match_all('/name="default_deposit_diagnostic_labor_category_keys\[\]"[^>]*\bchecked\b/', $html))->toBe(0);
});

test('saving deposits with diagnostics on and no labor categories is refused and keeps the saved categories', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => false,
        'default_deposit_diagnostic_labor_category_keys' => ['programming'],
    ]);

    $this->from(route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'deposits']))
        ->patch(route('operations.settings.shop.deposits.update'), [
            'default_deposit_enabled' => '1',
            'default_deposit_include_parts' => '0',
            'default_deposit_include_diagnostics' => '1',
        ])->assertSessionHasErrors([
            'default_deposit_diagnostic_labor_category_keys' => 'Select at least one labor category when diagnostics are included.',
        ]);

    $settings = ShopSettings::reloadCurrent();

    expect($settings->defaultDepositIncludeParts())->toBeTrue()
        ->and($settings->defaultDepositIncludeDiagnostics())->toBeFalse()
        ->and($settings->defaultDepositDiagnosticLaborCategoryKeys())->toBe(['programming']);
});

test('deposit diagnostic categories fall back to the default list only when none were ever saved', function () {
    $settings = ShopSettings::current();

    $settings->forceFill([
        'default_deposit_include_diagnostics' => false,
        'default_deposit_diagnostic_labor_category_keys' => null,
    ]);
    expect($settings->defaultDepositDiagnosticLaborCategoryKeys())->toBe(ShopSettings::DEFAULT_DEPOSIT_DIAGNOSTIC_LABOR_CATEGORY_KEYS);

    $settings->forceFill(['default_deposit_diagnostic_labor_category_keys' => []]);
    expect($settings->defaultDepositDiagnosticLaborCategoryKeys())->toBe([]);

    $settings->forceFill(['default_deposit_include_diagnostics' => true]);
    expect($settings->defaultDepositDiagnosticLaborCategoryKeys())->toBe(ShopSettings::DEFAULT_DEPOSIT_DIAGNOSTIC_LABOR_CATEGORY_KEYS);

    $settings->forceFill(['default_deposit_diagnostic_labor_category_keys' => ['programming', 'diagnostic']]);
    expect($settings->defaultDepositDiagnosticLaborCategoryKeys())->toBe(['programming', 'diagnostic']);
});

test('billing class fee rate override disables when shop fees are off in the shared Labor editor', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $html = $this->get(route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'labor']))
        ->assertOk()
        ->assertSee('data-billing-classes-shared', false)
        ->assertSee('data-billing-class-settings-modal', false)
        ->getContent();

    expect($html)
        ->toContain("draft.shop_fees_enabled = \$event.target.value === '1'")
        ->toContain(':disabled="! draft.shop_fees_enabled"')
        ->toContain('Fee rate override')
        ->not->toContain('name="customer_types[0][shop_fees_enabled]"')
        ->not->toContain('id="financial-tab-billing-classes"');
});

test('saving billing classes after switching shop fees stores the override only when fees are on', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'customer_types' => [
            ['name' => 'Retail', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => '4.500', 'discount_type' => 'none'],
            ['name' => 'Internal', 'shop_fees_enabled' => false, 'shop_fee_rate_override' => null, 'discount_type' => 'none'],
        ],
    ]);

    $this->patch(route('operations.settings.shop.customer-types.update'), [
        'customer_types' => [
            ['name' => 'Retail', 'shop_fees_enabled' => '0', 'discount_type' => 'none'],
            ['name' => 'Internal', 'shop_fees_enabled' => '1', 'shop_fee_rate_override' => '3.25', 'discount_type' => 'none'],
        ],
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasNoErrors();

    $rows = collect(ShopSettings::current()->fresh()->customer_types)->keyBy('name');

    expect($rows['Retail']['shop_fees_enabled'])->toBeFalse()
        ->and($rows['Retail']['shop_fee_rate_override'])->toBeNull()
        ->and($rows['Retail']['fee_override'])->toBe('none')
        ->and($rows['Internal']['shop_fees_enabled'])->toBeTrue()
        ->and($rows['Internal']['shop_fee_rate_override'])->toBe('3.250')
        ->and($rows['Internal']['fee_override'])->toBe('3.250');
});

test('clearing a billing class fee rate override goes back to the shop rate, and an unposted override is kept', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'customer_types' => [
            ['name' => 'Retail', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => '4.500', 'discount_type' => 'none'],
            ['name' => 'Business', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => '2.000', 'discount_type' => 'none'],
        ],
    ]);

    $this->patch(route('operations.settings.shop.customer-types.update'), [
        'customer_types' => [
            ['name' => 'Retail', 'shop_fees_enabled' => '1', 'shop_fee_rate_override' => '', 'discount_type' => 'none'],
            ['name' => 'Business', 'shop_fees_enabled' => '1', 'discount_type' => 'none'],
        ],
    ])->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasNoErrors();

    $rows = collect(ShopSettings::current()->fresh()->customer_types)->keyBy('name');

    expect($rows['Retail']['shop_fees_enabled'])->toBeTrue()
        ->and($rows['Retail']['shop_fee_rate_override'])->toBeNull()
        ->and($rows['Retail']['fee_override'])->toBeNull()
        ->and($rows['Business']['shop_fee_rate_override'])->toBe('2.000');
});

test('saving a parts matrix does not rewrite tax settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'tax_enabled' => true,
        'tax_label' => 'C/S Tax',
        'default_tax_rate' => '8.250',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $this->patch(route('operations.settings.shop.parts-matrices.update', 'oem-parts'), [
        'parts_matrices' => [
            [
                'key' => 'oem-parts',
                'name' => 'OEM Dealer Parts',
                'rows' => [
                    ['min_cost' => '0', 'max_cost' => '25', 'markup_percentage' => '95', 'sort_order' => '1'],
                ],
            ],
            [
                'key' => 'aft-parts',
                'name' => 'AFT Parts',
                'rows' => [
                    ['min_cost' => '0', 'max_cost' => '5', 'markup_percentage' => '180', 'sort_order' => '1'],
                ],
            ],
        ],
        'default_parts_matrix_key' => 'oem-parts',
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $settings = ShopSettings::current();

    expect($settings->tax_enabled)->toBeTrue()
        ->and($settings->tax_label)->toBe('C/S Tax')
        ->and((string) $settings->default_tax_rate)->toBe('8.250')
        ->and($settings->taxable_labor)->toBeFalse()
        ->and($settings->taxable_parts)->toBeTrue()
        ->and($settings->partsMatrixByKey('oem-parts')['name'])->toBe('OEM Dealer Parts');
});

test('admin can save a single parts matrix without touching other shop settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'shop_name' => 'Owner Shop Name',
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $this->patch(route('operations.settings.shop.parts-matrices.update', 'oem-parts'), [
        'parts_matrices' => [
            [
                'key' => 'oem-parts',
                'name' => 'OEM Dealer Parts',
                'rows' => [
                    ['min_cost' => '0', 'max_cost' => '25', 'markup_percentage' => '95', 'sort_order' => '1'],
                ],
            ],
            [
                'key' => 'aft-parts',
                'name' => 'AFT Parts',
                'rows' => [
                    ['min_cost' => '0', 'max_cost' => '5', 'markup_percentage' => '180', 'sort_order' => '1'],
                ],
            ],
        ],
        'default_parts_matrix_key' => 'oem-parts',
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $settings = ShopSettings::current();

    expect($settings->shop_name)->toBe('Owner Shop Name')
        ->and($settings->partsMatrixByKey('oem-parts')['name'])->toBe('OEM Dealer Parts')
        ->and($settings->partsMatrixByKey('oem-parts')['rows'][0]['markup_percentage'])->toBe('95.00')
        ->and($settings->defaultPartsMatrix()['key'])->toBe('oem-parts');
});

test('parts matrix autosave accepts json so validation failures are 422 not a successful html redirect', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $before = ShopSettings::current()->parts_matrices;

    $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->patch(route('operations.settings.shop.parts-matrices.update', 'aft-parts'), [
        'parts_matrices' => [
            [
                'key' => 'aft-parts',
                'name' => 'AFT Parts',
                'rows' => [
                    ['min_cost' => '', 'max_cost' => '5', 'markup_percentage' => '180', 'sort_order' => '1'],
                ],
            ],
        ],
        'default_parts_matrix_key' => 'aft-parts',
    ])->assertStatus(422)
        ->assertJsonMissing(['committed' => true]);

    expect(ShopSettings::current()->fresh()->parts_matrices)->toBe($before);
});

test('parts matrix json autosave returns an explicit committed payload and persists a fresh server read', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $baseline = ShopSettings::DEFAULT_PARTS_MATRICES;
    ShopSettings::current()->update(['parts_matrices' => $baseline]);

    $payload = collect($baseline)->map(function (array $matrix): array {
        $rows = collect($matrix['rows'])->map(fn (array $row, int $index): array => [
            'min_cost' => $row['min_cost'],
            'max_cost' => $row['max_cost'] ?? '',
            'markup_percentage' => $matrix['key'] === 'fluids' ? '151' : $row['markup_percentage'],
            'sort_order' => (string) ($index + 1),
        ])->all();

        return [
            'key' => $matrix['key'],
            'name' => $matrix['name'],
            'rows' => $rows,
        ];
    })->values()->all();

    $response = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->patch(route('operations.settings.shop.parts-matrices.update', 'fluids'), [
        'parts_matrices' => $payload,
        'default_parts_matrix_key' => 'aft-parts',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('committed', true)
        ->assertJsonPath('matrix_key', 'fluids');

    $persisted = ShopSettings::current()->fresh();

    expect($persisted->partsMatrixByKey('fluids')['rows'][0]['markup_percentage'])->toBe('151.00')
        ->and($persisted->partsMatrixByKey('aft-parts')['rows'][0]['markup_percentage'])->toBe('180.00')
        ->and($response->json('parts_matrices'))->toBe($persisted->partsMatrices());
});

test('parts matrix json autosave rejects guests and advisors without committing', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES]);
    $before = ShopSettings::current()->fresh()->parts_matrices;

    $this->withHeaders([
        'Accept' => 'application/json',
    ])->patch(route('operations.settings.shop.parts-matrices.update', 'aft-parts'), [
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'default_parts_matrix_key' => 'aft-parts',
    ])->assertUnauthorized();

    expect(ShopSettings::current()->fresh()->parts_matrices)->toBe($before);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $advisorResponse = $this->withHeaders([
        'Accept' => 'application/json',
    ])->patch(route('operations.settings.shop.parts-matrices.update', 'aft-parts'), [
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'default_parts_matrix_key' => 'aft-parts',
    ]);

    expect(in_array($advisorResponse->status(), [401, 403, 302], true))->toBeTrue()
        ->and($advisorResponse->json('committed') ?? null)->toBeNull()
        ->and(ShopSettings::current()->fresh()->parts_matrices)->toBe($before);
});

test('later parts matrix json save wins when two commits are applied in order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $baseline = ShopSettings::DEFAULT_PARTS_MATRICES;
    ShopSettings::current()->update(['parts_matrices' => $baseline]);

    $makePayload = function (string $filtersMarkup) use ($baseline): array {
        return collect($baseline)->map(function (array $matrix) use ($filtersMarkup): array {
            $rows = collect($matrix['rows'])->map(fn (array $row, int $index): array => [
                'min_cost' => $row['min_cost'],
                'max_cost' => $row['max_cost'] ?? '',
                'markup_percentage' => $matrix['key'] === 'filters' ? $filtersMarkup : $row['markup_percentage'],
                'sort_order' => (string) ($index + 1),
            ])->all();

            return [
                'key' => $matrix['key'],
                'name' => $matrix['name'],
                'rows' => $rows,
            ];
        })->values()->all();
    };

    $headers = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    $this->withHeaders($headers)->patch(route('operations.settings.shop.parts-matrices.update', 'filters'), [
        'parts_matrices' => $makePayload('171'),
        'default_parts_matrix_key' => 'aft-parts',
    ])->assertOk()->assertJsonPath('committed', true);

    $this->withHeaders($headers)->patch(route('operations.settings.shop.parts-matrices.update', 'filters'), [
        'parts_matrices' => $makePayload('174'),
        'default_parts_matrix_key' => 'aft-parts',
    ])->assertOk()->assertJsonPath('committed', true);

    $fresh = ShopSettings::current()->fresh();

    expect($fresh->partsMatrixByKey('filters')['rows'][0]['markup_percentage'])->toBe('174.00')
        ->and($fresh->partsMatrixByKey('oem-parts')['rows'][0]['markup_percentage'])->toBe('90.00')
        ->and($fresh->partsMatrixByKey('aft-parts')['rows'][0]['markup_percentage'])->toBe('180.00');
});

test('parts matrix save keyed to one matrix persists every submitted sibling unchanged except the edited tier', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $baseline = ShopSettings::DEFAULT_PARTS_MATRICES;
    ShopSettings::current()->update(['parts_matrices' => $baseline]);

    $payload = collect($baseline)->map(function (array $matrix): array {
        $rows = collect($matrix['rows'])->map(fn (array $row, int $index): array => [
            'min_cost' => $row['min_cost'],
            'max_cost' => $row['max_cost'] ?? '',
            'markup_percentage' => $row['markup_percentage'],
            'sort_order' => (string) ($index + 1),
        ])->all();

        if ($matrix['key'] === 'filters') {
            $rows[0]['markup_percentage'] = '177';
        }

        return [
            'key' => $matrix['key'],
            'name' => $matrix['name'],
            'rows' => $rows,
        ];
    })->values()->all();

    $this->patch(route('operations.settings.shop.parts-matrices.update', 'filters'), [
        'parts_matrices' => $payload,
        'default_parts_matrix_key' => collect($baseline)->firstWhere('is_default', true)['key'] ?? $baseline[0]['key'],
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $settings = ShopSettings::current()->fresh();
    $expectedKeys = collect($baseline)->pluck('key')->sort()->values()->all();
    $actualKeys = collect($settings->parts_matrices)->pluck('key')->sort()->values()->all();

    expect($actualKeys)->toBe($expectedKeys)
        ->and($settings->partsMatrixByKey('filters')['rows'][0]['markup_percentage'])->toBe('177.00');

    foreach ($baseline as $matrix) {
        if ($matrix['key'] === 'filters') {
            continue;
        }

        expect($settings->partsMatrixByKey($matrix['key'])['rows'][0]['markup_percentage'])
            ->toBe($matrix['rows'][0]['markup_percentage']);
    }
});

test('advisor cannot access or edit shop settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    // SettingsAuthorizationFailureResponse: HTML → Job Board + errors (not bare 403).
    $this->get('/app/settings/shop')
        ->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors('settings');

    $this->patch(route('operations.settings.shop.general.update'), [
        'shop_name' => 'Unauthorized Update',
    ])->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors('settings');

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_label' => 'C/S Tax',
        'default_tax_rate' => '8.250',
        'taxable_labor' => '0',
        'taxable_parts' => '1',
    ])->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors('settings');

    $this->patch(route('operations.settings.shop.parts-matrices.update', 'aft-parts'), [
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ])->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors('settings');

    expect(ShopSettings::query()->where('shop_name', 'Unauthorized Update')->exists())->toBeFalse();
});
test('shop settings navigation is hidden from advisors', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Shop Settings');
});

test('shop settings seeder provides current business defaults without wiping operational settings', function () {
    Storage::fake('public');

    $this->seed(ShopSettingsSeeder::class);

    $settings = ShopSettings::current();

    expect($settings->shop_name)->toBe('Demo Auto Repair')
        ->and($settings->phone)->toBe('719-555-0100')
        ->and($settings->email)->toBe('hello@demo-auto.test')
        ->and($settings->website)->toBe('https://demo-auto.test')
        ->and($settings->address_line_1)->toBe('100 Main Street')
        ->and($settings->address_line_2)->toBe('Suite A')
        ->and($settings->postal_code)->toBe('80909')
        ->and($settings->logo_path)->toBe('shop-logos/demo-shop-logo.webp')
        ->and($settings->default_labor_rate_cents)->toBe(16500)
        ->and($settings->tax_enabled)->toBeTrue()
        ->and($settings->tax_label)->toBe('C/S Tax')
        ->and((string) $settings->default_tax_rate)->toBe('8.250')
        ->and($settings->taxable_labor)->toBeFalse()
        ->and($settings->taxable_parts)->toBeTrue()
        ->and($settings->parts_matrices)->toHaveCount(5)
        ->and(collect($settings->partsMatrices())->pluck('name')->all())->toBe(['AFT Parts', 'Filters', 'Fluids', 'OEM Parts', 'Warranty (No Markup)'])
        ->and($settings->defaultPartsMatrix()['name'])->toBe('AFT Parts')
        ->and($settings->defaultPartsMatrix()['rows'][0]['margin_percentage'])->toBe('64')
        ->and($settings->partsMatrixByKey('oem-parts')['rows'][0]['markup_percentage'])->toBe('90.00')
        ->and($settings->partsMatrixByKey('filters')['rows'][5]['max_cost'])->toBe('99999.99')
        ->and(collect($settings->customer_types)->pluck('name')->all())->toContain('Retail', 'Warranty', 'Military')
        ->and(collect($settings->customer_types)->firstWhere('name', 'Warranty')['shop_fees_enabled'])->toBeFalse()
        ->and(collect($settings->customer_types)->firstWhere('name', 'Warranty')['default_parts_matrix_key'])->toBe('warranty-no-markup')
        ->and(collect($settings->customer_types)->firstWhere('name', 'Military')['discount_type'])->toBe('labor')
        ->and(collect($settings->customer_types)->firstWhere('name', 'Military')['discount_amount'])->toBe('10.00')
        ->and($settings->estimate_disclaimer)->toBe(ShopSettings::DEFAULT_ESTIMATE_DISCLAIMER)
        ->and($settings->recommendation_disclaimer)->toBe(ShopSettings::DEFAULT_RECOMMENDATION_DISCLAIMER)
        ->and($settings->default_estimate_state)->toBe(RepairOrderStatus::Estimate->value)
        ->and($settings->appointments_enabled)->toBeTrue()
        ->and($settings->operational_profile)->toBe(OperationalProfile::RepairShop->value);

    Storage::disk('public')->assertExists('shop-logos/demo-shop-logo.webp');

    $settings->update([
        'shop_name' => 'Owner Changed Shop',
        'default_labor_rate_cents' => 18100,
        'shop_fee_cap_cents' => 4200,
    ]);

    $this->seed(ShopSettingsSeeder::class);

    expect($settings->fresh()->shop_name)->toBe('Demo Auto Repair')
        ->and($settings->fresh()->default_labor_rate_cents)->toBe(18100)
        ->and($settings->fresh()->shop_fee_cap_cents)->toBe(4200);
});

test('labor settings save applies the standard hourly rate and ignores obsolete category form fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $beforeDiagnostic = collect(ShopSettings::current()->laborCategories())->firstWhere('key', 'diagnostic');

    $this->patch(route('operations.settings.shop.labor.update'), laborSettingsPayload([
        'standard_hourly_rate' => '180.00',
        // Obsolete Labor-categories table fields must not drive persistence after the Work Types matrix redesign.
        'labor_categories' => collect(ShopSettings::DEFAULT_LABOR_CATEGORIES)->values()->map(function (array $category): array {
            return [
                'key' => $category['key'],
                'name' => $category['name'],
                'rate' => match ($category['key']) {
                    'mechanical' => '180.00',
                    'diagnostic' => '195.00',
                    default => number_format($category['rate_cents'] / 100, 2, '.', ''),
                },
                'minimum_hours' => $category['key'] === 'diagnostic' ? '1.25' : $category['minimum_hours'],
                'rounding_rule' => $category['key'] === 'courtesy' ? 'tenth' : $category['rounding_rule'],
            ];
        })->all(),
    ]))->assertRedirect(route('operations.settings.shop.edit'));

    $settings = ShopSettings::current();
    $diagnostic = collect($settings->laborCategories())->firstWhere('key', 'diagnostic');
    $mechanical = collect($settings->laborCategories())->firstWhere('key', 'mechanical');

    expect($settings->default_labor_rate_cents)->toBe(18000)
        ->and($mechanical['rate_cents'])->toBe(18000)
        ->and($mechanical['is_default'])->toBeTrue()
        ->and($diagnostic['rate_cents'])->toBe($beforeDiagnostic['rate_cents'])
        ->and($diagnostic['minimum_hours'])->toBe($beforeDiagnostic['minimum_hours'])
        ->and(collect($settings->laborCategories())->firstWhere('key', 'courtesy')['rounding_rule'])
        ->toBe(collect(ShopSettings::DEFAULT_LABOR_CATEGORIES)->firstWhere('key', 'courtesy')['rounding_rule']);
});

test('the default category rate row is the shop default labor rate', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $payload = laborSettingsPayload([
        'standard_hourly_rate' => '172.25',
    ]);
    $payload['labor_categories'] = collect($payload['labor_categories'])
        ->map(function (array $category): array {
            if ($category['key'] === ShopSettings::DEFAULT_LABOR_CATEGORY_KEY) {
                $category['rate'] = '172.25';
            }

            return $category;
        })
        ->all();

    $this->patch(route('operations.settings.shop.labor.update'), $payload)
        ->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasNoErrors();

    $settings = ShopSettings::current();

    expect($settings->default_labor_rate_cents)->toBe(17225)
        ->and($settings->defaultLaborRate())->toBe('172.25')
        ->and(collect($settings->laborCategories())->firstWhere('key', ShopSettings::DEFAULT_LABOR_CATEGORY_KEY)['rate_cents'])->toBe(17225);
});

test('labor settings save does not change the default category from obsolete form fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $before = collect(ShopSettings::current()->laborCategories())->mapWithKeys(
        fn (array $category): array => [$category['key'] => $category['rate_cents']],
    );
    $mechanicalRate = number_format($before[ShopSettings::DEFAULT_LABOR_CATEGORY_KEY] / 100, 2, '.', '');

    $this->patch(route('operations.settings.shop.labor.update'), laborSettingsPayload([
        'standard_hourly_rate' => $mechanicalRate,
        // Retired table field — must not reassign which category is default.
        'default_labor_category_key' => 'diagnostic',
    ]))->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasNoErrors();

    $settings = ShopSettings::current();
    $after = collect($settings->laborCategories())->mapWithKeys(
        fn (array $category): array => [$category['key'] => $category['rate_cents']],
    );

    // Default category ownership stays on the Labor Matrix / category authority, not the retired table form.
    expect($after->all())->toEqual($before->all())
        ->and(collect($settings->laborCategories())->firstWhere('is_default', true)['key'])
        ->toBe(ShopSettings::DEFAULT_LABOR_CATEGORY_KEY)
        ->and($settings->default_labor_rate_cents)->toBe($before[ShopSettings::DEFAULT_LABOR_CATEGORY_KEY]);
});

test('labor categories default repairpal and warranty with adjust labor off', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $settings = ShopSettings::current();

    expect(collect($settings->laborCategories())->firstWhere('key', 'repairpal')['allows_modifiers'])->toBeFalse()
        ->and(collect($settings->laborCategories())->firstWhere('key', 'warranty-other')['allows_modifiers'])->toBeFalse()
        ->and(collect($settings->laborCategories())->firstWhere('key', 'mechanical')['allows_modifiers'])->toBeTrue();
});

test('labor settings save ignores obsolete adjust-labor category form fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $payload = laborSettingsPayload();
    $payload['labor_categories'] = collect($payload['labor_categories'])
        ->map(function (array $category): array {
            if ($category['key'] === ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY) {
                $category['allows_modifiers'] = '1';
            }

            return $category;
        })
        ->all();

    $this->patch(route('operations.settings.shop.labor.update'), $payload)
        ->assertRedirect(route('operations.settings.shop.edit'));

    expect(collect(ShopSettings::current()->laborCategories())->firstWhere('key', 'repairpal')['allows_modifiers'])->toBeFalse();
});

test('admin can delete a non-default parts matrix', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => [
            ['name' => 'Retail', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'fee_override' => null, 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => null],
            ['name' => 'Fleet', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'fee_override' => null, 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => 'filters'],
        ],
    ]);

    $this->delete(route('operations.settings.shop.parts-matrices.destroy', 'filters'), [
        'confirm_name' => 'Filters',
    ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'financial']));

    $settings = ShopSettings::current();

    expect(collect($settings->partsMatrices())->pluck('key')->all())
        ->not->toContain('filters')
        ->and(collect($settings->parts_matrices)->firstWhere('key', 'filters')['is_active'] ?? true)->toBeFalse()
        ->and(collect($settings->customer_types)->firstWhere('name', 'Fleet')['default_parts_matrix_key'])->toBeNull()
        ->and($settings->defaultPartsMatrix()['key'])->toBe('aft-parts');
});

test('admin cannot delete the default parts matrix', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $this->from(route('operations.settings.shop.edit'))
        ->delete(route('operations.settings.shop.parts-matrices.destroy', 'aft-parts'), [
            'confirm_name' => 'AFT Parts',
        ])
        ->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasErrors('parts_matrices');

    expect(collect(ShopSettings::current()->partsMatrices())->pluck('key')->all())->toContain('aft-parts');
});

test('admin cannot delete the last parts matrix', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'parts_matrices' => [
            [
                'key' => 'aft-parts',
                'name' => 'AFT Parts',
                'is_default' => true,
                'rows' => ShopSettings::DEFAULT_PARTS_MATRICES[1]['rows'],
            ],
        ],
    ]);

    $this->from(route('operations.settings.shop.edit'))
        ->delete(route('operations.settings.shop.parts-matrices.destroy', 'oem-parts'))
        ->assertNotFound();

    $this->from(route('operations.settings.shop.edit'))
        ->delete(route('operations.settings.shop.parts-matrices.destroy', 'aft-parts'), [
            'confirm_name' => 'AFT Parts',
        ])
        ->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasErrors('parts_matrices');

    expect(ShopSettings::current()->partsMatrices())->toHaveCount(1);
});

test('admin cannot delete a parts matrix without confirming the matrix name', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $this->from(route('operations.settings.shop.edit'))
        ->delete(route('operations.settings.shop.parts-matrices.destroy', 'filters'), [
            'confirm_name' => 'Wrong Name',
        ])
        ->assertRedirect(route('operations.settings.shop.edit'))
        ->assertSessionHasErrors('confirm_name');

    expect(collect(ShopSettings::current()->partsMatrices())->pluck('key')->all())->toContain('filters');
});

test('admin can save label printing orientation for brother ql labels', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.settings.shop.edit', ['section' => 'printing']))
        ->assertOk()
        ->assertSee('Label orientation');

    $this->from(route('operations.settings.shop.edit', ['section' => 'printing']))
        ->patch(route('operations.settings.shop.printing.update'), [
            'qz_printing_enabled' => '1',
            'qz_printing_key_tag_printer' => 'Brother QL-800',
            'qz_key_tag_orientation' => 'portrait',
            'qz_key_tag_vin_display' => 'last6',
            'qz_key_tag_media_type' => 'mono',
            'oil_change_sticker_next_due_months' => 6,
            'oil_change_interval_miles' => 5000,
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'printing']));

    expect(ShopSettings::current()->qz_key_tag_orientation)->toBe('portrait');
});

test('shop settings current returns the same instance per request', function () {
    $this->seed(ShopSettingsSeeder::class);
    ShopSettings::forgetCurrent();

    $first = ShopSettings::current();
    $second = ShopSettings::current();

    expect($first)->toBe($second);
});

test('shop settings memoization clears after save', function () {
    ShopSettings::forgetCurrent();

    $settings = ShopSettings::current();
    $settings->update(['shop_name' => 'Memoization Test Shop']);

    expect(ShopSettings::current()->shop_name)->toBe('Memoization Test Shop');
});

test('empty shop settings are created from install defaults', function () {
    ShopSettings::query()->delete();
    ShopSettings::forgetCurrent();

    $settings = ShopSettings::current()->fresh();
    $defaults = shopSettingsInstallDefaults();
    $columns = array_flip(array_map(strtolower(...), Schema::getColumnListing('shop_settings')));
    $expected = new ShopSettings;
    $expected->forceFill($defaults);

    expect(ShopSettings::query()->count())->toBe(1)
        ->and($settings->shop_name)->toBeNull();

    foreach ($defaults as $column => $value) {
        if (! isset($columns[strtolower($column)])) {
            expect(array_key_exists($column, $settings->getAttributes()))->toBeFalse();

            continue;
        }

        expect($settings->getAttribute($column))->toEqual($expected->getAttribute($column));
    }
});

test('existing shop settings are returned unchanged', function () {
    $existing = ShopSettings::current();
    $existing->update([
        'shop_name' => 'Kept Shop',
        'default_labor_rate_cents' => 9900,
    ]);
    ShopSettings::forgetCurrent();

    $settings = ShopSettings::current();

    expect(ShopSettings::query()->count())->toBe(1)
        ->and($settings->is($existing))->toBeTrue()
        ->and($settings->shop_name)->toBe('Kept Shop')
        ->and($settings->default_labor_rate_cents)->toBe(9900);
});

/**
 * @return array<string, mixed>
 */
function shopSettingsInstallDefaults(): array
{
    $method = new \ReflectionMethod(ShopSettings::class, 'installDefaults');
    $method->setAccessible(true);

    return $method->invoke(null);
}
