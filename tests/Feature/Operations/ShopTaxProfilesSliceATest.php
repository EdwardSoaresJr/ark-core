<?php

use App\Ark\Operations\EstimatePricing\ShopTaxCatalog;
use App\Ark\Operations\EstimatePricing\TaxProfile;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
});

test('legacy single-rate tax save still projects TaxProfile schema 1 and stamps null living ROs', function () {
    ShopSettings::current()->update([
        'tax_enabled' => true,
        'tax_label' => 'Old Tax',
        'default_tax_rate' => '7.000',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'tax_profiles' => null,
    ]);

    $customer = App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Tax',
        'last_name' => 'SliceA',
        'phone' => '555-0174',
        'customer_type' => 'Retail',
    ]);
    $vehicle = App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Tax stamp',
        'tax_profile' => null,
    ]);

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_label' => 'C/S Tax',
        'default_tax_rate' => '8.250',
        'taxable_labor' => '0',
        'taxable_parts' => '1',
        'taxable_shop_fees' => '0',
        'tax_document_presentation' => 'combined',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $settings = ShopSettings::current()->fresh();
    expect($settings->tax_label)->toBe('C/S Tax')
        ->and((string) $settings->default_tax_rate)->toStartWith('8.250')
        ->and($settings->taxProfiles())->toHaveCount(1)
        ->and(ShopTaxCatalog::combinedRate($settings->taxProfiles()[0]))->toBe('8.250')
        ->and($settings->taxDocumentPresentation())->toBe('combined');

    $profile = TaxProfile::fromShopSettings($settings);
    expect($profile->schema)->toBe(1)
        ->and($profile->rate)->toStartWith('8.250')
        ->and($profile->label)->toBe('C/S Tax');

    expect($ro->fresh()->tax_profile['rate'])->toStartWith('7.000');
});

test('multi-profile catalog save syncs legacy columns from the default profile combined rate', function () {
    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_document_presentation' => 'itemized',
        'tax_profiles' => [
            [
                'key' => 'colorado-springs',
                'name' => 'Colorado Springs',
                'is_default' => '1',
                'is_active' => '1',
                'state' => 'CO',
                'cities' => 'Colorado Springs',
                'zip_codes' => '80903, 80909',
                'taxable_labor' => '0',
                'taxable_parts' => '1',
                'taxable_shop_fees' => '1',
                'components' => [
                    [
                        'id' => 'comp-state',
                        'name' => 'State',
                        'rate' => '2.900',
                        'jurisdiction_type' => 'state',
                        'is_active' => '1',
                    ],
                    [
                        'id' => 'comp-city',
                        'name' => 'City',
                        'rate' => '3.120',
                        'jurisdiction_type' => 'city',
                        'is_active' => '1',
                    ],
                    [
                        'id' => 'comp-county',
                        'name' => 'County',
                        'rate' => '1.230',
                        'jurisdiction_type' => 'county',
                        'is_active' => '0',
                    ],
                ],
            ],
            [
                'key' => 'out-of-state',
                'name' => 'Out-of-state service',
                'is_default' => '0',
                'is_active' => '1',
                'taxable_labor' => '0',
                'taxable_parts' => '0',
                'taxable_shop_fees' => '0',
                'components' => [
                    [
                        'id' => 'comp-zero',
                        'name' => 'No local tax',
                        'rate' => '0.000',
                        'jurisdiction_type' => 'other',
                        'is_active' => '1',
                    ],
                ],
            ],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $settings = ShopSettings::current()->fresh();
    expect($settings->taxProfiles())->toHaveCount(2)
        ->and($settings->taxDocumentPresentation())->toBe('itemized')
        ->and($settings->tax_label)->toBe('Colorado Springs')
        ->and((string) $settings->default_tax_rate)->toStartWith('6.020')
        ->and($settings->taxable_parts)->toBeTrue()
        ->and($settings->taxable_shop_fees)->toBeTrue()
        ->and($settings->taxable_labor)->toBeFalse();

    $default = collect($settings->taxProfiles())->firstWhere('is_default', true);
    expect($default['components'])->toHaveCount(3)
        ->and(collect($default['components'])->where('is_active', true))->toHaveCount(2)
        ->and($default['zip_codes'])->toContain('80903');
});

test('inactive components remain stored and do not affect the combined rate', function () {
    $profile = [
        'key' => 'fountain',
        'name' => 'Fountain',
        'is_default' => true,
        'is_active' => true,
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'components' => [
            ['id' => 'a', 'name' => 'State', 'rate' => '2.900', 'jurisdiction_type' => 'state', 'is_active' => true],
            ['id' => 'b', 'name' => 'Retired district', 'rate' => '9.000', 'jurisdiction_type' => 'special_district', 'is_active' => false],
        ],
    ];

    $normalized = ShopTaxCatalog::normalizeProfiles([$profile]);
    expect(ShopTaxCatalog::combinedRate($normalized[0]))->toBe('2.900')
        ->and($normalized[0]['components'])->toHaveCount(2);
});
