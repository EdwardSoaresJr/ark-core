<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalIntelligence;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-06 04:00:00', config('app.timezone')));
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('diagnostic repair follow through counts ros with approved repair after diagnostic scope', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Diagnostic',
        'last_name' => 'FollowThrough',
        'phone' => '555-0144',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Electrical diagnostic path.',
        'opened_at' => OperationalReportDateScope::shopNow()->copy()->startOfDay()->addHours(9)->timezone(config('app.timezone')),
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Electrical diagnostic',
        'recommendation_intent' => 'diagnostic',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'billing_posture' => 'default',
        'position' => 1,
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Wiring repair',
        'recommendation_intent' => 'immediate_attention',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'billing_posture' => 'default',
        'position' => 2,
    ]);

    $shopToday = OperationalReportDateScope::shopDateString(now());
    $from = OperationalReportDateScope::shopNow()->copy()->startOfDay();
    $to = OperationalReportDateScope::shopNow()->copy()->endOfDay();

    $metrics = new OperationalIntelligence($from, $to);

    expect($metrics->diagnosticRepairFollowThrough())->toMatchArray([
        'diagnostic_ros' => 1,
        'repair_follow_through' => 1,
        'rate_label' => '100%',
    ])
        ->and($shopToday)->toBe('2026-06-05');
});
