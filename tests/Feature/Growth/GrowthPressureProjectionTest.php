<?php

use App\Ark\Growth\Authority\AuthorityLedgerProjection;
use App\Ark\Growth\Authority\GrowthPressureProjection;
use App\Ark\Growth\Models\GrowthLocationMetric;
use App\Ark\Growth\Models\GrowthSearchQuery;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('growth pressure surfaces missed review opportunities', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Review',
        'last_name' => 'Miss',
        'phone' => '555-0199',
        'email' => 'driver@example.com',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'REV1',
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'close_variant_key' => 'paid',
        'closed_at' => now()->subDays(2),
        'review_request_sent' => null,
        'concern_summary' => 'Check engine light',
    ]);

    $projection = app(GrowthPressureProjection::class)->resolve();

    expect(collect($projection['rows'])->firstWhere('key', 'missed_review_opportunities')['posture'])->toBe('pressure')
        ->and($projection['missed_review_opportunities'])->toHaveCount(1);
});

test('authority ledger separates effort from earned', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Repeat',
        'last_name' => 'Driver',
        'phone' => '555-0188',
        'created_at' => now()->subYear(),
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'REP1',
        'year' => 2017,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'close_variant_key' => 'paid',
        'closed_at' => now()->subMonths(2),
        'concern_summary' => 'Oil change',
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'close_variant_key' => 'paid',
        'closed_at' => now()->subDays(3),
        'review_request_sent' => true,
        'review_request_recorded_at' => now()->subDays(3),
        'concern_summary' => 'Brakes',
    ]);

    $ledger = app(AuthorityLedgerProjection::class)->resolve();

    expect(collect($ledger['effort']['entries'])->firstWhere('label', 'Review requested at close')['count'])->toBe(1)
        ->and(collect($ledger['earned']['entries'])->firstWhere('label', 'Returning customer visit')['count'])->toBe(1)
        ->and(collect($ledger['earned']['entries'])->firstWhere('label', 'Review requested at close'))->toBeNull();
});

test('website performance includes market pressure section', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    GrowthSearchQuery::query()->create([
        'query' => 'lugs and plugs',
        'report_date' => now()->toDateString(),
        'period_start' => now()->subDays(6)->toDateString(),
        'period_end' => now()->toDateString(),
        'clicks' => 2,
        'impressions' => 20,
        'ctr' => 0.1,
        'position' => 1,
    ]);

    GrowthLocationMetric::query()->create([
        'metric' => 'CALL_CLICKS',
        'report_date' => now()->toDateString(),
        'value' => 3,
    ]);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('website.performance'))
        ->assertOk()
        ->assertSee('Market pressure', false)
        ->assertSee('Authority effort', false);
});

test('owner today surfaces market pressure with advisor breakdown', function (): void {
    $owner = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Admin->value);
    completeRequiredLearnFor($owner);

    $ben = User::factory()->create(['name' => 'Ben Advisor'])->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Market',
        'last_name' => 'Pressure',
        'phone' => '555-0177',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'MKT1',
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'close_variant_key' => 'paid',
        'closed_at' => now()->subDays(2),
        'review_request_sent' => true,
        'review_request_recorded_by' => $ben->id,
        'review_request_recorded_at' => now()->subDays(2),
        'concern_summary' => 'Brakes',
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'close_variant_key' => 'paid',
        'closed_at' => now()->subDay(),
        'review_request_sent' => null,
        'concern_summary' => 'Oil change',
    ]);

    $this->actingAs($owner)
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Market pressure')
        ->assertSee('Advisor breakdown')
        ->assertSee('Ben Advisor')
        ->assertSee('missed review opportunit', false);
});
