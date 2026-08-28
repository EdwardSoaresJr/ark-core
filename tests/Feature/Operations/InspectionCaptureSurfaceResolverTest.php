<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\InspectionCaptureSurfaceResolver;
use App\Ark\Operations\Inspections\InspectionCoverageProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

function captureSurfaceRepairOrder(?User $technician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Capture',
        'last_name' => 'Surface',
        'mobile_phone' => '7195550199',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'assigned_technician_id' => $technician?->id,
        'concern_summary' => 'Capture surface RO',
    ]);
}

test('capture surface resolver prefers bay shell for tablets and phones', function () {
    expect(InspectionCaptureSurfaceResolver::resolve(Request::create('/', 'GET', server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)',
    ])))->toBe(InspectionCaptureSurfaceResolver::TABLET);

    expect(InspectionCaptureSurfaceResolver::resolve(Request::create('/', 'GET', server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
    ])))->toBe(InspectionCaptureSurfaceResolver::TABLET);

    expect(InspectionCaptureSurfaceResolver::resolve(Request::create('/', 'GET', server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
    ])))->toBe(InspectionCaptureSurfaceResolver::DESKTOP_WALK);
});

test('capture surface resolver honors explicit override query', function () {
    $desktopForced = Request::create('/?capture_surface=desktop_walk', 'GET', server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)',
    ]);

    expect(InspectionCaptureSurfaceResolver::resolve($desktopForced))
        ->toBe(InspectionCaptureSurfaceResolver::DESKTOP_WALK);
});

test('technician coverage capture url uses tablet surface for ipad user agent', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = captureSurfaceRepairOrder($technician);

    $request = Request::create(
        route('operations.repair-orders.show', $repairOrder),
        'GET',
        server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)'],
    );
    app()->instance('request', $request);

    $coverage = InspectionCoverageProjection::for($repairOrder, $technician);

    expect($coverage['capture_surface'])->toBe(InspectionCaptureSurfaceResolver::TABLET)
        ->and($coverage['capture_url'])->toBe($coverage['tablet_url'])
        ->and($coverage['capture_url'])->toContain('surface=tablet')
        ->and($coverage['cta_label'])->toBe('Open Inspection');
});
