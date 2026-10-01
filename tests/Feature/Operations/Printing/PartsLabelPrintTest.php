<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Printing\PartsLabelPrintContext;
use App\Ark\Operations\Printing\PrintRoutingService;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\ShopSettingsSeeder;

test('parts label context uses ro ymm part number description and qty', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Brakes',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pad set ceramic',
        'quantity' => '1.00',
        'unit_price_cents' => 8900,
        'part_number' => '04565-T2A-A00',
        'subtotal_cents' => 8900,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 8900,
    ]);

    $ctx = PartsLabelPrintContext::fromLine($line->fresh(['repairOrder.vehicle']));

    expect($ctx->roNumberLine)->toBe('RO '.$repairOrder->repairOrderId())
        ->and($ctx->vehicleLine)->toBe('2019 Honda Civic')
        ->and($ctx->partNumberLine)->toBe('04565-T2A-A00')
        ->and($ctx->descriptionLine)->toBe('Front brake pad set ceramic')
        ->and($ctx->quantityLine)->toBe('Qty 1');
});

test('parts label prints one sticker per quantity with copy of total', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Brakes',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Brake rotor',
        'quantity' => '2.00',
        'unit_price_cents' => 12000,
        'part_number' => '45251-T2A-A00',
        'subtotal_cents' => 24000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 24000,
    ]);

    $line = $line->fresh(['repairOrder.vehicle']);

    expect(PartsLabelPrintContext::stickerCountForQuantity($line->quantity))->toBe(2)
        ->and(PartsLabelPrintContext::fromLine($line, 1, 2)->quantityLine)->toBe('1/2')
        ->and(PartsLabelPrintContext::fromLine($line, 2, 2)->quantityLine)->toBe('2/2');

    $urls = PartsLabelPrintContext::printUrlsForLine($repairOrder, $line);

    expect($urls)->toHaveCount(2)
        ->and($urls[0])->toContain('copy=1')
        ->and($urls[0])->toContain('of=2')
        ->and($urls[1])->toContain('copy=2')
        ->and($urls[1])->toContain('of=2');
});

test('parts rail exposes print all for received parts and per-line labels', function () {
    $this->seed([ArkAuthorizationSeeder::class, ShopSettingsSeeder::class]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingParts,
        'concern_summary' => 'Oil leak',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Valve cover',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Valve cover gasket',
        'quantity' => '2.00',
        'unit_price_cents' => 4500,
        'part_number' => '11213-0P010',
        'procurement_state' => PartProcurementState::Received,
        'subtotal_cents' => 9000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 9000,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Valve cover bolt',
        'quantity' => '1.00',
        'unit_price_cents' => 500,
        'part_number' => '90105-A12-000',
        'procurement_state' => PartProcurementState::Ordered,
        'subtotal_cents' => 500,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 500,
    ]);

    $this->get(route('operations.repair-orders.workspace-tabs.show', [$repairOrder, 'parts']))
        ->assertOk()
        ->assertSee('Print all received labels', false)
        ->assertSee('Print labels', false)
        ->assertSee('arkPrintPartsLabelsBatch', false)
        ->assertSee('copy=1', false)
        ->assertSee('of=2', false);
});

test('parts label print route rejects labor lines', function () {
    $this->seed([ArkAuthorizationSeeder::class, ShopSettingsSeeder::class]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Brakes',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace pads',
        'quantity' => '1.50',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 22500,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 22500,
    ]);

    $this->get(route('operations.repair-orders.lines.print-parts-label', [$repairOrder, $line]))
        ->assertStatus(422);
});

test('parts label routes to the key tag printer', function () {
    expect(PrintRoutingService::isKnownDocumentType(PrintRoutingService::DOC_PARTS_LABEL))->toBeTrue()
        ->and(PrintRoutingService::resolvePrinterName(PrintRoutingService::DOC_PARTS_LABEL))
        ->toBe(PrintRoutingService::resolvePrinterName(PrintRoutingService::DOC_KEY_TAG));
});
