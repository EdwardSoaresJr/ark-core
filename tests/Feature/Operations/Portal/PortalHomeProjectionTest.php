<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Portal\PortalHomeProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('portal home projection builds vehicle cards with active visit context', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Customer',
        'phone' => '7195551000',
        'email' => 'edward@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Overheating',
    ]);

    $home = app(PortalHomeProjection::class)->forCustomer($customer);

    expect($home['first_name'])->toBe('Edward')
        ->and($home['vehicle_cards'])->toHaveCount(1)
        ->and($home['vehicle_cards'][0]['display_name'])->toBe('2016 Ram 2500')
        ->and($home['vehicle_cards'][0]['active_visit']['summary'])->toBe('Overheating')
        ->and($home['active_visit_count'])->toBe(1);
});

test('authenticated portal home shows personalized vehicle workspace', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Customer',
        'phone' => '7195551000',
        'email' => 'edward@example.test',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    $this->actingAs($customer, 'portal')
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Welcome back', false)
        ->assertSee('Edward', false)
        ->assertSee('2016 Ram 2500', false)
        ->assertSee('needs your approval', false)
        ->assertSee('Questions?', false)
        ->assertSee('Open vehicle', false);
});
