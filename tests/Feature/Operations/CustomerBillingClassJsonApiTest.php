<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\CommercialPricingProfile;
use App\Ark\Operations\EstimatePricing\CustomerBillingClassIdentity;
use App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrixProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    ShopSettings::current()->update([
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
        'commercial_pricing_profile_version' => 1,
    ]);
});

test('customer types catalog exposes stable keys and customer-only classes', function () {
    $response = $this->actingAs($this->admin)
        ->getJson(route('operations.settings.shop.customer-types.show'))
        ->assertOk();

    $keys = collect($response->json('classes'))->pluck('key')->all();

    expect($keys)->toContain('retail', 'fleet', 'warranty', 'repairpal', 'internal', 'military', 'business')
        ->and(collect($response->json('classes'))->firstWhere('key', 'military'))
        ->not->toHaveKey('labor_column_key')
        ->and(collect($response->json('classes'))->firstWhere('key', 'retail')['labor_column_key'])
        ->toBe('customer_pay');
});

test('item update saves commercial fields without rewriting open RO commercial stamps', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'Identity',
        'phone' => '555-0180',
        'customer_type' => 'Military',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2022,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Identity stamp guard',
        'commercial_pricing_profile' => CommercialPricingProfile::fromShopSettings(ShopSettings::current())->toArray(),
    ]);
    $before = $ro->fresh()->commercial_pricing_profile;

    $this->actingAs($this->admin)
        ->patchJson(route('operations.settings.shop.customer-types.item.update', ['classKey' => 'military']), [
            'name' => 'Military',
            'pill_color' => 'green',
            'document_presentation_profile' => 'retail',
            'shop_fees_enabled' => true,
            'shop_fee_rate_override' => null,
            'default_parts_matrix_key' => null,
            'discount_type' => 'labor',
            'discount_amount' => '12.00',
            'concern_billing_posture' => null,
        ])
        ->assertOk()
        ->assertJsonPath('class.discount_amount', '12.00')
        ->assertJsonPath('class.key', 'military');

    expect($ro->fresh()->commercial_pricing_profile)->toBe($before);

    $military = collect(ShopSettings::current()->storedCustomerTypeRows())->firstWhere('key', 'military');
    expect($military['discount_amount'])->toBe('12.00')
        ->and($military['pill_color'] ?? null)->toBe('green');
});

test('creating a customer billing class does not create a labor column', function () {
    $columnsBefore = collect(app(LaborWorkTypeMatrixProjection::class)->build()['columns'])->pluck('value')->all();

    $this->actingAs($this->admin)
        ->postJson(route('operations.settings.shop.customer-types.store'), [
            'name' => 'Preferred Partner',
            'pill_color' => 'blue',
            'document_presentation_profile' => 'retail',
            'shop_fees_enabled' => true,
            'discount_type' => 'none',
        ])
        ->assertCreated()
        ->assertJsonPath('class.key', 'preferred_partner')
        ->assertJsonMissingPath('class.labor_column_key');

    $columnsAfter = collect(app(LaborWorkTypeMatrixProjection::class)->build()['columns'])->pluck('value')->all();

    expect($columnsAfter)->toBe($columnsBefore)
        ->and(CustomerBillingClassIdentity::classKeyForLaborColumn('preferred_partner'))->toBeNull();
});

test('labor matrix projection links only explicit built-in associations', function () {
    $matrix = app(LaborWorkTypeMatrixProjection::class)->build();
    $byValue = collect($matrix['columns'])->keyBy('value');

    expect($byValue['customer_pay']['customer_class_key'])->toBe('retail')
        ->and($byValue['fleet']['customer_class_key'])->toBe('fleet')
        ->and($byValue['warranty_other']['customer_class_key'])->toBe('warranty')
        ->and($byValue['repairpal']['customer_class_key'])->toBe('repairpal')
        ->and($byValue['internal']['customer_class_key'])->toBe('internal')
        ->and(collect($matrix['billing_classes'])->firstWhere('key', 'military')['customer_only'])->toBeTrue()
        ->and(collect($matrix['billing_classes'])->firstWhere('key', 'business')['customer_only'])->toBeTrue();
});

test('labor settings page uses shared billing classes editor and renamed labor column action', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor',
            'work_type' => 'troubleshooting',
        ]))
        ->assertOk()
        ->assertSee('data-billing-classes-shared', false)
        ->assertSee('+ Add Labor Column')
        ->assertSee('Manage Billing Classes')
        ->assertSee('Class Settings')
        ->assertSee('data-labor-class-settings', false)
        ->assertSee('Does not create a customer Billing Class', false);
});

test('billing classes tab still renders shared editor entry point', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'billing-classes',
        ]))
        ->assertOk()
        ->assertSee('data-billing-classes-shared', false)
        ->assertSee('data-settings-billing-classes', false)
        ->assertSee('+ Add Billing Class')
        ->assertSee('Customer only');
});
