<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Preferences\CollectionLayout;
use App\Ark\Runtime\Preferences\CollectionLayoutModule;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

it('customers index serves list and cards over the same authorized set', function () {
    $customer = Customer::query()->create([
        'first_name' => 'List',
        'last_name' => 'Cards Customer',
        'phone' => '555-7100',
    ]);

    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search', ['view' => 'list', 'q' => 'Cards Customer']))
        ->assertOk()
        ->assertPlainCookie(CollectionLayoutModule::Customers->cookieName(), CollectionLayout::List->value)
        ->getContent();

    expect($html)->toContain('data-collection')
        ->and($html)->toContain('data-collection-view="list"')
        ->and($html)->toContain('data-collection-view="cards"')
        ->and($html)->toContain('data-collection-layout-toggle')
        ->and($html)->toContain('data-ark-ui-tenant=')
        ->and($html)->toContain('data-ark-ui-user=')
        ->and($html)->toContain('List Cards Customer')
        ->and($html)->toContain('data-collection-sort="name"')
        ->and($html)->toContain('ark-collection-hybrid')
        ->and($html)->not->toContain('ark-collection-list-table');

    $cards = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search', ['view' => 'cards', 'q' => 'Cards Customer']))
        ->assertOk()
        ->getContent();

    expect($cards)->toContain('List Cards Customer')
        ->and($cards)->toContain(route('operations.customers.show', $customer));
});

it('customers list sorts by name and preserves search context', function () {
    Customer::query()->create([
        'first_name' => 'Zed',
        'last_name' => 'Sort',
        'phone' => '555-7101',
    ]);
    Customer::query()->create([
        'first_name' => 'Ann',
        'last_name' => 'Sort',
        'phone' => '555-7102',
    ]);

    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search', [
            'view' => 'list',
            'q' => 'Sort',
            'sort' => 'name',
            'dir' => 'asc',
        ]))
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'Ann Sort'))->toBeLessThan(strpos($html, 'Zed Sort'))
        ->and($html)->toContain('aria-sort="ascending"');
});

it('vehicles index supports list cards toggle and plate sort chrome', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Vehicle',
        'last_name' => 'Owner',
        'phone' => '555-7103',
    ]);
    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'plate' => 'ABC1234',
    ]);

    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.vehicles.search', ['view' => 'list', 'q' => 'Civic']))
        ->assertOk()
        ->assertPlainCookie(CollectionLayoutModule::Vehicles->cookieName(), CollectionLayout::List->value)
        ->getContent();

    expect($html)->toContain('data-collection-module="vehicles"')
        ->and($html)->toContain('Honda')
        ->and($html)->toContain('data-collection-sort="plate"')
        ->and($html)->toContain('ark-collection-hybrid')
        ->and($html)->not->toContain('ark-collection-list-table')
        ->and($html)->toContain('ABC1234');
});

it('repair orders index list preserves filters and drill-down links', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Collection List Brakes');
    $repairOrder->update(['concern_summary' => 'Collection list brakes']);

    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.index', [
            'view' => 'list',
            'status' => RepairOrderStatus::WaitingApproval->value,
            'q' => 'brakes',
        ]))
        ->assertOk()
        ->assertPlainCookie(CollectionLayoutModule::RepairOrders->cookieName(), CollectionLayout::List->value)
        ->getContent();

    expect($html)->toContain('data-collection-module="repair_orders"')
        ->and($html)->toContain('RO #'.$repairOrder->repair_order_id)
        ->and($html)->toContain(route('operations.repair-orders.show', $repairOrder))
        ->and($html)->toContain('name="status"')
        ->and($html)->toContain('ark-collection-hybrid')
        ->and($html)->not->toContain('ark-collection-list-table')
        ->and($html)->not->toContain('data-job-board');
});

it('empty search states remain available for all three indexes', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.customers.search', ['q' => 'Nobody Here Ever']))
        ->assertOk()
        ->assertSee('No customers match', false);

    $this->actingAs($advisor)
        ->get(route('operations.vehicles.search', ['q' => 'Nobody Here Ever']))
        ->assertOk()
        ->assertSee('No vehicles match', false);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index', ['q' => 'Nobody Here Ever']))
        ->assertOk()
        ->assertSee('No repair orders match these filters', false);
});
