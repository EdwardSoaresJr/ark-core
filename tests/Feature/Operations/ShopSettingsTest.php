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

    return array_merge([
        'default_labor_rate' => '165.00',
        'default_labor_category_key' => ShopSettings::DEFAULT_LABOR_CATEGORY_KEY,
        'labor_categories' => $categories,
    ], $overrides);
}

test('admin can view operational shop settings defaults', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertSee('Operational Settings')
        ->assertDontSee('Current outcomes')
        ->assertSee('Financial rules, estimate defaults, and document language')
        ->assertSee('Shop Identity')
        ->assertSee('Financial Rules')
        ->assertSee('Documents / Disclaimers')
        ->assertSee('Workflow Defaults')
        ->assertSee('Default visit posture')
        ->assertSee('Check In visit')
        ->assertSee('Worksheet notes')
        ->assertSee('Estimate logo')
        ->assertSee('Default labor rate')
        ->assertSee('Sales tax rate')
        ->assertSee('Tax display name')
        ->assertSee('Tax labor')
        ->assertSee('Tax parts')
        ->assertSee('Used when calculating taxable estimate/invoice totals.')
        ->assertSee('Effective posture')
        ->assertSee('shop fees not taxable')
        ->assertSee('Tax shop fees')
        ->assertDontSee('Default tax rate')
        ->assertDontSee('Taxable labor')
        ->assertDontSee('Taxable parts')
        ->assertSee('Parts Matrix')
        ->assertSee('AFT Parts')
        ->assertSee('OEM Parts')
        ->assertSee('Warranty (No Markup)')
        ->assertSee('Billing Classes')
        ->assertSee('Warranty')
        ->assertSee('Military')
        ->assertSee('Markup is the editable pricing policy')
        ->assertSee('Editable authority')
        ->assertSee('Read-only truth')
        ->assertSee('Target posture')
        ->assertSee('line overrides remain operational exceptions')
        ->assertSee('Save for margin')
        ->assertDontSee('marginFromMarkup(row.markup_percentage)', false)
        ->assertSee('Add Row')
        ->assertSee('Remove')
        ->assertSee('Fleet shop fees')
        ->assertSee('Warranty parts matrix')
        ->assertSee('Standard billing class')
        ->assertSee('name="customer_types[0][shop_fees_enabled]"', false)
        ->assertSee('name="customer_types[2][shop_fees_enabled]"', false)
        ->assertDontSee('Default estimate state')
        ->assertDontSee('>Order<', false)
        ->assertDontSee('name="parts_matrices[1][rows][0][margin_percentage]"', false)
        ->assertSee(ShopSettings::DEFAULT_ESTIMATE_DISCLAIMER)
        ->assertSee(ShopSettings::DEFAULT_RECOMMENDATION_DISCLAIMER)
        ->assertDontSee('name="default_estimate_state"', false)
        ->assertSee('150.00')
        ->assertSee('180.00');
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
    ])->assertRedirect(route('operations.settings.shop.edit'));

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
        'default_labor_rate' => '175.50',
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
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $this->patch(route('operations.settings.shop.workflow.update'), [
        'default_visit_mode' => 'needs_shuttle',
        'default_recommendation_intent' => 'immediate_attention',
        'default_notes_private' => '1',
        'default_estimate_state' => RepairOrderStatus::Estimate->value,
    ])->assertRedirect(route('operations.settings.shop.edit'));

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
        ->and(collect($settings->customer_types)->firstWhere('name', 'Warranty')['default_parts_matrix_key'])->toBe('aft-parts')
        ->and($settings->customer_types[2]['discount_amount'])->toBe('10.00')
        ->and($settings->customer_types[2]['default_parts_matrix_key'])->toBe('warranty-no-markup')
        ->and($settings->customer_types[3]['discount_type'])->toBe('none')
        ->and($settings->estimate_validity_days)->toBe(14)
        ->and($settings->default_visit_mode)->toBe('needs_shuttle')
        ->and($settings->default_recommendation_intent)->toBe('immediate_attention')
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

test('saving tax settings recalculates living repair order line tax without taxing shop fees', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'tax_enabled' => true,
        'default_tax_rate' => '10.000',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => null,
    ]);

    $repairOrder = repairOrderForFinancialAuthority();
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

    expect($part->shop_fee_cents)->toBe(1000)
        ->and($part->tax_cents)->toBe(1000)
        ->and($part->total_cents)->toBe(12000);
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

test('admin can update labor categories from shop settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->patch(route('operations.settings.shop.labor.update'), laborSettingsPayload([
        'default_labor_rate' => '180.00',
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
        ->and($diagnostic['rate_cents'])->toBe(19500)
        ->and($diagnostic['minimum_hours'])->toBe('1.25')
        ->and(collect($settings->laborCategories())->firstWhere('key', 'courtesy')['rounding_rule'])->toBe('tenth');
});

test('labor categories default repairpal and warranty with adjust labor off', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $settings = ShopSettings::current();

    expect(collect($settings->laborCategories())->firstWhere('key', 'repairpal')['allows_modifiers'])->toBeFalse()
        ->and(collect($settings->laborCategories())->firstWhere('key', 'warranty-other')['allows_modifiers'])->toBeFalse()
        ->and(collect($settings->laborCategories())->firstWhere('key', 'mechanical')['allows_modifiers'])->toBeTrue();
});

test('admin can enable adjust labor on a program category', function () {
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

    expect(collect(ShopSettings::current()->laborCategories())->firstWhere('key', 'repairpal')['allows_modifiers'])->toBeTrue();
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
