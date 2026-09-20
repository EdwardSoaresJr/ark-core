<?php

namespace Database\Seeders;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\OperationalProfile;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ShopSettingsSeeder extends Seeder
{
    private const SEEDED_LOGO_SOURCE = 'seed-assets/operations/demo-shop-logo.webp';

    private const SEEDED_LOGO_PATH = 'shop-logos/demo-shop-logo.webp';

    private const SHOP_IDENTITY = [
        'shop_name' => 'Demo Auto Repair',
        'phone' => '719-555-0100',
        'email' => 'hello@demo-auto.test',
        'website' => 'https://demo-auto.test',
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'Suite A',
        'city' => 'Demo City',
        'state' => 'CO',
        'postal_code' => '80909',
    ];

    private const PARTS_MATRICES = [
        [
            'key' => 'oem-parts',
            'name' => 'OEM Parts',
            'is_default' => false,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => '25.00', 'markup_percentage' => '90.00', 'sort_order' => 1],
                ['min_cost' => '25.01', 'max_cost' => '100.00', 'markup_percentage' => '70.00', 'sort_order' => 2],
                ['min_cost' => '100.01', 'max_cost' => '250.00', 'markup_percentage' => '55.00', 'sort_order' => 3],
                ['min_cost' => '250.01', 'max_cost' => '500.00', 'markup_percentage' => '45.00', 'sort_order' => 4],
                ['min_cost' => '500.01', 'max_cost' => '1000.00', 'markup_percentage' => '40.00', 'sort_order' => 5],
                ['min_cost' => '1000.01', 'max_cost' => '2500.00', 'markup_percentage' => '35.00', 'sort_order' => 6],
                ['min_cost' => '2500.01', 'max_cost' => '99999.99', 'markup_percentage' => '32.00', 'sort_order' => 7],
            ],
        ],
        [
            'key' => 'aft-parts',
            'name' => 'AFT Parts',
            'is_default' => true,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => '5.00', 'markup_percentage' => '180.00', 'sort_order' => 1],
                ['min_cost' => '5.01', 'max_cost' => '10.00', 'markup_percentage' => '150.00', 'sort_order' => 2],
                ['min_cost' => '10.01', 'max_cost' => '25.00', 'markup_percentage' => '120.00', 'sort_order' => 3],
                ['min_cost' => '25.01', 'max_cost' => '50.00', 'markup_percentage' => '110.00', 'sort_order' => 4],
                ['min_cost' => '50.01', 'max_cost' => '100.00', 'markup_percentage' => '95.00', 'sort_order' => 5],
                ['min_cost' => '100.01', 'max_cost' => '200.00', 'markup_percentage' => '85.00', 'sort_order' => 6],
                ['min_cost' => '200.01', 'max_cost' => '500.00', 'markup_percentage' => '70.00', 'sort_order' => 7],
                ['min_cost' => '500.01', 'max_cost' => '1000.00', 'markup_percentage' => '55.00', 'sort_order' => 8],
                ['min_cost' => '1000.01', 'max_cost' => '2500.00', 'markup_percentage' => '45.00', 'sort_order' => 9],
                ['min_cost' => '2500.01', 'max_cost' => '99999.99', 'markup_percentage' => '38.00', 'sort_order' => 10],
            ],
        ],
        [
            'key' => 'fluids',
            'name' => 'Fluids',
            'is_default' => false,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => '10.00', 'markup_percentage' => '150.00', 'sort_order' => 1],
                ['min_cost' => '10.01', 'max_cost' => '25.00', 'markup_percentage' => '110.00', 'sort_order' => 2],
                ['min_cost' => '25.01', 'max_cost' => '50.00', 'markup_percentage' => '80.00', 'sort_order' => 3],
                ['min_cost' => '50.01', 'max_cost' => '100.00', 'markup_percentage' => '65.00', 'sort_order' => 4],
                ['min_cost' => '100.01', 'max_cost' => '250.00', 'markup_percentage' => '55.00', 'sort_order' => 5],
                ['min_cost' => '250.01', 'max_cost' => '99999.99', 'markup_percentage' => '45.00', 'sort_order' => 6],
            ],
        ],
        [
            'key' => 'warranty-no-markup',
            'name' => 'Warranty (No Markup)',
            'is_default' => false,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => null, 'markup_percentage' => '0.00', 'sort_order' => 1],
            ],
        ],
        [
            'key' => 'filters',
            'name' => 'Filters',
            'is_default' => false,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => '10.00', 'markup_percentage' => '130.00', 'sort_order' => 1],
                ['min_cost' => '10.01', 'max_cost' => '25.00', 'markup_percentage' => '95.00', 'sort_order' => 2],
                ['min_cost' => '25.01', 'max_cost' => '50.00', 'markup_percentage' => '75.00', 'sort_order' => 3],
                ['min_cost' => '50.01', 'max_cost' => '100.00', 'markup_percentage' => '60.00', 'sort_order' => 4],
                ['min_cost' => '100.01', 'max_cost' => '250.00', 'markup_percentage' => '50.00', 'sort_order' => 5],
                ['min_cost' => '250.01', 'max_cost' => '99999.99', 'markup_percentage' => '45.00', 'sort_order' => 6],
            ],
        ],
    ];

    private const CUSTOMER_TYPES = ShopSettings::DEFAULT_CUSTOMER_TYPES;

    public function run(): void
    {
        $settings = ShopSettings::current();
        $logoPath = $this->seedLogoPath();

        $settings->fill([
            ...self::SHOP_IDENTITY,
            'logo_path' => $logoPath,
            'default_labor_rate_cents' => $settings->default_labor_rate_cents ?: 15000,
            'tax_enabled' => true,
            'tax_label' => blank($settings->tax_label) || $settings->tax_label === 'Tax' ? 'C/S Tax' : $settings->tax_label,
            'default_tax_rate' => '8.250',
            'taxable_labor' => false,
            'taxable_parts' => true,
            'taxable_shop_fees' => false,
            'shop_fee_enabled' => true,
            'shop_fee_rate' => '5.000',
            'shop_fee_cap_cents' => $settings->shop_fee_cap_cents ?? 3500,
            'parts_matrix' => $settings->parts_matrix ?: ShopSettings::DEFAULT_PARTS_MATRIX,
            'parts_matrices' => self::PARTS_MATRICES,
            'customer_types' => self::CUSTOMER_TYPES,
            'estimate_disclaimer' => $settings->estimate_disclaimer ?: ShopSettings::DEFAULT_ESTIMATE_DISCLAIMER,
            'invoice_disclaimer' => $settings->invoice_disclaimer ?: ShopSettings::DEFAULT_INVOICE_DISCLAIMER,
            'recommendation_disclaimer' => $settings->recommendation_disclaimer ?: ShopSettings::DEFAULT_RECOMMENDATION_DISCLAIMER,
            'authorization_language' => $settings->authorization_language ?: ShopSettings::DEFAULT_AUTHORIZATION_LANGUAGE,
            'customer_type_disclaimers' => $settings->customer_type_disclaimers ?: ShopSettings::defaultCustomerTypeDisclaimerMap(),
            'estimate_validity_days' => 7,
            'default_recommendation_intent' => $settings->default_recommendation_intent ?: 'maintenance',
            'default_estimate_state' => $settings->default_estimate_state ?: RepairOrderStatus::Estimate->value,
            'shop_excellence_targets' => $settings->shop_excellence_targets ?: ShopExcellenceTargets::DEFAULTS,
            'appointments_enabled' => true,
            'operational_profile' => $settings->operational_profile ?: OperationalProfile::RepairShop->value,
        ])->save();
    }

    private function seedLogoPath(): ?string
    {
        $sourcePath = resource_path(self::SEEDED_LOGO_SOURCE);

        if (! File::exists($sourcePath)) {
            return null;
        }

        Storage::disk('public')->put(self::SEEDED_LOGO_PATH, File::get($sourcePath));

        return self::SEEDED_LOGO_PATH;
    }
}
