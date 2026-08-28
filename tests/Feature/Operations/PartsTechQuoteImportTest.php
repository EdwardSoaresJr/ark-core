<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function partsTechQuoteCartByIdResponse(string $repairOrderNumber, array $items): array
{
    return [
        'data' => [
            'cart' => [
                'id' => 'cart-1',
                'repairOrderNumber' => $repairOrderNumber,
                'orders' => [
                    [
                        'id' => 'order-1',
                        'supplier' => ['name' => 'AutoZone'],
                        'items' => $items,
                    ],
                ],
            ],
        ],
    ];
}

function partsTechQuoteImportGraphqlPreparerStub(string $query)
{
    if (str_contains($query, 'updateCart')) {
        return Http::response(['data' => ['updateCart' => ['cart' => ['id' => 'cart-1']]]], 200);
    }

    if (str_contains($query, 'activateCart')) {
        return Http::response(['data' => ['activateCart' => ['cart' => ['id' => 'cart-1', 'active' => true]]]], 200);
    }

    if (str_contains($query, 'updateActiveCartPurchaseOrderNumber')) {
        return Http::response([
            'data' => [
                'updateActiveCartPurchaseOrderNumber' => [
                    '__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload',
                    'updatedCartId' => 'cart-1',
                ],
            ],
        ], 200);
    }

    if (str_contains($query, 'updateActiveCartOrderPurchaseOrderNumber')) {
        return Http::response([
            'data' => [
                'updateActiveCartOrderPurchaseOrderNumber' => [
                    '__typename' => 'UpdateOrderPurchaseOrderNumberSuccessPayload',
                    'updatedOrderId' => 'order-1',
                ],
            ],
        ], 200);
    }

    if (str_contains($query, 'vehicles')) {
        return Http::response([
            'data' => [
                'vehicles' => [
                    ['id' => 'vehicle-1', 'vin' => '1HGCM82633A004352'],
                ],
            ],
        ], 200);
    }

    if (str_contains($query, 'linkVehicleToCart')) {
        return Http::response(['data' => ['linkVehicleToCart' => ['cart' => ['id' => 'cart-1']]]], 200);
    }

    if (str_contains($query, 'createCart')) {
        return Http::response(['data' => ['createCart' => ['cart' => ['id' => 'cart-1']]]], 200);
    }

    return null;
}

/**
 * @param  list<array<string, mixed>>  $items
 */
function partsTechQuoteImportGraphqlFake(string $activeCartReference, array $items, string $quoteCartId = 'cart-1'): void
{
    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => function ($request) use ($activeCartReference, $items, $quoteCartId) {
            $query = (string) json_decode($request->body(), true)['query'];

            $preparerStub = partsTechQuoteImportGraphqlPreparerStub($query);

            if ($preparerStub !== null) {
                return $preparerStub;
            }

            if (str_contains($query, 'activeCart')) {
                $itemStubs = array_map(
                    fn (array $item): array => ['id' => (string) $item['id']],
                    $items,
                );

                return Http::response([
                    'data' => [
                        'activeCart' => [
                            'id' => $quoteCartId,
                            'repairOrderNumber' => $activeCartReference,
                            'orders' => $itemStubs === []
                                ? []
                                : [['id' => 'order-1', 'items' => $itemStubs]],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($query, 'carts(search')) {
                return Http::response(['data' => ['carts' => ['edges' => []]]], 200);
            }

            if (str_contains($query, 'cart(id')) {
                return Http::response(partsTechQuoteCartByIdResponse($activeCartReference, $items), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);
}

/**
 * @param  list<array<string, mixed>>  $quoteItems
 */
function partsTechQuoteImportGraphqlFakeWithInactiveCart(string $activeCartReference, array $quoteItems): void
{
    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => function ($request) use ($activeCartReference, $quoteItems) {
            $query = (string) json_decode($request->body(), true)['query'];

            $preparerStub = partsTechQuoteImportGraphqlPreparerStub($query);

            if ($preparerStub !== null) {
                return $preparerStub;
            }

            if (str_contains($query, 'activeCart')) {
                return Http::response([
                    'data' => [
                        'activeCart' => [
                            'id' => 'cart-empty',
                            'repairOrderNumber' => $activeCartReference,
                            'orders' => [],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($query, 'carts(search')) {
                return Http::response([
                    'data' => [
                        'carts' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => 'cart-1',
                                        'repairOrderNumber' => $activeCartReference,
                                        'orders' => [
                                            [
                                                'items' => [
                                                    ['id' => 'item-1'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($query, 'cart(id')) {
                return Http::response(partsTechQuoteCartByIdResponse($activeCartReference, $quoteItems), 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);
}

test('parts tech quote preview returns cart lines and concerns', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    $items = [
        [
            'id' => 'item-1',
            'quantity' => 2,
            'partNumber' => 'HP-1004',
            'partName' => 'K&N Engine Oil Filter',
            'brand' => ['name' => 'K&N'],
            'builtItem' => [
                'product' => [
                    'partNumberDisplay' => 'HP-1004',
                    'title' => 'K&N Engine Oil Filter',
                    'price' => 16.99,
                ],
            ],
        ],
    ];

    partsTechQuoteImportGraphqlFake('R88042', $items);

    ShopSettings::current()->update([
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('po_synced', true)
        ->assertJsonPath('partstech_login', 'ark-shop')
        ->assertJsonPath('cart_reference', 'R88042')
        ->assertJsonPath('default_parts_matrix_key', 'aft-parts')
        ->assertJsonPath('repair_order_number', '88042')
        ->assertJsonPath('lines.0.source_key', 'pt:item-1')
        ->assertJsonPath('lines.0.part_number', 'HP-1004')
        ->assertJsonPath('concerns.0.id', $concern->id)
        ->assertJsonPath('concerns.0.summary', 'Maintenance')
        ->assertJsonPath('concerns.0.default_parts_matrix_key', 'aft-parts');
});

test('parts tech quote preview surfaces front and rear position for duplicate brake lines', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    partsTechQuoteImportGraphqlFake('R88042', [
        partsTechQuoteImportBrakePadItem('item-front', 'SC2094', 'Front'),
        partsTechQuoteImportBrakePadItem('item-rear', 'SC1813', 'Rear'),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('lines.0.position_label', 'Front')
        ->assertJsonPath('lines.0.description', 'Front — Wagner BrakeBest Select Ceramic Disc Brake Pad Set')
        ->assertJsonPath('lines.1.position_label', 'Rear')
        ->assertJsonPath('lines.1.description', 'Rear — Wagner BrakeBest Select Ceramic Disc Brake Pad Set');
});

test('parts tech quote import assigns selected parts to chosen concerns', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
    ]);

    [$repairOrder, $maintenanceConcern] = partsTechQuoteImportFixture();
    $brakesConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'disposition' => 'recommended',
        'position' => 1,
    ]);

    partsTechQuoteImportGraphqlFake('R88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'HP-1004',
            'partName' => 'K&N Engine Oil Filter',
            'brand' => ['name' => 'K&N'],
            'builtItem' => ['product' => ['price' => 16.99]],
        ],
        [
            'id' => 'item-2',
            'quantity' => 1,
            'partNumber' => 'BR-55',
            'partName' => 'Front brake pads',
            'brand' => ['name' => 'Wagner'],
            'builtItem' => ['product' => ['price' => 89.50]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.partstech.import', $repairOrder), [
            'assignments' => [
                ['source_key' => 'pt:item-1', 'repair_order_concern_id' => $maintenanceConcern->id],
                ['source_key' => 'pt:item-2', 'repair_order_concern_id' => $brakesConcern->id],
                ['source_key' => 'pt:item-ignored', 'repair_order_concern_id' => ''],
            ],
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('status');

    $lines = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->orderBy('id')->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->repair_order_concern_id)->toBe($maintenanceConcern->id)
        ->and($lines[0]->part_number)->toBe('HP-1004')
        ->and($lines[1]->repair_order_concern_id)->toBe($brakesConcern->id)
        ->and($lines[1]->part_number)->toBe('BR-55')
        ->and($lines->every(fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Part))->toBeTrue();
});

test('parts tech quote import accepts legacy numeric cart references', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    partsTechQuoteImportGraphqlFake('88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'HP-1004',
            'partName' => 'K&N Engine Oil Filter',
            'brand' => ['name' => 'K&N'],
            'builtItem' => ['product' => ['price' => 16.99]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('lines.0.source_key', 'pt:item-1');
});

test('parts tech quote preview returns single concern for scope prefill', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    partsTechQuoteImportGraphqlFake('R88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'DG1092',
            'partName' => 'Brake pads',
            'brand' => ['name' => 'Wagner'],
            'builtItem' => ['product' => ['price' => 42.00]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonCount(1, 'concerns')
        ->assertJsonPath('concerns.0.id', $concern->id)
        ->assertJsonPath('default_repair_order_concern_id', $concern->id)
        ->assertJsonPath('default_parts_matrix_key', 'aft-parts');
});

test('parts tech quote import loads parts from inactive cart when active cart is empty', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    partsTechQuoteImportGraphqlFakeWithInactiveCart('R88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'DG1092',
            'partName' => 'Brake pads',
            'brand' => ['name' => 'Wagner'],
            'builtItem' => ['product' => ['price' => 42.00]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('lines.0.part_number', 'DG1092');
});

test('parts tech quote import applies scope billing matrix like manual part lines', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    [$repairOrder, $concern] = partsTechQuoteImportFixture();
    $concern->update(['billing_posture' => ConcernBillingPosture::Warranty]);

    partsTechQuoteImportGraphqlFake('R88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'HP-1004',
            'partName' => 'K&N Engine Oil Filter',
            'brand' => ['name' => 'K&N'],
            'builtItem' => ['product' => ['price' => 10.00]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.partstech.import', $repairOrder), [
            'assignments' => [
                [
                    'source_key' => 'pt:item-1',
                    'repair_order_concern_id' => $concern->id,
                    'part_cost' => '10.00',
                    'pricing_mode' => 'matrix',
                ],
            ],
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('status');

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($line->pricing_mode)->toBe('matrix')
        ->and($line->pricing_matrix_key)->toBe('warranty-no-markup')
        ->and($line->matrix_applied)->toBeTrue()
        ->and($line->is_overridden)->toBeFalse()
        ->and($line->part_cost_cents)->toBe(1000)
        ->and($line->matrix_suggested_price_cents)->not->toBeNull();
});

test('parts tech quote import uses each scope default parts matrix', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    [$repairOrder, $maintenanceConcern] = partsTechQuoteImportFixture();
    $warrantyConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Warranty repair',
        'disposition' => 'recommended',
        'billing_posture' => ConcernBillingPosture::Warranty,
        'position' => 1,
    ]);

    partsTechQuoteImportGraphqlFake('R88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'HP-1004',
            'partName' => 'K&N Engine Oil Filter',
            'brand' => ['name' => 'K&N'],
            'builtItem' => ['product' => ['price' => 10.00]],
        ],
        [
            'id' => 'item-2',
            'quantity' => 1,
            'partNumber' => 'WP-1',
            'partName' => 'Water pump',
            'brand' => ['name' => 'ACDelco'],
            'builtItem' => ['product' => ['price' => 10.00]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.partstech.import', $repairOrder), [
            'assignments' => [
                [
                    'source_key' => 'pt:item-1',
                    'repair_order_concern_id' => $maintenanceConcern->id,
                    'part_cost' => '10.00',
                ],
                [
                    'source_key' => 'pt:item-2',
                    'repair_order_concern_id' => $warrantyConcern->id,
                    'part_cost' => '10.00',
                ],
            ],
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('status');

    $lines = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->pricing_matrix_key)->toBe('aft-parts')
        ->and($lines[1]->pricing_matrix_key)->toBe('warranty-no-markup')
        ->and($lines->every(fn (RepairOrderLine $line): bool => $line->matrix_applied))->toBeTrue();
});

test('parts tech quote import rejects carts tied to a different repair order', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    partsTechQuoteImportGraphqlFake('12', []);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.partstech.import', $repairOrder), [
            'assignments' => [
                ['source_key' => 'pt:item-1', 'repair_order_concern_id' => $concern->id],
            ],
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('error');

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(0);
});

test('parts tech quote preview includes labor-anchored repair actions per scope', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    $workGroup = $concern->workGroups()->create([
        'title' => 'Replace Water Pump',
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace water pump',
        'quantity' => '2.00',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 33000,
        'total_cents' => 33000,
    ]);

    partsTechQuoteImportGraphqlFake('R88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'WP-1',
            'partName' => 'Water pump',
            'brand' => ['name' => 'ACDelco'],
            'builtItem' => ['product' => ['price' => 120.00]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('concerns.0.work_groups.0.id', $workGroup->id)
        ->assertJsonPath('concerns.0.work_groups.0.title', 'Replace Water Pump')
        ->assertJsonPath('concerns.0.work_groups.0.has_labor_anchor', true);
});

test('parts tech quote import honors explicit matrix override per part', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    partsTechQuoteImportGraphqlFake('R88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'OEM-100',
            'partName' => 'OEM pad set',
            'brand' => ['name' => 'Honda'],
            'builtItem' => ['product' => ['price' => 40.00]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.partstech.import', $repairOrder), [
            'assignments' => [
                [
                    'source_key' => 'pt:item-1',
                    'repair_order_concern_id' => $concern->id,
                    'part_cost' => '40.00',
                    'pricing_matrix_key' => 'oem-parts',
                ],
            ],
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('status');

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($line->pricing_mode)->toBe('matrix')
        ->and($line->pricing_matrix_key)->toBe('oem-parts')
        ->and($line->matrix_applied)->toBeTrue()
        ->and($line->part_cost_cents)->toBe(4000);
});

test('parts tech quote import attaches parts to a repair action with labor', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
    ]);

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    $workGroup = $concern->workGroups()->create([
        'title' => 'Replace Water Pump',
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace water pump',
        'quantity' => '2.00',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 33000,
        'total_cents' => 33000,
    ]);

    partsTechQuoteImportGraphqlFake('R88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'WP-1',
            'partName' => 'Water pump',
            'brand' => ['name' => 'ACDelco'],
            'builtItem' => ['product' => ['price' => 120.00]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.partstech.import', $repairOrder), [
            'assignments' => [
                [
                    'source_key' => 'pt:item-1',
                    'repair_order_concern_id' => $concern->id,
                    'repair_order_work_group_id' => $workGroup->id,
                ],
            ],
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#repair-action-'.$workGroup->id)
        ->assertSessionHas('status');

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->where('type', RepairOrderLineType::Part)->sole();

    expect($line->repair_order_work_group_id)->toBe($workGroup->id)
        ->and($line->part_number)->toBe('WP-1');
});

test('parts tech quote import rejects repair actions without labor', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder, $concern] = partsTechQuoteImportFixture();

    $workGroup = $concern->workGroups()->create([
        'title' => 'Replace Water Pump',
        'position' => 1,
    ]);

    partsTechQuoteImportGraphqlFake('R88042', [
        [
            'id' => 'item-1',
            'quantity' => 1,
            'partNumber' => 'WP-1',
            'partName' => 'Water pump',
            'brand' => ['name' => 'ACDelco'],
            'builtItem' => ['product' => ['price' => 120.00]],
        ],
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.partstech.import', $repairOrder), [
            'assignments' => [
                [
                    'source_key' => 'pt:item-1',
                    'repair_order_concern_id' => $concern->id,
                    'repair_order_work_group_id' => $workGroup->id,
                ],
            ],
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('error');
});

function partsTechQuoteImportBrakePadItem(string $id, string $partNumber, string $position): array
{
    return [
        'id' => $id,
        'quantity' => 1,
        'partNumber' => $partNumber,
        'partName' => 'BrakeBest Select Ceramic Disc Brake Pad Set',
        'brand' => ['name' => 'Wagner'],
        'builtItem' => [
            'product' => [
                'price' => 42.00,
                'attributes' => [
                    ['name' => 'Position', 'value' => [$position]],
                ],
            ],
        ],
    ];
}

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern}
 */
function partsTechQuoteImportFixture(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
        'customer_type' => 'Retail',
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
        'concern_summary' => 'Oil service',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Maintenance',
        'disposition' => 'recommended',
        'position' => 0,
    ]);

    return [$repairOrder, $concern];
}

test('parts tech quote preview refuses when another repair order owns the active cart', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder] = partsTechQuoteImportFixture();

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => function ($request) {
            $query = (string) json_decode($request->body(), true)['query'];

            $preparerStub = partsTechQuoteImportGraphqlPreparerStub($query);

            if ($preparerStub !== null) {
                return $preparerStub;
            }

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

            if (str_contains($query, 'carts(search')) {
                return Http::response(['data' => ['carts' => ['edges' => []]]], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertStatus(423)
        ->assertJsonPath('cart_locked', true)
        ->assertJsonPath('blocking_cart_reference', 'R12')
        ->assertJsonPath('expected_cart_reference', 'R88042');
});

test('parts tech catalog prepare refuses when another repair order owns the active cart', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder] = partsTechQuoteImportFixture();

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => Http::response([
            'data' => [
                'activeCart' => [
                    'id' => 'cart-busy',
                    'repairOrderNumber' => 'R12',
                    'orders' => [['id' => 'order-1', 'items' => [['id' => 'item-1']]]],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder))
        ->assertStatus(423)
        ->assertJsonPath('cart_locked', true)
        ->assertJsonPath('blocking_cart_reference', 'R12');
});

test('parts tech quote preview refuses sync when another repair order owns the active cart with parts', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    [$repairOrder] = partsTechQuoteImportFixture();

    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => function ($request) {
            $query = (string) data_get(json_decode($request->body(), true), 'query', '');

            if (str_contains($query, 'activeCart')) {
                return Http::response([
                    'data' => [
                        'activeCart' => [
                            'id' => 'cart-busy',
                            'repairOrderNumber' => 'RTEST',
                            'orders' => [['id' => 'order-1', 'items' => [['id' => 'item-1']]]],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);

    $preparer = app(App\Ark\Operations\Parts\PartsTechCartPreparer::class);

    expect(fn () => $preparer->prepareOrReport($repairOrder, syncOnly: true))
        ->toThrow(App\Ark\Operations\Parts\PartsTechShopSessionLockedException::class);
});
