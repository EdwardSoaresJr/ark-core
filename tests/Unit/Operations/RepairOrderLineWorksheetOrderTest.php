<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderLineWorksheetOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\ShopSettingsSeeder;


beforeEach(function (): void {
    $this->seed(ShopSettingsSeeder::class);
});

test('worksheet order ranks labor before parts before sublets before notes', function () {
    expect(RepairOrderLineWorksheetOrder::rank(RepairOrderLineType::Labor))->toBe(10)
        ->and(RepairOrderLineWorksheetOrder::rank(RepairOrderLineType::Package))->toBe(20)
        ->and(RepairOrderLineWorksheetOrder::rank(RepairOrderLineType::Part))->toBe(30)
        ->and(RepairOrderLineWorksheetOrder::rank(RepairOrderLineType::Sublet))->toBe(40)
        ->and(RepairOrderLineWorksheetOrder::rank(RepairOrderLineType::Fee))->toBe(50)
        ->and(RepairOrderLineWorksheetOrder::rank(RepairOrderLineType::Note))->toBe(60);
});

test('worksheet order sort places labor before part even when part was created first', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $customer = Customer::query()->create([
        'first_name' => 'Sort',
        'last_name' => 'Order',
        'phone' => '7195551313',
        'customer_type' => 'Retail',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 88002,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Cold start rattle',
        'opened_at' => now('UTC'),
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Cold start rattle',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Timing chain tensioner',
        'quantity' => 1,
        'unit_price_cents' => 8550,
        'subtotal_cents' => 8550,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => 8550,
    ]);
    $labor = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnose cold start rattle',
        'quantity' => 1.5,
        'unit_price_cents' => 15000,
        'subtotal_cents' => 22500,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => 22500,
    ]);

    $sorted = RepairOrderLineWorksheetOrder::sort(
        RepairOrderLine::query()->where('repair_order_concern_id', $concern->id)->get()
    );

    expect($sorted->pluck('id')->all())->toBe([$labor->id, $part->id])
        ->and($sorted->pluck('description')->all())->toBe([
            'Diagnose cold start rattle',
            'Timing chain tensioner',
        ]);
});

test('concern lines relation returns labor then parts then notes regardless of create order', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $customer = Customer::query()->create([
        'first_name' => 'Line',
        'last_name' => 'Order',
        'phone' => '7195551212',
        'customer_type' => 'Retail',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 88001,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brakes',
        'opened_at' => now('UTC'),
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Brake pads',
        'quantity' => 1,
        'unit_price_cents' => 5000,
        'subtotal_cents' => 5000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => 5000,
    ]);
    $note = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Customer supplied rotors',
        'quantity' => 1,
        'unit_price_cents' => 0,
        'subtotal_cents' => 0,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => 0,
    ]);
    $labor = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace pads',
        'quantity' => 1.5,
        'unit_price_cents' => 16500,
        'subtotal_cents' => 24750,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => 24750,
    ]);

    $ordered = $concern->fresh()->lines()->pluck('id')->all();

    expect($ordered)->toBe([$labor->id, $part->id, $note->id]);
});
