<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Printing\KeyTagPrintContext;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;

test('key tag context uses shop name customer vehicle and vin last six', function () {
    ShopSettings::current()->update(['shop_name' => 'Auto Repair Keeper']);

    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => '2HGFC2F59KH123456',
        'plate' => '4XYZ789',
        'plate_state' => 'TX',
        'nickname' => 'Daily',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Oil change',
    ]);

    $ctx = KeyTagPrintContext::fromRepairOrder($repairOrder, 'last6');

    expect($ctx->businessNameLine)->toBe('Auto Repair Keeper')
        ->and($ctx->customerName)->toBe('Jane Driver')
        ->and($ctx->vehicleLine)->toBe('2019 Honda Civic')
        ->and($ctx->licensePlateLine)->toBe('Daily 4XYZ789 TX')
        ->and($ctx->vinLineForKeyTag())->toBe('VIN 123456');
});
