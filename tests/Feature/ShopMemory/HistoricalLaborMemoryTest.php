<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\ShopMemory\Projections\LaborSuggestionProjection;
use App\Ark\ShopMemory\Providers\HistoricalLaborProvider;
use App\Ark\ShopMemory\Suggestion\SuggestionContext;
use App\Ark\ShopMemory\Suggestion\SuggestionCorpus;
use App\Ark\ShopMemory\Suggestion\SuggestionProviderRegistry;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;


beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

function shopMemoryRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Ada',
        'last_name' => 'Advisor',
        'phone' => '555-0202',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Shop Memory labor seed',
    ]);
}

function seedLaborDescriptions(RepairOrder $repairOrder, array $descriptions): void
{
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    foreach ($descriptions as $index => $description) {
        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor,
            'description' => $description,
            'quantity' => '1.00',
            'unit_price_cents' => 16500,
            'subtotal_cents' => 16500,
            'total_cents' => 16500,
            'labor_category_key' => 'mechanical',
            'labor_category_name' => 'Mechanical',
            'labor_entered_hours' => '1.00',
            'labor_billed_hours' => '1.00',
            'labor_rate_cents' => 16500,
            'position' => $index + 1,
        ]);
    }
}

test('shop memory registers historical labor provider', function (): void {
    expect(app(SuggestionProviderRegistry::class)->keys())
        ->toBe([HistoricalLaborProvider::KEY]);
});

test('historical labor provider returns matching work language by usage', function (): void {
    $repairOrder = shopMemoryRepairOrder();

    seedLaborDescriptions($repairOrder, [
        'Replace front brake pads and rotors',
        'Replace front brake pads and rotors',
        'Replace front brake pads and rotors',
        'Replace serpentine belt',
        'Replace water pump',
    ]);

    $provider = new HistoricalLaborProvider;
    $suggestions = $provider->suggest(new SuggestionContext(
        query: 'replace front',
        corpus: SuggestionCorpus::WorkLanguage,
        limit: 8,
    ));

    expect($suggestions)->not->toBeEmpty()
        ->and($suggestions[0]->text)->toBe('Replace front brake pads and rotors')
        ->and($suggestions[0]->providerKey)->toBe(HistoricalLaborProvider::KEY)
        ->and($suggestions[0]->corpus)->toBe(SuggestionCorpus::WorkLanguage);
});

test('labor suggestion projection surfaces historical labor language', function (): void {
    $repairOrder = shopMemoryRepairOrder();

    seedLaborDescriptions($repairOrder, [
        'Replace front brake pads and rotors',
        'Replace rear brake pads and rotors',
    ]);

    $result = app(LaborSuggestionProjection::class)->suggest('brake pads', 8);

    expect($result->texts())->toContain('Replace front brake pads and rotors')
        ->and($result->texts())->toContain('Replace rear brake pads and rotors');
});

test('labor memory suggest endpoint returns shop memory payload', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = shopMemoryRepairOrder();

    seedLaborDescriptions($repairOrder, [
        'Replace front brake pads and rotors',
    ]);

    $this->actingAs($user)
        ->getJson(route('operations.repair-orders.labor-memory-suggest', $repairOrder).'?q=replace+front')
        ->assertOk()
        ->assertJsonPath('suggestions.0.text', 'Replace front brake pads and rotors')
        ->assertJsonPath('suggestions.0.provider', HistoricalLaborProvider::KEY);
});

test('historical labor provider ignores short queries and non-labor lines', function (): void {
    $repairOrder = shopMemoryRepairOrder();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Parts',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Replace front brake pads and rotors',
        'quantity' => '1.00',
        'unit_price_cents' => 5000,
        'subtotal_cents' => 5000,
        'total_cents' => 5000,
        'position' => 1,
    ]);

    $provider = new HistoricalLaborProvider;

    expect($provider->suggest(new SuggestionContext(
        query: 'r',
        corpus: SuggestionCorpus::WorkLanguage,
    )))->toBeEmpty()
        ->and($provider->suggest(new SuggestionContext(
            query: 'replace front',
            corpus: SuggestionCorpus::WorkLanguage,
        )))->toBeEmpty();
});
