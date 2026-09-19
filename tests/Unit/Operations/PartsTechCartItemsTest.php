<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\PartsTechCartItems;
use App\Ark\Operations\Parts\PartsTechShopReference;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('parts tech cart items ignore quantity-zero stubs left after a cleared cart', function () {
    $cart = [
        'id' => 'cart-cleared',
        'repairOrderNumber' => 'R1737',
        'orders' => [[
            'items' => [
                ['id' => 'ghost-1', 'quantity' => 0, 'partNumber' => '', 'partName' => ''],
                ['id' => 'ghost-2', 'quantity' => 0],
            ],
        ]],
    ];

    expect(PartsTechCartItems::liveCount($cart))->toBe(0);
});

test('parts tech cart items count id-only search rows as live', function () {
    $cart = [
        'orders' => [[
            'items' => [
                ['id' => 'item-1'],
            ],
        ]],
    ];

    expect(PartsTechCartItems::liveCount($cart))->toBe(1);
});

test('parts tech cart items count rows with a part identity', function () {
    $cart = [
        'orders' => [[
            'items' => [
                ['id' => 'item-1', 'quantity' => 2, 'partNumber' => 'HP-1004', 'partName' => 'Oil filter'],
            ],
        ]],
    ];

    expect(PartsTechCartItems::liveCount($cart))->toBe(1);
});

test('parts tech shop reference treats numeric carts as assigned to the R-prefixed RO', function () {
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
        'repair_order_id' => 1737,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Reference match',
    ]);

    expect(PartsTechShopReference::isAssignedCartReference('1737', $repairOrder))->toBeTrue()
        ->and(PartsTechShopReference::isAssignedCartReference('R1737', $repairOrder))->toBeTrue()
        ->and(PartsTechShopReference::isAssignedCartReference('', $repairOrder))->toBeFalse()
        ->and(PartsTechShopReference::isAssignedCartReference('R1740', $repairOrder))->toBeFalse();
});
