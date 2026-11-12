<?php

use App\Ark\Operations\EstimatePricing\LaborBillingMinimumMode;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimums;
use App\Ark\Operations\EstimatePricing\LaborPricingProfile;
use App\Ark\Operations\EstimatePricing\TaxSourcingLocationKind;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPrintMenuProjection;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($this->admin);

    ShopSettings::current()->update([
        'tax_enabled' => true,
        'tax_document_presentation' => 'combined',
        'default_tax_rate' => '6.020',
        'tax_label' => 'Sales Tax',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'tax_profiles' => [[
            'key' => 'springs',
            'name' => 'Colorado Springs',
            'is_default' => true,
            'is_active' => true,
            'state' => 'CO',
            'cities' => ['Colorado Springs'],
            'zip_codes' => ['80903'],
            'taxable_labor' => false,
            'taxable_parts' => true,
            'taxable_shop_fees' => false,
            'components' => [
                ['id' => 'st', 'name' => 'State', 'rate' => '2.900', 'jurisdiction_type' => 'state', 'sort_order' => 1, 'is_active' => true],
                ['id' => 'city', 'name' => 'City', 'rate' => '3.120', 'jurisdiction_type' => 'city', 'sort_order' => 2, 'is_active' => true],
            ],
        ]],
        'qz_printing_enabled' => true,
    ]);
    ShopSettings::forgetCurrent();
});

function workspaceUiRo(): RepairOrder
{
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Workspace',
        'last_name' => 'UI',
        'phone' => '555-0100',
        'customer_type' => 'Retail',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80903',
    ]);
    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Workspace UI acceptance',
    ]);
}

test('tax jurisdiction lives in a modal opened from the sales tax row, not a standalone card', function () {
    $ro = workspaceUiRo();

    $html = $this->get(route('operations.repair-orders.show', $ro))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('data-tax-jurisdiction-card')
        ->and($html)->toContain('data-tax-jurisdiction-modal')
        ->and($html)->toContain('data-tax-jurisdiction-trigger')
        ->and($html)->toContain('data-tax-summary-row')
        ->and($html)->toContain('ark-open-tax-jurisdiction');
});

test('tax jurisdiction store still confirms profile through the existing endpoint', function () {
    $ro = workspaceUiRo();

    $this->post(route('operations.repair-orders.tax-profile.store', $ro), [
        'tax_profile_key' => 'springs',
        'tax_sourcing_location_kind' => TaxSourcingLocationKind::Shop->value,
        'confirm_jurisdiction' => '1',
        \App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD => (int) $ro->fresh()->estimate_version,
    ])->assertRedirect(route('operations.repair-orders.show', $ro));

    expect($ro->fresh()->tax_profile['profile_key'] ?? null)->toBe('springs')
        ->and($ro->fresh()->tax_profile['sourcing']['selection_status'] ?? null)->toBe('confirmed');
});

test('header and footer print menus expose identical authoritative items', function () {
    $ro = workspaceUiRo();

    $items = RepairOrderPrintMenuProjection::items($ro->fresh(), null, $this->admin);
    $keys = collect($items)->pluck('key')->all();

    expect($keys)->toContain('key_tag', 'oil_sticker', 'check_in_sheet', 'estimate_pdf')
        ->and($keys)->not->toContain('final_invoice');

    freezePostedInvoiceSnapshot($ro, 10000, 602);
    $invoice = \App\Ark\Operations\Documents\EstimateDocument::query()
        ->where('repair_order_id', $ro->id)
        ->latest('id')
        ->firstOrFail();
    $withInvoice = RepairOrderPrintMenuProjection::items($ro->fresh(), ['invoice' => $invoice], $this->admin);
    expect(collect($withInvoice)->pluck('key')->all())->toContain('final_invoice')
        ->and(collect($withInvoice)->pluck('label')->all())->toContain('Final Invoice');

    $html = $this->get(route('operations.repair-orders.show', $ro->fresh()))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-ro-print-menu'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('data-ro-footer-print')
        ->and($html)->toContain('dropup-menu')
        ->and(substr_count($html, 'data-ro-print-item="estimate_pdf"'))->toBe(2)
        ->and(substr_count($html, 'data-ro-print-item="check_in_sheet"'))->toBe(2);
});

test('concern headers share accent wash and ordinal, not priority stripes', function () {
    $ro = workspaceUiRo();
    RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
    ]);
    RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Tires',
        'disposition' => RepairOrderConcernDisposition::Recommended,
    ]);

    $html = $this->get(route('operations.repair-orders.show', $ro))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('ops-concern-header-accent')
        ->and($html)->toContain('Concern 1')
        ->and($html)->toContain('Concern 2')
        ->and($html)->not->toContain('card-status-start bg-');
});

test('ro-wide billing minimum renders once near totals without concern alert banners', function () {
    $ro = workspaceUiRo();
    $ro->forceFill([
        'labor_pricing_profile' => (new LaborPricingProfile(
            version: 1,
            billingMinimums: [
                'customer_pay' => [
                    'mode' => LaborBillingMinimumMode::PerRepairOrder->value,
                    'hours' => '1.00',
                    'shortfall_rate_cents' => 16500,
                    'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
                ],
            ],
            defaultLaborRateCents: 16500,
            schema: 1,
        ))->toArray(),
    ])->save();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Labor',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diag',
        'quantity' => '0.50',
        'labor_entered_hours' => '0.50',
        'labor_billed_hours' => '0.50',
        'unit_price_cents' => 16500,
        'labor_rate_cents' => 16500,
        'subtotal_cents' => 8250,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 8250,
    ]);

    $html = $this->get(route('operations.repair-orders.show', $ro->fresh(['lines', 'concerns'])))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-billing-minimum-ro-summary')
        ->and($html)->not->toContain('data-billing-minimum-explanation');
});
