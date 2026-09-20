<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\ShopMemory\Providers\HistoricalConcernProvider;
use App\Ark\ShopMemory\Providers\HistoricalLaborProvider;
use App\Ark\ShopMemory\ShopMemoryFeatures;
use App\Ark\ShopMemory\ShopMemoryProviderCatalog;
use App\Ark\ShopMemory\ShopMemorySuggestionEvent;
use App\Ark\ShopMemory\Suggestion\SuggestionEngine;
use App\Ark\ShopMemory\Suggestion\SuggestionOutcome;
use App\Ark\ShopMemory\Suggestion\SuggestionProviderRegistry;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;


beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

function shopMemoryV1Advisor(): User
{
    $user = User::factory()->create();
    $user->assignRole(ArkRole::Advisor->value);

    return $user;
}

function shopMemoryV1RepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Mem',
        'last_name' => 'Ory',
        'phone' => '555-0303',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Shop Memory v1 seed',
    ]);
}

test('default enablement registers only historical labor', function (): void {
    expect(ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::HISTORICAL_LABOR))->toBeTrue()
        ->and(ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::HISTORICAL_CONCERN))->toBeFalse()
        ->and(ShopMemoryFeatures::addConcernPopupEnabled())->toBeTrue()
        ->and(app(SuggestionProviderRegistry::class)->keys())->toBe([HistoricalLaborProvider::KEY]);
});

test('diagnostics show disabled providers as not registered not missing', function (): void {
    $lines = implode("\n", app(SuggestionEngine::class)->diagnostics()->lines());

    expect($lines)->toContain('historical_labor')
        ->and($lines)->toMatch('/historical_labor\s+enabled\s+registered\s+healthy/')
        ->and($lines)->toMatch('/historical_concern\s+disabled\s+not registered/')
        ->and($lines)->not->toMatch('/historical_concern\s+disabled\s+not registered\s+missing/');
});

test('concern memory suggest returns empty when problem-language providers are off', function (): void {
    $user = shopMemoryV1Advisor();
    $repairOrder = shopMemoryV1RepairOrder();

    $this->actingAs($user)
        ->getJson(route('operations.repair-orders.concern-memory-suggest', $repairOrder).'?q=brake')
        ->assertOk()
        ->assertJson([
            'enabled' => false,
            'suggestions' => [],
        ]);
});

test('ai rewrite returns 404 when disabled', function (): void {
    $user = shopMemoryV1Advisor();
    $repairOrder = shopMemoryV1RepairOrder();

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.ai-rewrite', $repairOrder), [
            'text' => 'customer says brakes shake',
        ])
        ->assertNotFound();
});

test('suggestion event store persists frozen outcomes', function (): void {
    $user = shopMemoryV1Advisor();
    $repairOrder = shopMemoryV1RepairOrder();

    $this->actingAs($user)
        ->postJson(route('operations.shop-memory.suggestion-events.store'), [
            'provider_key' => 'historical_labor',
            'suggestion_id' => 'historical_labor:abc',
            'outcome' => SuggestionOutcome::AcceptedUnchanged->value,
            'surface' => 'labor_entry',
            'query' => 'brake',
            'repair_order_id' => $repairOrder->id,
        ])
        ->assertCreated();

    expect(ShopMemorySuggestionEvent::query()->count())->toBe(1)
        ->and(ShopMemorySuggestionEvent::query()->first()->outcome)->toBe(SuggestionOutcome::AcceptedUnchanged);
});

test('enabling historical concern registers provider and answers suggest', function (): void {
    $settings = ShopSettings::current();
    $memory = ShopMemoryProviderCatalog::defaultSettings();
    $memory['providers'][ShopMemoryProviderCatalog::HISTORICAL_CONCERN] = true;
    $settings->forceFill(['shop_memory' => $memory])->save();

    // Reboot registry for this process — features read DB; re-register manually for test.
    $registry = app(SuggestionProviderRegistry::class);
    if (! in_array(HistoricalConcernProvider::KEY, $registry->keys(), true)) {
        $registry->register(app(HistoricalConcernProvider::class));
    }

    expect(ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::HISTORICAL_CONCERN))->toBeTrue();

    $user = shopMemoryV1Advisor();
    $repairOrder = shopMemoryV1RepairOrder();

    $this->actingAs($user)
        ->getJson(route('operations.repair-orders.concern-memory-suggest', $repairOrder).'?q=xx')
        ->assertOk()
        ->assertJsonPath('enabled', true);
});

test('repair order edit renders add work workspace modal entry when surface enabled', function (): void {
    $user = shopMemoryV1Advisor();
    $repairOrder = shopMemoryV1RepairOrder();

    $this->actingAs($user)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('+ Add Work', false)
        ->assertSee('Customer Concern', false)
        ->assertSee('What would you like to add?', false)
        ->assertSee('id="workspace-modal-host"', false)
        ->assertDontSee('ops-worksheet-entry-intake__trigger', false);
});
