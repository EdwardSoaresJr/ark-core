<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

function hybridIndexFixture(): array
{
    $customer = Customer::query()->create(['first_name' => 'Hybrid', 'last_name' => 'Index', 'phone' => '7195550777']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2020, 'make' => 'Toyota', 'model' => 'Tacoma', 'plate' => 'HYB123']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Hybrid list concern',
    ]);

    return [$customer, $vehicle, $repairOrder];
}

dataset('hybrid indexes', [
    'repair orders' => ['operations.repair-orders.index', 'Hybrid list concern'],
    'customers' => ['operations.customers.search', 'Hybrid Index'],
    'vehicles' => ['operations.vehicles.search', 'Tacoma'],
]);

it('renders the list view as hybrid records with no table', function (string $routeName, string $expected): void {
    hybridIndexFixture();

    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route($routeName, ['view' => 'list']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('ark-collection-hybrid')
        ->and($html)->toContain('data-collection-hybrid-sort-compact')
        ->and($html)->toContain($expected)
        ->and($html)->toContain('ark-status-edge')
        ->and($html)->toContain('data-status-edge=')
        ->and($html)->not->toContain('ark-collection-list-table')
        ->and($html)->not->toContain('ark-collection-mobile-list')
        ->and($html)->toContain('data-collection-view="cards"');
})->with('hybrid indexes');

it('paints the Repair Orders hybrid row edge from the RO status tone', function (): void {
    [, , $repairOrder] = hybridIndexFixture();

    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.index', ['view' => 'list']))
        ->assertOk()
        ->getContent();

    $tone = $repairOrder->fresh()->status->indexTone();

    expect($html)->toContain('ark-status-edge--'.$tone)
        ->and($html)->toContain('data-status-edge="'.$tone.'"');
});

it('keeps sort links and preserves filters in the compact sort form', function (): void {
    hybridIndexFixture();

    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search', ['view' => 'list', 'q' => 'Hybrid']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-collection-sort="name"')
        ->and($html)->toContain('name="q" value="Hybrid"')
        ->and($html)->toContain('<select')
        ->and($html)->toContain('name="sort"');
});

it('shows open repair order counts for customers without per-row queries', function (): void {
    hybridIndexFixture();
    foreach (range(1, 4) as $n) {
        $other = Customer::query()->create(['first_name' => 'Extra', 'last_name' => 'Hybrid'.$n, 'phone' => '71955508'.$n.'0']);
        $otherVehicle = Vehicle::query()->create(['customer_id' => $other->id, 'year' => 2015, 'make' => 'Ford', 'model' => 'Ranger']);
        RepairOrder::query()->create([
            'customer_id' => $other->id,
            'vehicle_id' => $otherVehicle->id,
            'status' => RepairOrderStatus::Estimate,
            'concern_summary' => 'Extra',
        ]);
    }

    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor)->get(route('operations.customers.search', ['view' => 'list']))->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $html = $this->actingAs($advisor)->get(route('operations.customers.search', ['view' => 'list']))->assertOk()->getContent();
    $customerQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'repair_orders'))->count();

    expect($html)->toContain('1 open RO')
        ->and($customerQueries)->toBeLessThanOrEqual(4);
});
