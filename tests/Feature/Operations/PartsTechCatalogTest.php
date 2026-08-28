<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\PartsTechCartPreparer;
use App\Ark\Operations\Parts\PartsTechCatalogLauncher;
use App\Ark\Operations\Parts\PartsTechShopReference;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('parts tech catalog redirects with vehicle vin and repair order number', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.api_key', 'secret-key');
    config()->set('services.partstech.base_url', 'https://partstech.test');
    config()->set('services.partstech.catalog_path', '');

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
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);
    $repairOrder->refresh();

    $partsTechReference = PartsTechShopReference::cartReference($repairOrder);

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => Http::sequence()
            ->push(['data' => ['activeCart' => ['id' => 'cart-old', 'repairOrderNumber' => '99', 'orders' => []]]])
            ->push(['data' => ['archiveCart' => ['cart' => ['id' => 'cart-old']]]])
            ->push(['data' => ['activeCart' => null]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['createCart' => ['cart' => ['id' => 'cart-new', 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateCart' => ['cart' => ['id' => 'cart-new', 'repairOrderNumber' => $partsTechReference, 'notes' => 'ARK repair order '.$partsTechReference]]]])
            ->push(['data' => ['activateCart' => ['cart' => ['id' => 'cart-new', 'active' => true, 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateActiveCartPurchaseOrderNumber' => ['__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload', 'updatedCartId' => 'cart-new']]])
            ->push(['data' => ['activeCart' => ['orders' => []]]])
            ->push(['data' => ['vehicles' => [['id' => 'vehicle-1', 'vin' => '1HGCM82633A004352']]]])
            ->push(['data' => ['linkVehicleToCart' => ['cart' => ['id' => 'cart-new']]]]),
    ]);

    $response = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.partstech', [
            'repairOrder' => $repairOrder,
            'concern_id' => 12,
        ]));

    $response->assertRedirectContains('https://partstech.test?')
        ->assertRedirectContains('vin=1HGCM82633A004352')
        ->assertRedirectContains('repairOrderNumber='.$partsTechReference)
        ->assertRedirectContains('poNumber='.$partsTechReference)
        ->assertRedirectContains('concern_id=12');

    expect($response->headers->get('Location'))
        ->not->toContain('username=')
        ->not->toContain('secret-key');

    Http::assertSentCount(13);
});

test('parts tech prepare endpoint returns catalog url and preparation status', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

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
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);
    $repairOrder->refresh();

    $partsTechReference = PartsTechShopReference::cartReference($repairOrder);

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => Http::sequence()
            ->push(['data' => ['activeCart' => ['id' => 'cart-old', 'repairOrderNumber' => '99', 'orders' => []]]])
            ->push(['data' => ['archiveCart' => ['cart' => ['id' => 'cart-old']]]])
            ->push(['data' => ['activeCart' => null]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['createCart' => ['cart' => ['id' => 'cart-new', 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateCart' => ['cart' => ['id' => 'cart-new', 'repairOrderNumber' => $partsTechReference, 'notes' => 'ARK repair order '.$partsTechReference]]]])
            ->push(['data' => ['activateCart' => ['cart' => ['id' => 'cart-new', 'active' => true, 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateActiveCartPurchaseOrderNumber' => ['__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload', 'updatedCartId' => 'cart-new']]])
            ->push(['data' => ['activeCart' => ['orders' => []]]])
            ->push(['data' => ['vehicles' => [['id' => 'vehicle-1', 'vin' => '1HGCM82633A004352']]]])
            ->push(['data' => ['linkVehicleToCart' => ['cart' => ['id' => 'cart-new']]]]),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder))
        ->assertOk()
        ->assertJsonPath('prepared', true)
        ->assertJsonPath('catalog_url', fn (string $url): bool => str_contains($url, 'repairOrderNumber='.$partsTechReference))
        ->assertJsonPath('catalog_url', fn (string $url): bool => str_contains($url, 'poNumber='.$partsTechReference))
        ->assertJsonPath('repair_order_number', (string) $repairOrder->repair_order_id);
});

test('parts tech prepare includes concern_id on catalog url when requested', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

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
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake pads',
    ]);
    $repairOrder->refresh();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => 'recommended',
        'position' => 1,
    ]);

    $partsTechReference = PartsTechShopReference::cartReference($repairOrder);

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => Http::sequence()
            ->push(['data' => ['activeCart' => ['id' => 'cart-old', 'repairOrderNumber' => '99', 'orders' => []]]])
            ->push(['data' => ['archiveCart' => ['cart' => ['id' => 'cart-old']]]])
            ->push(['data' => ['activeCart' => null]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['createCart' => ['cart' => ['id' => 'cart-new', 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateCart' => ['cart' => ['id' => 'cart-new', 'repairOrderNumber' => $partsTechReference, 'notes' => 'ARK repair order '.$partsTechReference]]]])
            ->push(['data' => ['activateCart' => ['cart' => ['id' => 'cart-new', 'active' => true, 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateActiveCartPurchaseOrderNumber' => ['__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload', 'updatedCartId' => 'cart-new']]])
            ->push(['data' => ['activeCart' => ['orders' => []]]])
            ->push(['data' => ['vehicles' => [['id' => 'vehicle-1', 'vin' => '1HGCM82633A004352']]]])
            ->push(['data' => ['linkVehicleToCart' => ['cart' => ['id' => 'cart-new']]]]),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder), [
            'concern_id' => $concern->id,
        ])
        ->assertOk()
        ->assertJsonPath('prepared', true)
        ->assertJsonPath('catalog_url', fn (string $url): bool => str_contains($url, 'concern_id='.$concern->id));
});

test('repair order worksheet exposes per-scope partstech open for each concern', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.api_key', 'secret-key');
    config()->set('services.partstech.base_url', 'https://partstech.test');

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
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Multi-scope parts',
    ]);
    $repairOrder->refresh();

    $first = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => 'recommended',
        'position' => 1,
    ]);
    $second = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Oil service',
        'disposition' => 'recommended',
        'position' => 2,
    ]);

    $response = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk();

    $html = $response->getContent();

    expect($html)
        ->toContain('partstechPreferredConcernId')
        ->toContain('ops-scope-settings__partstech')
        ->toContain('openPartsTechCatalog(false, '.$first->id.')')
        ->toContain('openPartsTechCatalog(false, '.$second->id.')');
});

test('parts tech catalog can be enabled with password credential', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.api_key', null);
    config()->set('services.partstech.password', 'secret-password');

    expect(app(PartsTechCatalogLauncher::class)->configured())->toBeTrue()
        ->and(app(PartsTechCatalogLauncher::class)->usesRemoteCartPreparation())->toBeTrue();
});

test('parts tech catalog skips remote cart preparation without password', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.api_key', 'secret-key');
    config()->set('services.partstech.password', null);
    config()->set('services.partstech.base_url', 'https://partstech.test');

    Http::fake();

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
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.partstech', $repairOrder))
        ->assertRedirect();

    Http::assertNothingSent();
});

test('parts tech catalog opens with ymm when vin is missing', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.api_key', 'secret-key');
    config()->set('services.partstech.password', null);
    config()->set('services.partstech.base_url', 'https://partstech.test');
    config()->set('services.partstech.catalog_path', '');

    Http::fake();
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);
    $repairOrder->refresh();

    $partsTechReference = PartsTechShopReference::cartReference($repairOrder);

    $response = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.partstech', $repairOrder));

    $response->assertRedirect()
        ->assertRedirectContains('https://partstech.test?')
        ->assertRedirectContains('repairOrderNumber='.$partsTechReference)
        ->assertRedirectContains('poNumber='.$partsTechReference)
        ->assertRedirectContains('year=2018')
        ->assertRedirectContains('make=Honda')
        ->assertRedirectContains('model=Accord');

    expect($response->headers->get('Location'))
        ->not->toContain('vin=');

    expect(app(PartsTechCatalogLauncher::class)->catalogWarning($repairOrder))
        ->toBeNull();
});

test('parts tech catalog warns when vehicle has neither vin nor ymm', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.api_key', 'secret-key');
    config()->set('services.partstech.password', null);
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);

    expect(app(PartsTechCatalogLauncher::class)->catalogWarning($repairOrder))
        ->toContain('No VIN or year/make/model');
});

test('parts tech repair order number uses legacy repair order id when present', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 88042,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);

    $launcher = app(PartsTechCatalogLauncher::class);

    expect($repairOrder->repairOrderId())->toBe('88042')
        ->and($launcher->repairOrderNumber($repairOrder))->toBe('88042')
        ->and($launcher->partsTechCartReference($repairOrder))->toBe('R88042')
        ->and($launcher->poNumber($repairOrder))->toBe('R88042');
});

test('parts tech repair order number assigns next shop number for new repair orders', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);
    RepairOrder::query()->create([
        'repair_order_id' => 88041,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Prior repair order',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);

    expect($repairOrder->repair_order_id)->toBe(88042)
        ->and(app(PartsTechCatalogLauncher::class)->repairOrderNumber($repairOrder))->toBe('88042')
        ->and($repairOrder->repair_order_id)->not->toBe($repairOrder->id);
});

test('parts tech launcher resolves normalized vin first', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.api_key', 'secret-key');

    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'vin' => ' messy-vin ',
        'normalized_vin' => '1HGCM82633A004352',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);

    expect(app(PartsTechCatalogLauncher::class)->vinForRepairOrder($repairOrder))
        ->toBe('1HGCM82633A004352');
});

test('parts tech cart preparer reuses active cart when repair order number already matches', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

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
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);
    $repairOrder->refresh();

    $partsTechReference = PartsTechShopReference::cartReference($repairOrder);

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => Http::sequence()
            ->push(['data' => ['activeCart' => ['id' => 'cart-existing', 'repairOrderNumber' => $partsTechReference, 'orders' => []]]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['updateCart' => ['cart' => ['id' => 'cart-existing', 'repairOrderNumber' => $partsTechReference, 'notes' => 'ARK repair order '.$partsTechReference]]]])
            ->push(['data' => ['activateCart' => ['cart' => ['id' => 'cart-existing', 'active' => true, 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateActiveCartPurchaseOrderNumber' => ['__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload', 'updatedCartId' => 'cart-existing']]])
            ->push(['data' => ['activeCart' => ['orders' => []]]])
            ->push(['data' => ['vehicles' => [['id' => 'vehicle-1', 'vin' => '1HGCM82633A004352']]]])
            ->push(['data' => ['linkVehicleToCart' => ['cart' => ['id' => 'cart-existing']]]]),
    ]);

    expect(app(PartsTechCartPreparer::class)->prepareOrReport($repairOrder))->toBeTrue();

    Http::assertSentCount(10);
});

test('parts tech cart preparer does not archive an active cart that already has parts', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

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
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 88042,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => function ($request) {
            $query = (string) json_decode($request->body(), true)['query'];

            if (str_contains($query, 'activeCart')) {
                return Http::response([
                    'data' => [
                        'activeCart' => [
                            'id' => 'cart-busy',
                            'repairOrderNumber' => 'R12',
                            'orders' => [['id' => 'order-1', 'items' => [['id' => 'item-1']]]],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $preparer = app(PartsTechCartPreparer::class);

    expect(fn () => $preparer->prepareOrReport($repairOrder))
        ->toThrow(App\Ark\Operations\Parts\PartsTechShopSessionLockedException::class);
});

test('parts tech cart preparer can archive a foreign active cart when force cart switch is requested', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

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
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 88042,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => Http::sequence()
            ->push(['data' => ['activeCart' => [
                'id' => 'cart-busy',
                'repairOrderNumber' => 'R12',
                'orders' => [['id' => 'order-1', 'items' => [['id' => 'item-1']]]],
            ]]])
            ->push(['data' => ['archiveCart' => ['cart' => ['id' => 'cart-busy']]]])
            ->push(['data' => ['activeCart' => null]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['createCart' => ['cart' => ['id' => 'cart-88042', 'repairOrderNumber' => 'R88042']]]])
            ->push(['data' => ['updateCart' => ['cart' => ['id' => 'cart-88042', 'repairOrderNumber' => 'R88042', 'notes' => 'ARK repair order R88042']]]])
            ->push(['data' => ['activateCart' => ['cart' => ['id' => 'cart-88042', 'active' => true, 'repairOrderNumber' => 'R88042']]]])
            ->push(['data' => ['updateActiveCartPurchaseOrderNumber' => ['__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload', 'updatedCartId' => 'cart-88042']]])
            ->push(['data' => ['activeCart' => ['orders' => [['id' => 'order-88042']]]]])
            ->push(['data' => ['updateActiveCartOrderPurchaseOrderNumber' => ['__typename' => 'UpdateOrderPurchaseOrderNumberSuccessPayload', 'updatedOrderId' => 'order-88042']]])
            ->push(['data' => ['vehicles' => [['id' => 'vehicle-1', 'vin' => '1HGCM82633A004352']]]])
            ->push(['data' => ['linkVehicleToCart' => ['cart' => ['id' => 'cart-88042']]]]),
    ]);

    expect(app(PartsTechCartPreparer::class)->prepareOrReport($repairOrder, forceCartSwitch: true))->toBeTrue();
});

test('parts tech cart preparer still sets purchase order when vehicle lookup fails', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'vin' => 'ARK00000002810001',
        'normalized_vin' => 'ARK00000002810001',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);
    $repairOrder->refresh();

    $partsTechReference = PartsTechShopReference::cartReference($repairOrder);

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => Http::sequence()
            ->push(['data' => ['activeCart' => null]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['createCart' => ['cart' => ['id' => 'cart-ark', 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateCart' => ['cart' => ['id' => 'cart-ark', 'repairOrderNumber' => $partsTechReference, 'notes' => 'ARK repair order '.$partsTechReference]]]])
            ->push(['data' => ['activateCart' => ['cart' => ['id' => 'cart-ark', 'active' => true, 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateActiveCartPurchaseOrderNumber' => ['__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload', 'updatedCartId' => 'cart-ark']]])
            ->push(['data' => ['activeCart' => ['orders' => []]]])
            ->push(['data' => ['vehicles' => []]]),
    ]);

    expect(app(PartsTechCartPreparer::class)->prepareOrReport($repairOrder))->toBeTrue();
});

test('parts tech cart preparer archives stale active cart when repair order number differs', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

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
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 88042,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Parts order test',
    ]);

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => Http::sequence()
            ->push(['data' => ['activeCart' => ['id' => 'cart-stale', 'repairOrderNumber' => '12', 'orders' => []]]])
            ->push(['data' => ['archiveCart' => ['cart' => ['id' => 'cart-stale']]]])
            ->push(['data' => ['activeCart' => null]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['createCart' => ['cart' => ['id' => 'cart-88042', 'repairOrderNumber' => 'R88042']]]])
            ->push(['data' => ['updateCart' => ['cart' => ['id' => 'cart-88042', 'repairOrderNumber' => 'R88042', 'notes' => 'ARK repair order R88042']]]])
            ->push(['data' => ['activateCart' => ['cart' => ['id' => 'cart-88042', 'active' => true, 'repairOrderNumber' => 'R88042']]]])
            ->push(['data' => ['updateActiveCartPurchaseOrderNumber' => ['__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload', 'updatedCartId' => 'cart-88042']]])
            ->push(['data' => ['activeCart' => ['orders' => [['id' => 'order-88042']]]]])
            ->push(['data' => ['updateActiveCartOrderPurchaseOrderNumber' => ['__typename' => 'UpdateOrderPurchaseOrderNumberSuccessPayload', 'updatedOrderId' => 'order-88042']]])
            ->push(['data' => ['vehicles' => [['id' => 'vehicle-1', 'vin' => '1HGCM82633A004352']]]])
            ->push(['data' => ['linkVehicleToCart' => ['cart' => ['id' => 'cart-88042']]]]),
    ]);

    expect(app(PartsTechCartPreparer::class)->prepareOrReport($repairOrder))->toBeTrue();
});

test('parts tech prepare uses advisor personal login when configured on user profile', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $customer = Customer::query()->create([
        'first_name' => 'Ben',
        'last_name' => 'Advisor',
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
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Personal login test',
    ]);
    $repairOrder->refresh();

    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill([
        'partstech_username' => 'ben-advisor',
        'partstech_password' => 'ben-password',
    ])->save();

    $partsTechReference = PartsTechShopReference::cartReference($repairOrder);
    $loginUsername = null;

    Http::fake([
        'partstech.test/api/login' => function ($request) use (&$loginUsername) {
            $loginUsername = $request->data()['username'] ?? null;

            return Http::response([], 200);
        },
        'partstech.test/graphql' => Http::sequence()
            ->push(['data' => ['activeCart' => null]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['carts' => ['edges' => []]]])
            ->push(['data' => ['createCart' => ['cart' => ['id' => 'cart-new', 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateCart' => ['cart' => ['id' => 'cart-new', 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['activateCart' => ['cart' => ['id' => 'cart-new', 'active' => true, 'repairOrderNumber' => $partsTechReference]]]])
            ->push(['data' => ['updateActiveCartPurchaseOrderNumber' => ['__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload', 'updatedCartId' => 'cart-new']]])
            ->push(['data' => ['activeCart' => ['orders' => []]]])
            ->push(['data' => ['vehicles' => [['id' => 'vehicle-1', 'vin' => '1HGCM82633A004352']]]])
            ->push(['data' => ['linkVehicleToCart' => ['cart' => ['id' => 'cart-new']]]]),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder))
        ->assertOk()
        ->assertJsonPath('prepared', true)
        ->assertJsonPath('partstech_login', 'ben-advisor')
        ->assertJsonPath('partstech_login_source', 'user');

    expect($loginUsername)->toBe('ben-advisor');
});

test('parts tech cart preparer links vehicle by ymm when vin is missing', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
        'trim' => 'EX',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'YMM parts test',
    ]);
    $repairOrder->refresh();

    $partsTechReference = PartsTechShopReference::cartReference($repairOrder);
    $suggestSearches = [];

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => function ($request) use ($partsTechReference, &$suggestSearches) {
            $payload = json_decode($request->body(), true);
            $query = (string) ($payload['query'] ?? '');

            if (str_contains($query, 'activeCart') && ! str_contains($query, 'mutation')) {
                return Http::response(['data' => ['activeCart' => null]], 200);
            }

            if (str_contains($query, 'carts(search')) {
                return Http::response(['data' => ['carts' => ['edges' => []]]], 200);
            }

            if (str_contains($query, 'createCart')) {
                return Http::response([
                    'data' => ['createCart' => ['cart' => ['id' => 'cart-ymm', 'repairOrderNumber' => $partsTechReference]]],
                ], 200);
            }

            if (str_contains($query, 'updateCart')) {
                return Http::response([
                    'data' => ['updateCart' => ['cart' => ['id' => 'cart-ymm', 'repairOrderNumber' => $partsTechReference]]],
                ], 200);
            }

            if (str_contains($query, 'activateCart')) {
                return Http::response([
                    'data' => ['activateCart' => ['cart' => ['id' => 'cart-ymm', 'active' => true]]],
                ], 200);
            }

            if (str_contains($query, 'updateActiveCartPurchaseOrderNumber')) {
                return Http::response([
                    'data' => [
                        'updateActiveCartPurchaseOrderNumber' => [
                            '__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload',
                            'updatedCartId' => 'cart-ymm',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($query, 'vehicleSuggest')) {
                $suggestSearches[] = data_get($payload, 'variables.search');

                return Http::response([
                    'data' => [
                        'vehicleSuggest' => [
                            [
                                'id' => 'pt-vehicle-ex',
                                'year' => 2018,
                                'make' => ['name' => 'Honda'],
                                'model' => ['name' => 'Accord'],
                                'subModel' => ['name' => 'EX'],
                                'engine' => ['name' => '1.5L L4'],
                            ],
                            [
                                'id' => 'pt-vehicle-lx',
                                'year' => 2018,
                                'make' => ['name' => 'Honda'],
                                'model' => ['name' => 'Accord'],
                                'subModel' => ['name' => 'LX'],
                                'engine' => ['name' => '1.5L L4'],
                            ],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($query, 'linkVehicleToCart')) {
                expect(data_get($payload, 'variables.input.vehicleId'))->toBe('pt-vehicle-ex');
                expect(data_get($payload, 'variables.input.vin'))->toBeNull();

                return Http::response([
                    'data' => ['linkVehicleToCart' => ['cart' => ['id' => 'cart-ymm']]],
                ], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $preparer = app(PartsTechCartPreparer::class);

    expect($preparer->prepareOrReport($repairOrder))->toBeTrue();
    expect($suggestSearches)->toContain('2018 Honda Accord EX');
    expect($preparer->lastWarnings())->toBe([]);
});
