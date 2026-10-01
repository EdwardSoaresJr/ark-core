<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\LaborGuides\LaborGuideIntent;
use App\Ark\Operations\LaborGuides\LaborGuideProvider;
use App\Ark\Operations\Parts\PartsCatalogProvider;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Preferences\EstimateToolbarPreference;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

function estimateToolbarRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'vin' => '1HGCM82633A004352',
        'normalized_vin' => '1HGCM82633A004352',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Toolbar defaults',
    ]);
}

test('labor and parts toolbar defaults fall back to the first available option', function () {
    expect(EstimateToolbarPreference::resolve(null, EstimateToolbarPreference::KIND_LABOR, [
        LaborGuideIntent::KEY,
        LaborGuideProvider::AllData->value,
    ]))->toBe(LaborGuideIntent::KEY)
        ->and(EstimateToolbarPreference::resolve(null, EstimateToolbarPreference::KIND_PARTS, [
            PartsCatalogProvider::PartsTech->value,
            PartsCatalogProvider::Nexpart->value,
        ]))->toBe(PartsCatalogProvider::PartsTech->value);
});

test('stored toolbar defaults that are not available fall back', function () {
    $user = User::factory()->create([
        'default_labor_guide' => LaborGuideProvider::ProDemand->value,
        'default_parts_catalog' => PartsCatalogProvider::Nexpart->value,
    ]);

    expect(EstimateToolbarPreference::resolve($user, EstimateToolbarPreference::KIND_LABOR, [
        LaborGuideIntent::KEY,
        LaborGuideProvider::AllData->value,
    ]))->toBe(LaborGuideIntent::KEY)
        ->and(EstimateToolbarPreference::resolve($user, EstimateToolbarPreference::KIND_PARTS, [
            PartsCatalogProvider::PartsTech->value,
        ]))->toBe(PartsCatalogProvider::PartsTech->value);
});

test('advisor can persist labor guide and parts catalog toolbar defaults', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->postJson(route('operations.estimate-toolbar.default'), [
            'kind' => EstimateToolbarPreference::KIND_LABOR,
            'key' => LaborGuideProvider::AllData->value,
        ])
        ->assertOk()
        ->assertJsonPath('key', LaborGuideProvider::AllData->value);

    $this->actingAs($advisor)
        ->postJson(route('operations.estimate-toolbar.default'), [
            'kind' => EstimateToolbarPreference::KIND_PARTS,
            'key' => PartsCatalogProvider::PartsTech->value,
        ])
        ->assertOk()
        ->assertJsonPath('key', PartsCatalogProvider::PartsTech->value);

    expect($advisor->fresh()->default_labor_guide)->toBe(LaborGuideProvider::AllData->value)
        ->and($advisor->fresh()->default_parts_catalog)->toBe(PartsCatalogProvider::PartsTech->value);
});

test('estimate toolbar default rejects unknown keys', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->postJson(route('operations.estimate-toolbar.default'), [
            'kind' => EstimateToolbarPreference::KIND_LABOR,
            'key' => 'mitchell',
        ])
        ->assertStatus(422);
});

test('estimate worksheet pins the saved labor guide on the main button', function () {
    config()->set('services.labor_guides.alldata.base_url', 'https://alldata.test/repair');
    config()->set('services.labor_guides.prodemand.base_url', 'https://prodemand.test');

    $advisor = User::factory()->create([
        'default_labor_guide' => LaborGuideProvider::AllData->value,
    ])->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', estimateToolbarRepairOrder()))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('>AllData</span>')
        ->toContain('Make default')
        ->toContain('Other labor guides')
        ->toContain('laborGuideDefaultKey:');
});

test('estimate worksheet keeps parts tech on the main button when nexpart is not connected', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $advisor = User::factory()->create([
        'default_parts_catalog' => PartsCatalogProvider::Nexpart->value,
    ])->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', estimateToolbarRepairOrder()))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('>PartsTech</span>')
        ->toContain('>Pull Cart</span>')
        ->not->toContain('>Nexpart</span>')
        ->toContain('partsCatalogSelectedKey:')
        ->toContain('selectPartsCatalog(');
});
