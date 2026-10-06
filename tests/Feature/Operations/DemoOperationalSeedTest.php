<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Mail\ArkMailClient;
use App\Ark\Mail\TransactionalMailEnvelope;
use App\Ark\Mail\TransactionalMailOperation;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\DemoCommunicationsSeeder;
use Database\Seeders\DemoReportingSeeder;
use Database\Seeders\DemoScheduleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

test('demo seed puts open repair orders on the schedule and closed work into reports', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(AdminUserSeeder::class);
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
    ]);
    ShopSettings::forgetCurrent();

    $open = demoSeedRepairOrder('Open', 'Visit', RepairOrderStatus::InProgress);
    $closed = demoSeedRepairOrder('Closed', 'Visit', RepairOrderStatus::Closed);

    $this->seed(DemoScheduleSeeder::class);
    $this->seed(DemoReportingSeeder::class);

    expect(Appointment::query()->where('repair_order_id', $open->id)->exists())->toBeTrue()
        ->and(Appointment::query()->where('repair_order_id', $closed->id)->exists())->toBeFalse();

    $closed->refresh();
    expect($closed->posted_at)->not->toBeNull();

    $from = $closed->posted_at->copy()->startOfDay();
    $to = $closed->posted_at->copy()->endOfDay();
    $metrics = new OperationalReportRangeMetrics($from, $to);

    expect($metrics->postedInvoiceSalesCents())->toBeGreaterThan(0)
        ->and($metrics->postedInvoiceTotalCents())->toBe(OperationalReportTotals::cashCollectedCents($from, $to));
});

test('demo seed disconnects live email texting and voice', function () {
    config(['app.url' => 'https://demo.arksms.com']);
    Http::fake();

    ShopSettings::current()->forceFill([
        'telephony_enabled' => true,
        'telephony_provider' => 'twilio',
        'telephony_inbound_number' => '+17195550100',
        'square_email_pay_enabled' => true,
        'platform_base_url' => 'https://cloud.arksms.com',
        'platform_shop_public_id' => 'shop_demo',
        'platform_credential' => 'secret',
    ])->save();
    ShopSettings::forgetCurrent();

    $this->seed(DemoCommunicationsSeeder::class);

    $settings = ShopSettings::current();
    expect($settings->telephony_enabled)->toBeFalse()
        ->and($settings->telephony_provider)->toBe('none')
        ->and((bool) $settings->square_email_pay_enabled)->toBeFalse()
        ->and($settings->platform_credential)->toBeNull()
        ->and($settings->platform_base_url)->toBeNull();

    $result = app(ArkMailClient::class)->send(TransactionalMailEnvelope::intent(
        TransactionalMailOperation::EstimateSend,
        'customer@example.com',
        ['customer_name' => 'Demo'],
        (string) Str::uuid(),
    ));

    expect($result->ok())->toBeFalse();
    Http::assertNothingSent();
});

function demoSeedRepairOrder(string $first, string $last, RepairOrderStatus $status): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => '7195550100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => $first.' visit',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $first.' visit',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 16500,
        'total_cents' => 16500,
    ]);

    return $repairOrder;
}
