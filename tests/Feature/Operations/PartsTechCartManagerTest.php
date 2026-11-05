<?php

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Parts\PartsCartImportLedger;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderAuthorizationStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/../../Support/HostedPartsTechHttp.php';

beforeEach(function (): void {
    bindHostedPartsTechHttpClient();
    $this->seed(RepairOrderStatusCatalogSeeder::class);

    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'portal_signature_required' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    $this->cart = new ArrayObject([
        cartManagerItem('item-pads', 'BR-55', 'Front Brake Pad Set', 1, 82.50),
        cartManagerItem('item-filter', 'HP-1004', 'Engine Oil Filter', 2, 10.00),
    ]);
    cartManagerFakePartsTech('R77031', $this->cart);

    $this->advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($this->advisor);
});

/**
 * @return array<string, mixed>
 */
function cartManagerItem(string $id, string $partNumber, string $name, int $quantity, float $price): array
{
    return [
        'id' => $id,
        'quantity' => $quantity,
        'partNumber' => $partNumber,
        'partName' => $name,
        'builtItem' => ['product' => ['price' => $price]],
    ];
}

/**
 * One PartsTech cart for the repair order; the test changes its items between pulls.
 */
function cartManagerFakePartsTech(string $reference, ArrayObject $cart): void
{
    Http::fake([
        'partstech.test/api/login' => Http::response([], 200),
        'partstech.test/graphql' => function ($request) use ($reference, $cart) {
            $query = (string) json_decode($request->body(), true)['query'];
            $items = $cart->getArrayCopy();

            foreach ([
                'updateActiveCartOrderPurchaseOrderNumber' => ['__typename' => 'UpdateOrderPurchaseOrderNumberSuccessPayload', 'updatedOrderId' => 'order-1'],
                'updateActiveCartPurchaseOrderNumber' => ['__typename' => 'UpdateCartPurchaseOrderNumberSuccessPayload', 'updatedCartId' => 'cart-1'],
                'updateCart' => ['cart' => ['id' => 'cart-1']],
                'activateCart' => ['cart' => ['id' => 'cart-1', 'active' => true]],
                'linkVehicleToCart' => ['cart' => ['id' => 'cart-1']],
                'createCart' => ['cart' => ['id' => 'cart-1']],
            ] as $mutation => $payload) {
                if (str_contains($query, $mutation)) {
                    return Http::response(['data' => [$mutation => $payload]], 200);
                }
            }

            if (str_contains($query, 'vehicles')) {
                return Http::response(['data' => ['vehicles' => []]], 200);
            }

            if (str_contains($query, 'activeCart')) {
                return Http::response(['data' => ['activeCart' => [
                    'id' => 'cart-1',
                    'repairOrderNumber' => $reference,
                    'orders' => [['id' => 'order-1', 'items' => array_map(fn (array $item): array => ['id' => $item['id']], $items)]],
                ]]], 200);
            }

            if (str_contains($query, 'carts(search')) {
                return Http::response(['data' => ['carts' => ['edges' => []]]], 200);
            }

            if (str_contains($query, 'cart(id')) {
                return Http::response(['data' => ['cart' => [
                    'id' => 'cart-1',
                    'repairOrderNumber' => $reference,
                    'orders' => [['id' => 'order-1', 'supplier' => ['name' => 'AutoZone'], 'items' => $items]],
                ]]], 200);
            }

            return Http::response(['data' => []], 200);
        },
    ]);
}

/**
 * A repair order with two concerns on different parts matrices: a retail concern on the shop
 * default matrix and a warranty concern on the warranty matrix.
 *
 * @return array{0: RepairOrder, 1: RepairOrderConcern, 2: RepairOrderConcern}
 */
function cartManagerRepairOrder(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'Cart',
        'phone' => '555-0142',
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
        'repair_order_id' => 77031,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brakes and maintenance',
    ]);
    $brakes = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front Brake Noise',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 0,
    ]);
    $warranty = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Warranty oil leak',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::Warranty,
        'position' => 1,
    ]);

    return [$repairOrder, $brakes, $warranty];
}

/**
 * @param  list<array<string, mixed>>  $assignments
 */
function cartManagerImport(RepairOrder $repairOrder, array $assignments)
{
    return test()->postJson(route('operations.repair-orders.partstech.import', $repairOrder), [
        RepairOrderConcurrency::FIELD => (int) $repairOrder->fresh()->estimate_version,
        'assignments' => $assignments,
    ]);
}

test('the cart preview defaults every part to the concern PartsTech was opened from', function () {
    [$repairOrder, $brakes, $warranty] = cartManagerRepairOrder();

    $this->getJson(route('operations.repair-orders.partstech.import.preview', [$repairOrder, 'concern_id' => $warranty->id]))
        ->assertOk()
        ->assertJsonPath('default_repair_order_concern_id', $warranty->id)
        ->assertJsonPath('concerns.0.default_parts_matrix_key', 'aft-parts')
        ->assertJsonPath('concerns.1.default_parts_matrix_key', 'warranty-no-markup')
        ->assertJsonPath('lines.0.import_status', PartsCartImportLedger::STATUS_NEW)
        ->assertJsonPath('lines.1.import_status', PartsCartImportLedger::STATUS_NEW);

    $this->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('default_repair_order_concern_id', null);

    [$otherRepairOrder] = cartManagerRepairOrderOther();
    $foreignConcern = $otherRepairOrder->concerns()->first();

    $this->getJson(route('operations.repair-orders.partstech.import.preview', [$repairOrder, 'concern_id' => $foreignConcern->id]))
        ->assertOk()
        ->assertJsonPath('default_repair_order_concern_id', null);
});

test('cart pricing shows the sell price and gross margin each concern matrix gives', function () {
    [$repairOrder, $brakes, $warranty] = cartManagerRepairOrder();
    $calculator = app(EstimateTotalsCalculator::class);
    $standardSell = $calculator->matrixSuggestedPriceCents(8250, ShopSettings::current(), 'aft-parts');

    $rows = $this->postJson(route('operations.repair-orders.partstech.import.pricing', $repairOrder), [
        'rows' => [
            ['key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'quantity' => '1.00', 'pricing_matrix_key' => 'aft-parts'],
            ['key' => 'pt:warranty', 'repair_order_concern_id' => $warranty->id, 'part_cost' => '82.50', 'quantity' => '1.00', 'pricing_matrix_key' => 'warranty-no-markup'],
            ['key' => 'pt:override', 'repair_order_concern_id' => $warranty->id, 'part_cost' => '82.50', 'quantity' => '2.00', 'pricing_matrix_key' => 'aft-parts'],
            ['key' => 'pt:gone', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'pricing_matrix_key' => 'retired-matrix'],
            ['key' => 'pt:unassigned', 'repair_order_concern_id' => null, 'part_cost' => '82.50', 'pricing_matrix_key' => 'aft-parts'],
        ],
    ])->assertOk()->json('rows');

    $expectedMargin = $calculator->grossMarginPercentage(8250, $standardSell, 1);

    expect($standardSell)->toBeGreaterThan(8250)
        ->and($rows[0])->toMatchArray([
            'key' => 'pt:item-pads',
            'pricing_matrix_key' => 'aft-parts',
            'unit_cost_cents' => 8250,
            'unit_sell_cents' => $standardSell,
            'margin_percentage' => $expectedMargin,
            'below_cost' => false,
        ])
        ->and($expectedMargin)->toBe(number_format(round(($standardSell - 8250) / $standardSell * 100, 1), 1, '.', ''))
        ->and($rows[1]['pricing_matrix_key'])->toBe('warranty-no-markup')
        ->and($rows[1]['unit_sell_cents'])->toBe(8250)
        ->and($rows[1]['margin_percentage'])->toBe('0.0')
        ->and($rows[2]['pricing_matrix_key'])->toBe('aft-parts')
        ->and($rows[2]['unit_sell_cents'])->toBe($standardSell)
        ->and($rows[2]['line_sell'])->toBe('$'.number_format($standardSell * 2 / 100, 2))
        ->and($rows[3])->toBe(['key' => 'pt:gone', 'error' => 'This parts matrix is no longer available. Choose another.'])
        ->and($rows[4]['unit_sell_cents'])->toBe($standardSell);
});

test('gross margin is sell minus cost over sell, never markup, and is empty without a sell price', function () {
    $calculator = app(EstimateTotalsCalculator::class);

    expect($calculator->grossMarginPercentage(8250, 14995, 1))->toBe('45.0')
        ->and($calculator->grossMarginPercentage(8250, 14995))->toBe('45')
        ->and($calculator->grossMarginPercentage(1000, 800, 1))->toBe('-25.0')
        ->and($calculator->grossMarginPercentage(1000, 0, 1))->toBeNull();
});

test('cart pricing refuses a concern from another repair order', function () {
    [$repairOrder] = cartManagerRepairOrder();
    [$otherRepairOrder] = cartManagerRepairOrderOther();

    $this->postJson(route('operations.repair-orders.partstech.import.pricing', $repairOrder), [
        'rows' => [['key' => 'pt:x', 'repair_order_concern_id' => $otherRepairOrder->concerns()->first()->id, 'part_cost' => '10.00']],
    ])->assertUnprocessable()->assertJsonValidationErrors('rows.0.repair_order_concern_id');
});

test('one cart imports into two concerns at the price shown before import', function () {
    [$repairOrder, $brakes, $warranty] = cartManagerRepairOrder();

    $shown = collect($this->postJson(route('operations.repair-orders.partstech.import.pricing', $repairOrder), [
        'rows' => [
            ['key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'quantity' => '1.00', 'pricing_matrix_key' => 'aft-parts'],
            ['key' => 'pt:item-filter', 'repair_order_concern_id' => $warranty->id, 'part_cost' => '10.00', 'quantity' => '2.00', 'pricing_matrix_key' => 'warranty-no-markup'],
        ],
    ])->json('rows'))->keyBy('key');

    cartManagerImport($repairOrder, [
        ['source_key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'pricing_matrix_key' => 'aft-parts'],
        ['source_key' => 'pt:item-filter', 'repair_order_concern_id' => $warranty->id, 'part_cost' => '10.00', 'pricing_matrix_key' => 'warranty-no-markup'],
    ])
        ->assertOk()
        ->assertJsonPath('imported', 2)
        ->assertJsonPath('redirect_url', route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('status', 'Imported 2 part lines from PartsTech into Front Brake Noise, Warranty oil leak.');

    $pads = RepairOrderLine::query()->where('parts_cart_source_key', 'pt:item-pads')->sole();
    $filter = RepairOrderLine::query()->where('parts_cart_source_key', 'pt:item-filter')->sole();

    expect($pads->repair_order_concern_id)->toBe($brakes->id)
        ->and($pads->type)->toBe(RepairOrderLineType::Part)
        ->and($pads->quantity)->toBe('1.00')
        ->and($pads->part_cost_cents)->toBe(8250)
        ->and($pads->pricing_matrix_key)->toBe('aft-parts')
        ->and($pads->unit_price_cents)->toBe($shown['pt:item-pads']['unit_sell_cents'])
        ->and($pads->parts_cart_snapshot)->toBe(['quantity' => '1.00', 'part_cost_cents' => 8250, 'part_number' => 'BR-55'])
        ->and($filter->repair_order_concern_id)->toBe($warranty->id)
        ->and($filter->quantity)->toBe('2.00')
        ->and($filter->pricing_matrix_key)->toBe('warranty-no-markup')
        ->and($filter->unit_price_cents)->toBe($shown['pt:item-filter']['unit_sell_cents'])
        ->and($filter->subtotal_cents)->toBe(2 * $shown['pt:item-filter']['unit_sell_cents']);
});

test('re-pulling the cart marks imported parts and refuses to import them again by accident', function () {
    [$repairOrder, $brakes] = cartManagerRepairOrder();

    cartManagerImport($repairOrder, [
        ['source_key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'pricing_matrix_key' => 'aft-parts'],
    ])->assertOk();

    $this->cart[] = cartManagerItem('item-rotor', 'RT-9', 'Front Rotor', 2, 61.00);

    $this->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('lines.0.source_key', 'pt:item-pads')
        ->assertJsonPath('lines.0.import_status', PartsCartImportLedger::STATUS_IMPORTED)
        ->assertJsonPath('lines.0.imported_lines.0.concern_summary', 'Front Brake Noise')
        ->assertJsonPath('lines.1.import_status', PartsCartImportLedger::STATUS_NEW)
        ->assertJsonPath('lines.2.import_status', PartsCartImportLedger::STATUS_NEW);

    cartManagerImport($repairOrder, [
        ['source_key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'pricing_matrix_key' => 'aft-parts'],
        ['source_key' => 'pt:item-rotor', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '61.00', 'pricing_matrix_key' => 'aft-parts'],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Front Brake Pad Set is already on this estimate. Pull the cart again to see what is new.');

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);

    cartManagerImport($repairOrder, [
        ['source_key' => 'pt:item-rotor', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '61.00', 'pricing_matrix_key' => 'aft-parts'],
        ['source_key' => 'pt:item-rotor', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '61.00', 'pricing_matrix_key' => 'aft-parts'],
    ])->assertUnprocessable();

    expect(RepairOrderLine::query()->where('parts_cart_source_key', 'pt:item-rotor')->count())->toBe(0);
});

test('a cart line changed after import is flagged and never rewrites the estimate line', function () {
    [$repairOrder, $brakes] = cartManagerRepairOrder();

    cartManagerImport($repairOrder, [
        ['source_key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'pricing_matrix_key' => 'aft-parts'],
    ])->assertOk();

    $line = RepairOrderLine::query()->where('parts_cart_source_key', 'pt:item-pads')->sole();
    $line->update(['unit_price_cents' => 15995, 'subtotal_cents' => 15995, 'is_overridden' => true]);
    $advisorOwned = ['repair_order_concern_id', 'description', 'quantity', 'part_cost_cents', 'unit_price_cents', 'subtotal_cents', 'pricing_matrix_key', 'is_overridden'];
    $edited = $line->fresh()->only($advisorOwned);

    $this->cart[0] = cartManagerItem('item-pads', 'BR-55', 'Front Brake Pad Set', 2, 79.00);

    $this->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('lines.0.import_status', PartsCartImportLedger::STATUS_CHANGED)
        ->assertJsonPath('lines.0.imported_lines.0.cart_quantity', '1.00')
        ->assertJsonPath('lines.0.imported_lines.0.cart_part_cost', '82.50');

    expect($line->fresh()->only($advisorOwned))->toBe($edited);

    cartManagerImport($repairOrder, [
        ['source_key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '79.00', 'pricing_matrix_key' => 'aft-parts', 'import_again' => true],
    ])->assertOk();

    expect($line->fresh()->only($advisorOwned))->toBe($edited)
        ->and(RepairOrderLine::query()->where('parts_cart_source_key', 'pt:item-pads')->count())->toBe(2)
        ->and(RepairOrderLine::query()->where('parts_cart_source_key', 'pt:item-pads')->latest('id')->first()->quantity)->toBe('2.00');
});

test('a deleted imported line makes its cart part new again', function () {
    [$repairOrder, $brakes] = cartManagerRepairOrder();

    cartManagerImport($repairOrder, [
        ['source_key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'pricing_matrix_key' => 'aft-parts'],
    ])->assertOk();

    RepairOrderLine::query()->where('parts_cart_source_key', 'pt:item-pads')->delete();

    $this->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('lines.0.import_status', PartsCartImportLedger::STATUS_NEW);
});

test('parts imported into an approved concern wait for the customer and leave the approved amount alone', function () {
    [$repairOrder, $brakes] = cartManagerRepairOrder();
    $repairOrder->update(['status' => RepairOrderStatus::WaitingApproval]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $brakes->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Replace front pads',
        'quantity' => '1.00',
        'unit_price' => '200.00',
    ])->assertRedirect();

    $opened = $repairOrder->fresh(['concerns', 'lines.concern']);
    $this->post(route('operations.repair-orders.authorization.store', $repairOrder), [
        'source' => ApprovalSource::Phone->value,
        'approved_by' => 'Synthetic Cart',
        RepairOrderConcurrency::FIELD => (int) $opened->estimate_version,
        RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)->pendingChangesFingerprint($opened),
    ])->assertRedirect();

    expect($brakes->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved);

    $calculator = app(EstimateTotalsCalculator::class);
    $authorization = app(WorkCompletionAuthorization::class);
    $labor = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();
    $laborBefore = $labor->getAttributes();
    $approvedBefore = $calculator->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))->totalCents();

    cartManagerImport($repairOrder, [
        ['source_key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'pricing_matrix_key' => 'aft-parts'],
    ])->assertOk();

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $imported = RepairOrderLine::query()->where('parts_cart_source_key', 'pt:item-pads')->sole();

    expect($authorization->linesRequiringAuthorization($repairOrder)->pluck('id')->all())->toBe([$imported->id])
        ->and($authorization->customerApprovalCovers($imported->fresh(['concern'])))->toBeFalse()
        ->and($calculator->approvedTotalsForRead($repairOrder)->totalCents())->toBe($approvedBefore)
        ->and($labor->fresh()->getAttributes())->toBe($laborBefore)
        ->and($brakes->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved);
});

test('an import from an estimate opened before another change is refused', function () {
    [$repairOrder, $brakes] = cartManagerRepairOrder();
    $staleVersion = (int) $repairOrder->fresh()->estimate_version;

    cartManagerImport($repairOrder, [
        ['source_key' => 'pt:item-pads', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '82.50', 'pricing_matrix_key' => 'aft-parts'],
    ])->assertOk();

    $this->postJson(route('operations.repair-orders.partstech.import', $repairOrder), [
        RepairOrderConcurrency::FIELD => $staleVersion,
        'assignments' => [
            ['source_key' => 'pt:item-filter', 'repair_order_concern_id' => $brakes->id, 'part_cost' => '10.00', 'pricing_matrix_key' => 'aft-parts'],
        ],
    ])->assertStatus(409)->assertJsonPath('conflict', true);

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);
});

/**
 * @return array{0: RepairOrder}
 */
function cartManagerRepairOrderOther(): array
{
    $customer = Customer::query()->create(['first_name' => 'Other', 'last_name' => 'Shopper', 'phone' => '555-0143']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2015, 'make' => 'Ford', 'model' => 'Focus']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Another visit',
    ]);
    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Other concern',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 0,
    ]);

    return [$repairOrder];
}
