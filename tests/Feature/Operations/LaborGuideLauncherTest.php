<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\LaborGuides\LaborGuideLauncher;
use App\Ark\Operations\LaborGuides\LaborGuideProvider;
use App\Ark\Operations\RepairOrders\RepairOrderShopReference;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

test('labor guide redirects to configured entry url without query params', function () {
    config()->set('services.labor_guides.alldata.base_url', 'https://alldata.test/repair');
    config()->set('services.labor_guides.alldata.login_path', '');

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
        'concern_summary' => 'Labor guide test',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brake noise',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $response = $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.labor-guides.redirect', [
            'repairOrder' => $repairOrder,
            'provider' => LaborGuideProvider::AllData->value,
            'concern_id' => $concern->id,
        ]));

    $response->assertRedirect('https://alldata.test/repair');
});

test('labor guide handoff notice tells advisors to paste vin', function () {
    config()->set('services.labor_guides.prodemand.base_url', 'https://prodemand.test');

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
        'concern_summary' => 'Notice test',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brake noise',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $notice = app(LaborGuideLauncher::class)->handoffNotice($repairOrder, LaborGuideProvider::ProDemand, $concern->id);

    expect($notice)
        ->toContain('ProDemand')
        ->toContain('VIN 1HGCM82633A004352')
        ->toContain('paste')
        ->not->toContain('PartsTech');
});

test('labor guide opens without vin and skips clipboard copy', function () {
    config()->set('services.labor_guides.prodemand.base_url', 'https://prodemand.test');

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
        'concern_summary' => 'No VIN',
    ]);

    $launcher = app(LaborGuideLauncher::class);

    expect($launcher->launchUrl($repairOrder, LaborGuideProvider::ProDemand))
        ->toBe('https://prodemand.test')
        ->and($launcher->clipboardVin($repairOrder))->toBeNull()
        ->and($launcher->handoffNotice($repairOrder, LaborGuideProvider::ProDemand))
        ->toContain('no VIN');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.labor-guides.redirect', [
            'repairOrder' => $repairOrder,
            'provider' => LaborGuideProvider::ProDemand->value,
        ]))
        ->assertRedirect('https://prodemand.test');
});

test('labor guide clipboard vin is vin only', function () {
    config()->set('services.labor_guides.alldata.base_url', 'https://alldata.test');

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
        'concern_summary' => 'Clipboard test',
    ]);

    expect(app(LaborGuideLauncher::class)->clipboardVin($repairOrder))
        ->toBe('1HGCM82633A004352');
});

test('labor guide clipboard context includes scope summary', function () {
    config()->set('services.labor_guides.alldata.base_url', 'https://alldata.test');

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
        'concern_summary' => 'Clipboard test',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Steering vibration',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $context = app(LaborGuideLauncher::class)->clipboardContext($repairOrder, $concern->id);

    expect($context)
        ->toContain('RO '.RepairOrderShopReference::cartReference($repairOrder))
        ->toContain('VIN 1HGCM82633A004352')
        ->toContain('2018 Honda Accord')
        ->toContain('Scope: Steering vibration');
});
