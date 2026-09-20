<?php

use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use Illuminate\Support\Carbon;
use Tests\TestCase;


beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-06 04:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('resolve range interprets report dates in shop display timezone', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-05', '2026-06-05');

    expect($from->timezone('America/Denver')->toDateTimeString())->toBe('2026-06-05 00:00:00')
        ->and($to->timezone('America/Denver')->toDateTimeString())->toBe('2026-06-05 23:59:59')
        ->and(OperationalReportDateScope::weekdayCount($from, $to))->toBe(1);
});

test('resolve range defaults to shop month through shop today', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange(null, null);

    expect($from->timezone('America/Denver')->toDateString())->toBe('2026-06-01')
        ->and($to->timezone('America/Denver')->toDateString())->toBe('2026-06-05');
});

test('weekday count ignores utc calendar day when shop day is still friday', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-05', '2026-06-05');

    expect(now()->toDateString())->toBe('2026-06-06')
        ->and(OperationalReportDateScope::weekdayCount($from, $to))->toBe(1);

    [$saturdayFrom, $saturdayTo] = OperationalReportDateScope::resolveRange('2026-06-06', '2026-06-06');

    expect(OperationalReportDateScope::weekdayCount($saturdayFrom, $saturdayTo))->toBe(0);
});

test('shop open day count follows communications weekly hours and holiday closures', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'weekly_hours' => array_merge(ShopSettings::defaultTelephonyCallFlow()['weekly_hours'], [
                'saturday' => ['enabled' => true, 'open' => '09:00', 'close' => '13:00'],
            ]),
            'closed_dates' => ['2026-06-04'],
        ]),
    ]);

    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-01', '2026-06-07');

    expect(OperationalReportDateScope::shopOpenDayCount($from, $to))->toBe(5)
        ->and(TelephonyCallFlowSettings::fromShopSettings()->openDayCount($from, $to, OperationalReportDateScope::displayTimezone()))->toBe(5);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'weekly_hours' => array_merge(ShopSettings::defaultTelephonyCallFlow()['weekly_hours'], [
                'saturday' => ['enabled' => true, 'open' => '09:00', 'close' => '13:00'],
            ]),
            'closed_dates' => [],
        ]),
    ]);

    expect(OperationalReportDateScope::shopOpenDayCount($from, $to))->toBe(6);
});

test('sales closed includes native repair orders closed in range even when opened earlier', function () {
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Native',
        'last_name' => 'Close',
        'phone' => '5550111',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'repair_order_id' => 99055 + random_int(1, 99999),
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::Closed,
        'close_variant_key' => 'paid',
        'payment_status' => \App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus::Paid,
        'paid_at' => $from->copy()->addHours(10),
        'posted_at' => $from->copy()->addHours(10),
        'concern_summary' => 'Opened earlier, closed today.',
        'opened_at' => $from->copy()->subDays(5),
        'closed_at' => $from->copy()->addHours(10),
    ]);

    expect(
        OperationalReportDateScope::salesClosedBetween(\App\Ark\Operations\RepairOrders\RepairOrder::query()->whereKey($repairOrder->id), $from, $to)->exists(),
    )->toBeTrue();
});

test('sales closed excludes unpaid invoiced repair orders even when close date is in range', function () {
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Invoiced',
        'last_name' => 'Close',
        'phone' => '5550112',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Pilot',
    ]);

    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::Invoiced,
        'concern_summary' => 'Paid and invoiced today.',
        'opened_at' => $from->copy()->subDays(3),
        'closed_at' => $from->copy()->addHours(8),
    ]);

    expect(
        OperationalReportDateScope::closedBetween(\App\Ark\Operations\RepairOrders\RepairOrder::query()->whereKey($repairOrder->id), $from, $to)->exists(),
    )->toBeTrue()
        ->and(OperationalReportDateScope::salesClosedBetween(\App\Ark\Operations\RepairOrders\RepairOrder::query()->whereKey($repairOrder->id), $from, $to)->exists())
        ->toBeFalse();
});

test('sales closed still excludes imported carryover opened before the report range', function () {
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Legacy',
        'last_name' => 'Carryover',
        'phone' => '5550113',
        'legacy_arksms_customer_id' => 88001 + random_int(1, 99999),
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'Focus',
    ]);

    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'repair_order_id' => 88003 + random_int(1, 99999),
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::Closed,
        'close_variant_key' => 'paid',
        'concern_summary' => 'Imported carryover.',
        'opened_at' => OperationalReportDateScope::trustworthyDataStartsAt()->subDay(),
        'closed_at' => $from->copy()->addHours(6),
        'payment_status' => \App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus::Paid,
        'paid_at' => $from->copy()->addHours(6),
        'posted_at' => $from->copy()->addHours(6),
    ]);

    expect(
        OperationalReportDateScope::closedBetween(\App\Ark\Operations\RepairOrders\RepairOrder::query()->whereKey($repairOrder->id), $from, $to)->exists(),
    )->toBeFalse()
        ->and(OperationalReportDateScope::salesClosedBetween(\App\Ark\Operations\RepairOrders\RepairOrder::query()->whereKey($repairOrder->id), $from, $to)->exists())
        ->toBeFalse();
});
